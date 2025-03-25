<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ActivityLog;
use App\Models\PackageFeature;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

class PackageController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->middleware('permission:manage packages');
        $this->middleware('domain');
    }

    /**
     * Display a listing of packages.
     */
    public function index()
    {
        $domain = Auth::user()->currentDomain;
        $packages = Package::where('domain_id', $domain->id)->get();

        return view('admin.packages.index', compact('packages'));
    }

    /**
     * Show the form for creating a new package.
     */
    public function create()
    {
        return view('admin.packages.create');
    }

    public function store(Request $request)
    {
        $domain = Auth::user()->currentDomain;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'billing_type' => 'required|in:monthly,yearly,unit',
            'monthly_price' => 'required_if:billing_type,monthly,unit|nullable|numeric|min:0',
            'yearly_price' => 'required_if:billing_type,yearly|nullable|numeric|min:0',
            'unit_price' => 'required_if:billing_type,unit|nullable|numeric|min:0',
            'max_queries' => 'nullable|integer|min:1',
            'is_premium' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Begin transaction
            \DB::beginTransaction();

            // Create package in database first
            $package = new Package();
            $package->domain_id = $domain->id;
            $package->name = $request->name;
            $package->description = $request->description;
            $package->billing_type = $request->billing_type;
            $package->is_active = $request->has('is_active');
            $package->is_premium = $request->has('is_premium');

            // Set the max_queries based on billing type
            if ($request->billing_type !== 'unit') {
                $package->max_queries = $request->max_queries;
            }

            // Set prices based on billing type
            if ($request->billing_type === 'monthly' || $request->billing_type === 'unit') {
                $package->monthly_price = $request->monthly_price;
            }

            if ($request->billing_type === 'yearly') {
                $package->yearly_price = $request->yearly_price;
            }

            if ($request->billing_type === 'unit') {
                $package->unit_price = $request->unit_price;
            }

            $package->save();

            // Process features if provided
            if ($request->has('features') && is_array($request->features)) {
                $features = [];
                foreach ($request->features as $feature) {
                    if (isset($feature['name']) && !empty($feature['name'])) {
                        // Create a new package feature
                        $packageFeature = new PackageFeature();
                        $packageFeature->package_id = $package->id;
                        $packageFeature->name = $feature['name'];
                        $packageFeature->is_included = isset($feature['included']);
                        $packageFeature->save();

                        $features[] = [
                            'name' => $feature['name'],
                            'included' => isset($feature['included'])
                        ];
                    }
                }

                // Store features as metadata for Stripe
                $package->features_metadata = json_encode($features);
                $package->save();
            }

            // Create in Stripe
            $stripeResult = $this->stripeService->createProduct($package);

            if (!$stripeResult['success']) {
                \DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Failed to create package in Stripe: ' . $stripeResult['error'])
                    ->withInput();
            }

            // Update with Stripe IDs
            $package->stripe_product_id = $stripeResult['product_id'];

            if (isset($stripeResult['prices']['monthly'])) {
                $package->stripe_monthly_price_id = $stripeResult['prices']['monthly'];
            }

            if (isset($stripeResult['prices']['yearly'])) {
                $package->stripe_yearly_price_id = $stripeResult['prices']['yearly'];
            }

            if (isset($stripeResult['prices']['unit'])) {
                $package->stripe_unit_price_id = $stripeResult['prices']['unit'];
            }

            $package->save();

            // Commit transaction
            \DB::commit();

            // Log the activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => $domain->id,
                'action' => 'create',
                'description' => 'Created new package: ' . $package->name,
                'model_type' => Package::class,
                'model_id' => $package->id,
                'new_values' => json_encode($package->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.packages.index')
                ->with('success', 'Package created successfully and synchronized with Stripe.');

        } catch (Exception $e) {
            \DB::rollBack();
            return redirect()->back()
                ->with('error', 'An error occurred: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified package.
     */
    public function edit(Package $package)
    {
        // Check if the package belongs to the current domain
        if ($package->domain_id !== Auth::user()->current_domain_id) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'You can only edit packages from your selected domain.');
        }

        return view('admin.packages.edit', compact('package'));
    }

    /**
     * Update the specified package in storage and in Stripe.
     */
    public function update(Request $request, Package $package)
    {
        // Check if the package belongs to the current domain
        if ($package->domain_id !== Auth::user()->current_domain_id) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'You can only update packages from your selected domain.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_premium' => 'boolean',
            'max_queries' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'nullable|boolean',
            'features.*.id' => 'nullable|exists:package_features,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            \DB::beginTransaction();

            $oldValues = $package->toArray();

            // Update package in the database
            $package->name = $request->name;
            $package->description = $request->description;
            $package->is_active = $request->has('is_active');
            $package->is_premium = $request->has('is_premium');

            // Only update max_queries for non-unit billing types
            if ($package->billing_type !== 'unit') {
                $package->max_queries = $request->max_queries;
            }

            // Process features
            if ($request->has('features') && is_array($request->features)) {
                // Keep track of processed feature IDs
                $processedFeatureIds = [];
                $features = [];

                foreach ($request->features as $index => $featureData) {
                    if (!isset($featureData['name']) || empty($featureData['name'])) {
                        continue;
                    }

                    // Check if this is an existing feature being updated
                    if (isset($featureData['id'])) {
                        $feature = PackageFeature::where('id', $featureData['id'])
                            ->where('package_id', $package->id)
                            ->first();

                        if ($feature) {
                            $feature->name = $featureData['name'];
                            $feature->is_included = isset($featureData['included']);
                            $feature->save();

                            $processedFeatureIds[] = $feature->id;
                        }
                    } else {
                        // Create a new feature
                        $feature = new PackageFeature();
                        $feature->package_id = $package->id;
                        $feature->name = $featureData['name'];
                        $feature->is_included = isset($featureData['included']);
                        $feature->order = $index;
                        $feature->save();

                        $processedFeatureIds[] = $feature->id;
                    }

                    // Add to features array for metadata
                    $features[] = [
                        'name' => $featureData['name'],
                        'included' => isset($featureData['included'])
                    ];
                }

                // Delete any features that weren't in the request (removed by user)
                if (!empty($processedFeatureIds)) {
                    PackageFeature::where('package_id', $package->id)
                        ->whereNotIn('id', $processedFeatureIds)
                        ->delete();
                } else {
                    // If no features were processed, delete all
                    PackageFeature::where('package_id', $package->id)->delete();
                }

                // Update features metadata
                $package->features_metadata = json_encode($features);
            } else {
                // No features were sent, delete all existing ones
                PackageFeature::where('package_id', $package->id)->delete();
                $package->features_metadata = null;
            }

            $package->save();

            // Update product in Stripe
            $stripeResult = $this->stripeService->updateProduct($package);

            if (!$stripeResult['success']) {
                \DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Failed to update package in Stripe: ' . $stripeResult['error'])
                    ->withInput();
            }

            \DB::commit();

            // Log the activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => Auth::user()->current_domain_id,
                'action' => 'update',
                'description' => 'Updated package: ' . $package->name,
                'model_type' => Package::class,
                'model_id' => $package->id,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($package->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.packages.index')
                ->with('success', 'Package updated successfully and synchronized with Stripe.');

        } catch (Exception $e) {
            \DB::rollBack();
            return redirect()->back()
                ->with('error', 'An error occurred: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified package from storage and archive in Stripe.
     */
    public function destroy(Request $request, Package $package)
    {
        // Check if the package belongs to the current domain
        if ($package->domain_id !== Auth::user()->current_domain_id) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'You can only delete packages from your selected domain.');
        }

        // Check if package has subscribers
        if ($package->subscribers()->count() > 0) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Cannot delete package with active subscribers.');
        }

        try {
            \DB::beginTransaction();

            $packageDetails = $package->toArray();

            // Archive in Stripe (don't delete completely)
            if ($package->stripe_product_id) {
                $stripeResult = $this->stripeService->archiveProduct($package);

                if (!$stripeResult['success']) {
                    \DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Failed to archive package in Stripe: ' . $stripeResult['error']);
                }
            }

            // Delete from database
            $package->delete();

            \DB::commit();

            // Log the activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => Auth::user()->current_domain_id,
                'action' => 'delete',
                'description' => 'Deleted package: ' . $packageDetails['name'],
                'model_type' => Package::class,
                'model_id' => $packageDetails['id'],
                'old_values' => json_encode($packageDetails),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.packages.index')
                ->with('success', 'Package deleted successfully.');

        } catch (Exception $e) {
            \DB::rollBack();
            return redirect()->back()
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}
