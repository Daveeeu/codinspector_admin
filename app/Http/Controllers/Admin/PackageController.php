<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ActivityLog;
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

    /**
     * Store a newly created package in storage and in Stripe.
     */
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
            $package->is_active = true;

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
