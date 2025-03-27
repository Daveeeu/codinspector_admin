<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ActivityLog;
use App\Models\PackageFeature;
use App\Services\StripeService;
use App\Services\DomainConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Exception;

class PackageController extends Controller
{
    protected $stripeService;
    protected $domainConnectionService;

    public function __construct(StripeService $stripeService, DomainConnectionService $domainConnectionService)
    {
        $this->stripeService = $stripeService;
        $this->domainConnectionService = $domainConnectionService;
        $this->middleware('permission:manage packages');
        $this->middleware('domain');
    }

    /**
     * Display a listing of packages.
     */
    public function index()
    {
        $domain = Auth::user()->currentDomain;
        $db = $this->domainConnectionService->getDomainConnection();

        // Get packages from domain connection
        $packages = $db->table('packages')
            ->get();

        // Transform to Package models for the view
        $transformedPackages = [];
        foreach ($packages as $package) {
            $packageModel = new Package();
            foreach ((array)$package as $key => $value) {
                $packageModel->{$key} = $value;
            }

            // Add computed properties for the view
            if ($packageModel->cost_per_query > 0) {
                $packageModel->billing_type = 'unit';
                $packageModel->unit_price = $packageModel->cost_per_query;
            } elseif ($packageModel->cost_yearly > 0) {
                $packageModel->billing_type = 'yearly';
                $packageModel->yearly_price = $packageModel->cost_yearly;
            } else {
                $packageModel->billing_type = 'monthly';
                $packageModel->monthly_price = $packageModel->cost;
            }

            $packageModel->is_active = true; // Default to true if not present
            $packageModel->domain = $domain;

            // Get associated role if exists
            $permissions = json_decode($packageModel->permissions, true) ?: [];
            if (isset($permissions['role_id'])) {
                $role = $db->table('roles')
                    ->where('id', $permissions['role_id'])
                    ->first();
                if ($role) {
                    $packageModel->role_name = $role->name;
                }
            }

            $transformedPackages[] = $packageModel;
        }

        return view('admin.packages.index', ['packages' => $transformedPackages]);
    }

    /**
     * Show the form for creating a new package.
     */
    public function create()
    {
        // Fetch all available permissions
        $db = $this->domainConnectionService->getDomainConnection();
        $permissions = $db->table('permissions')->get();

        return view('admin.packages.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $domain = Auth::user()->currentDomain;
        $db = $this->domainConnectionService->getDomainConnection();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'billing_type' => 'required|in:monthly,yearly,unit',
            'monthly_price' => 'required_if:billing_type,monthly|nullable|numeric|min:0',
            'yearly_price' => 'required_if:billing_type,yearly|nullable|numeric|min:0',
            'unit_price' => 'required_if:billing_type,unit|nullable|numeric|min:0',
            'max_queries' => 'nullable|integer|min:1',
            'is_premium' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'role_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Begin transaction
            $db->beginTransaction();

            // Create role for package
            $roleId = null;
            if ($request->has('permissions') && !empty($request->permissions) && $request->filled('role_name')) {
                // Create role
                $roleId = $db->table('roles')->insertGetId([
                    'name' => $request->role_name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Attach permissions to role
                foreach ($request->permissions as $permissionId) {
                    $db->table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }

            // Directly insert into domain database
            $packageId = $db->table('packages')->insertGetId([
                'name' => $request->name,
                'description' => $request->description,
                'query_limit' => $request->billing_type !== 'unit' ? $request->input('max_queries') : 1,
                'cost' => $request->billing_type === 'monthly' ? $request->monthly_price : 0,
                'cost_yearly' => $request->billing_type === 'yearly' ? $request->yearly_price : 0,
                'cost_per_query' => $request->billing_type === 'unit' ? $request->unit_price : 0,
                'premium' => $request->has('is_premium') ? 1 : 0,
                'permissions' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Process features if provided
            $features = [];
            if ($request->has('features') && is_array($request->features)) {
                foreach ($request->features as $feature) {
                    if (isset($feature['name']) && !empty($feature['name'])) {
                        // Insert feature directly to domain database
                        $db->table('package_features')->insert([
                            'package_id' => $packageId,
                            'name' => $feature['name'],
                            'is_included' => isset($feature['included']) ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $features[] = [
                            'name' => $feature['name'],
                            'included' => isset($feature['included']) ? true : false
                        ];
                    }
                }
            }

            // Prepare permissions with features and role
            $permissions = [
                'features' => $features
            ];

            if ($roleId) {
                $permissions['role_id'] = $roleId;
                $permissions['role_name'] = $request->role_name;

                // Attach role to package in model_has_roles table
                $db->table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\Package',
                    'model_id' => $packageId
                ]);
            }

            // Update package with permissions JSON
            $db->table('packages')
                ->where('package_id', $packageId)
                ->update([
                    'permissions' => json_encode($permissions)
                ]);

            // Create a Package model instance for Stripe
            $package = new Package();
            $package->package_id = $packageId;
            $package->name = $request->name;
            $package->description = $request->description;
            $package->query_limit = $request->billing_type !== 'unit' ? $request->input('max_queries') : null;
            $package->cost = $request->billing_type === 'monthly' ? $request->monthly_price : 0;
            $package->cost_yearly = $request->billing_type === 'yearly' ? $request->yearly_price : 0;
            $package->cost_per_query = $request->billing_type === 'unit' ? $request->unit_price : 0;
            $package->premium = $request->has('is_premium') ? 1 : 0;
            $package->permissions = json_encode($permissions);
            $package->domain = $domain;
            $package->billing_type = $request->billing_type;

            // Set appropriate prices for Stripe based on billing type
            if ($request->billing_type === 'monthly') {
                $package->monthly_price = $request->monthly_price;
            } else if ($request->billing_type === 'yearly') {
                $package->yearly_price = $request->yearly_price;
            } else if ($request->billing_type === 'unit') {
                $package->unit_price = $request->unit_price;
            }

            // Create in Stripe
            $stripeResult = $this->stripeService->createProduct($package);

            if (!$stripeResult['success']) {
                $db->rollBack();
                return redirect()->back()
                    ->with('error', 'Failed to create package in Stripe: ' . $stripeResult['error'])
                    ->withInput();
            }

            // Update with Stripe IDs
            $updateData = [];

            // Store monthly price ID
            if (isset($stripeResult['prices']['monthly'])) {
                $updateData['stripe_price_id'] = $stripeResult['prices']['monthly'];
            }

            // Store yearly price ID
            if (isset($stripeResult['prices']['yearly'])) {
                $updateData['stripe_price_yearly_id'] = $stripeResult['prices']['yearly'];
            }

            // Update permissions JSON with Stripe product ID and unit price ID
            if (isset($stripeResult['product_id']) || isset($stripeResult['prices']['unit'])) {
                if (isset($stripeResult['product_id'])) {
                    $permissions['stripe_product_id'] = $stripeResult['product_id'];
                }

                if (isset($stripeResult['prices']['unit'])) {
                    $permissions['stripe_unit_price_id'] = $stripeResult['prices']['unit'];
                }

                $updateData['permissions'] = json_encode($permissions);
            }

            // Update package with Stripe IDs
            if (!empty($updateData)) {
                $db->table('packages')
                    ->where('package_id', $packageId)
                    ->update($updateData);
            }

            // Commit transaction
            $db->commit();

            // Log the activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => $domain->id,
                'action' => 'create',
                'description' => 'Created new package: ' . $request->name,
                'model_type' => 'App\\Models\\Package',
                'model_id' => $packageId,
                'new_values' => json_encode($db->table('packages')->where('package_id', $packageId)->first()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.packages.index')
                ->with('success', 'Package created successfully and synchronized with Stripe.');

        } catch (Exception $e) {
            $db->rollBack();
            return redirect()->back()
                ->with('error', 'An error occurred: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified package.
     */
    public function edit($packageId)
    {
        $db = $this->domainConnectionService->getDomainConnection();

        // Get package directly from domain database
        $packageData = $db->table('packages')
            ->where('package_id', $packageId)
            ->first();

        if (!$packageData) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package not found.');
        }

        // Convert to Package object
        $package = new Package();
        foreach ((array)$packageData as $key => $value) {
            $package->{$key} = $value;
        }

        // Add computed properties for the form
        if ($package->cost_per_query > 0) {
            $package->billing_type = 'unit';
            $package->unit_price = $package->cost_per_query;
        } elseif ($package->cost_yearly > 0) {
            $package->billing_type = 'yearly';
            $package->yearly_price = $package->cost_yearly;
        } else {
            $package->billing_type = 'monthly';
            $package->monthly_price = $package->cost;
        }

        // Map max_queries to the view's expected field
        $package->max_queries = $package->query_limit;

        // Get features
        $features = $db->table('package_features')
            ->where('package_id', $packageId)
            ->get();

        $package->features_list = $features;

        // Get role and permissions
        $permissions = json_decode($package->permissions, true) ?: [];
        $package->role_id = $permissions['role_id'] ?? null;
        $package->role_name = $permissions['role_name'] ?? '';

        // Get all available permissions
        $allPermissions = $db->table('permissions')->get();

        // Get role permissions if role exists
        $selectedPermissions = [];
        if ($package->role_id) {
            $rolePermissions = $db->table('role_has_permissions')
                ->where('role_id', $package->role_id)
                ->get();

            $selectedPermissions = $rolePermissions->pluck('permission_id')->toArray();
        }

        $package->selected_permissions = $selectedPermissions;

        return view('admin.packages.edit', compact('package', 'allPermissions'));
    }

    /**
     * Update the specified package in storage and in Stripe.
     */
    public function update(Request $request, $packageId)
    {
        $db = $this->domainConnectionService->getDomainConnection();

        // Check if package exists
        $packageData = $db->table('packages')
            ->where('package_id', $packageId)
            ->first();

        if (!$packageData) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package not found.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_premium' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'max_queries' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'nullable|boolean',
            'features.*.id' => 'nullable|integer',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'role_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $db->beginTransaction();

            $oldValues = (array)$packageData;

            // Determine billing type
            $billing_type = 'monthly';
            if ($packageData->cost_per_query > 0) {
                $billing_type = 'unit';
            } elseif ($packageData->cost_yearly > 0) {
                $billing_type = 'yearly';
            }

            // Update package
            $db->table('packages')
                ->where('package_id', $packageId)
                ->update([
                    'name' => $request->name,
                    'description' => $request->description,
                    'premium' => $request->has('is_premium') ? 1 : 0,
                    'query_limit' => $billing_type !== 'unit' ? $request->max_queries : $packageData->query_limit,
                    'updated_at' => now(),
                ]);

            // Process permissions
            $permissions = json_decode($packageData->permissions ?? '{}', true) ?: [];
            $currentRoleId = $permissions['role_id'] ?? null;

            // Update or create role
            if ($request->filled('role_name') && $request->has('permissions')) {
                if ($currentRoleId) {
                    // Update existing role
                    $db->table('roles')
                        ->where('id', $currentRoleId)
                        ->update([
                            'name' => $request->role_name,
                            'updated_at' => now()
                        ]);

                    // Delete existing role permissions
                    $db->table('role_has_permissions')
                        ->where('role_id', $currentRoleId)
                        ->delete();

                    // Add new permissions
                    foreach ($request->permissions as $permissionId) {
                        $db->table('role_has_permissions')->insert([
                            'permission_id' => $permissionId,
                            'role_id' => $currentRoleId,
                        ]);
                    }

                    // Update role info in permissions JSON
                    $permissions['role_name'] = $request->role_name;
                } else {
                    // Create new role
                    $roleId = $db->table('roles')->insertGetId([
                        'name' => $request->role_name,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Add permissions to role
                    foreach ($request->permissions as $permissionId) {
                        $db->table('role_has_permissions')->insert([
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ]);
                    }

                    // Add role info to permissions JSON
                    $permissions['role_id'] = $roleId;
                    $permissions['role_name'] = $request->role_name;

                    // Attach role to package in model_has_roles table
                    $db->table('model_has_roles')->insert([
                        'role_id' => $roleId,
                        'model_type' => 'App\\Models\\Package',
                        'model_id' => $packageId
                    ]);
                }
            } elseif ($currentRoleId && (!$request->filled('role_name') || !$request->has('permissions'))) {
                // Remove role association in model_has_roles
                $db->table('model_has_roles')
                    ->where('role_id', $currentRoleId)
                    ->where('model_type', 'App\\Models\\Package')
                    ->where('model_id', $packageId)
                    ->delete();

                // Remove role if no permissions or role name
                $db->table('role_has_permissions')
                    ->where('role_id', $currentRoleId)
                    ->delete();

                $db->table('roles')
                    ->where('id', $currentRoleId)
                    ->delete();

                unset($permissions['role_id']);
                unset($permissions['role_name']);
            }

            // Process features
            $features = [];
            $processedFeatureIds = [];

            if ($request->has('features') && is_array($request->features)) {
                foreach ($request->features as $index => $featureData) {
                    if (!isset($featureData['name']) || empty($featureData['name'])) {
                        continue;
                    }

                    // Check if this is an existing feature being updated
                    if (isset($featureData['id'])) {
                        $featureExists = $db->table('package_features')
                            ->where('id', $featureData['id'])
                            ->where('package_id', $packageId)
                            ->exists();

                        if ($featureExists) {
                            $db->table('package_features')
                                ->where('id', $featureData['id'])
                                ->update([
                                    'name' => $featureData['name'],
                                    'is_included' => isset($featureData['included']) ? 1 : 0,
                                    'updated_at' => now(),
                                ]);

                            $processedFeatureIds[] = $featureData['id'];
                        }
                    } else {
                        // Create a new feature
                        $featureId = $db->table('package_features')->insertGetId([
                            'package_id' => $packageId,
                            'name' => $featureData['name'],
                            'is_included' => isset($featureData['included']) ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $processedFeatureIds[] = $featureId;
                    }

                    // Add to features array for metadata
                    $features[] = [
                        'name' => $featureData['name'],
                        'included' => isset($featureData['included']) ? true : false
                    ];
                }

                // Delete any features that weren't in the request
                if (!empty($processedFeatureIds)) {
                    $db->table('package_features')
                        ->where('package_id', $packageId)
                        ->whereNotIn('id', $processedFeatureIds)
                        ->delete();
                } else {
                    // If no features were processed, delete all
                    $db->table('package_features')
                        ->where('package_id', $packageId)
                        ->delete();
                }

                // Update features in permissions
                $permissions['features'] = $features;
            } else {
                // No features were sent, delete all existing ones
                $db->table('package_features')
                    ->where('package_id', $packageId)
                    ->delete();

                // Remove features from permissions
                if (isset($permissions['features'])) {
                    unset($permissions['features']);
                }
            }

            // Update permissions in database
            $db->table('packages')
                ->where('package_id', $packageId)
                ->update([
                    'permissions' => json_encode($permissions),
                ]);

            // Create a Package model instance for Stripe
            $updatedPackageData = $db->table('packages')
                ->where('package_id', $packageId)
                ->first();

            $package = new Package();
            foreach ((array)$updatedPackageData as $key => $value) {
                $package->{$key} = $value;
            }

            // Set stripe_product_id from permissions for Stripe service
            if (isset($permissions['stripe_product_id'])) {
                $package->stripe_product_id = $permissions['stripe_product_id'];
            }

            // Update product in Stripe
            $stripeResult = $this->stripeService->updateProduct($package);

            if (!$stripeResult['success']) {
                $db->rollBack();
                return redirect()->back()
                    ->with('error', 'Failed to update package in Stripe: ' . $stripeResult['error'])
                    ->withInput();
            }

            $db->commit();

            // Log the activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => Auth::user()->current_domain_id,
                'action' => 'update',
                'description' => 'Updated package: ' . $request->name,
                'model_type' => 'App\\Models\\Package',
                'model_id' => $packageId,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode((array)$updatedPackageData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.packages.index')
                ->with('success', 'Package updated successfully and synchronized with Stripe.');

        } catch (Exception $e) {
            $db->rollBack();
            return redirect()->back()
                ->with('error', 'An error occurred: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified package from storage and archive in Stripe.
     */
    public function destroy(Request $request, $packageId)
    {
        $db = $this->domainConnectionService->getDomainConnection();

        // Check if package exists
        $packageData = $db->table('packages')
            ->where('package_id', $packageId)
            ->first();

        if (!$packageData) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package not found.');
        }

        // Check if package has subscribers
        $hasSubscribers = $db->table('subscriptions')
            ->where('package_id', $packageId)
            ->where('status', 'active')
            ->exists();

        if ($hasSubscribers) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Cannot delete package with active subscribers.');
        }

        try {
            $db->beginTransaction();

            $packageDetails = (array)$packageData;
            $permissions = json_decode($packageData->permissions ?? '{}', true) ?: [];

            // Delete associated role if exists
            if (isset($permissions['role_id'])) {
                $roleId = $permissions['role_id'];

                // Remove role association in model_has_roles
                $db->table('model_has_roles')
                    ->where('role_id', $roleId)
                    ->where('model_type', 'App\\Models\\Package')
                    ->where('model_id', $packageId)
                    ->delete();

                // Delete role permissions
                $db->table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->delete();

                // Delete role
                $db->table('roles')
                    ->where('id', $roleId)
                    ->delete();
            }

            // Get Stripe product ID from permissions
            $stripeProductId = $permissions['stripe_product_id'] ?? null;

            // Archive in Stripe (don't delete completely)
            if ($stripeProductId) {
                // Create a Package model instance for Stripe
                $package = new Package();
                foreach ($packageDetails as $key => $value) {
                    $package->{$key} = $value;
                }
                $package->stripe_product_id = $stripeProductId;

                $stripeResult = $this->stripeService->archiveProduct($package);

                if (!$stripeResult['success']) {
                    $db->rollBack();
                    return redirect()->back()
                        ->with('error', 'Failed to archive package in Stripe: ' . $stripeResult['error']);
                }
            }

            // Delete features
            $db->table('package_features')
                ->where('package_id', $packageId)
                ->delete();

            // Delete package
            $db->table('packages')
                ->where('package_id', $packageId)
                ->delete();

            $db->commit();

            // Log the activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => Auth::user()->current_domain_id,
                'action' => 'delete',
                'description' => 'Deleted package: ' . $packageDetails['name'],
                'model_type' => 'App\\Models\\Package',
                'model_id' => $packageId,
                'old_values' => json_encode($packageDetails),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.packages.index')
                ->with('success', 'Package deleted successfully.');

        } catch (Exception $e) {
            $db->rollBack();
            return redirect()->back()
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}
