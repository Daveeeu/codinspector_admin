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

class SubscriberController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->middleware('permission:manage subscribers');
        $this->middleware('domain');
    }

    /**
     * Megjeleníti az előfizetők listáját a Stripe-ból lekérdezve
     */
    public function index()
    {
        $domain = Auth::user()->currentDomain;

        // Lekérjük a Stripe-ból az előfizetőket
        $stripeCustomers = $this->stripeService->listCustomersForDomain($domain->domain);

        if (!$stripeCustomers['success']) {
            return view('admin.subscribers.index', [
                'subscribers' => [],
                'customers' => [],
                'error' => $stripeCustomers['error']
            ]);
        }

        // Lekérjük a csomagokat a jelenlegi domainről
        $packages = Package::where('domain_id', $domain->id)->get()->keyBy('stripe_product_id');

        // Lekérjük az előfizetéseket
        $subscriptions = [];
        if (count($stripeCustomers['data']) > 0) {
            $subscriptionsResult = $this->stripeService->listSubscriptions();

            if ($subscriptionsResult['success']) {
                // Rendezzük az előfizetéseket ügyfelek szerint
                foreach ($subscriptionsResult['data'] as $subscription) {
                    $customerId = $subscription->customer->id;
                    $subscriptions[$customerId][] = $subscription;
                }
            }
        }

        return view('admin.subscribers.index', [
            'customers' => $stripeCustomers['data'],
            'subscriptions' => $subscriptions,
            'packages' => $packages
        ]);
    }

    /**
     * Új előfizető létrehozásának űrlapja
     */
    public function create()
    {
        $domain = Auth::user()->currentDomain;
        $packages = Package::where('domain_id', $domain->id)
            ->where('is_active', true)
            ->get();

        return view('admin.subscribers.create', compact('packages'));
    }

    /**
     * Új előfizető létrehozása a Stripe-ban
     */
    public function store(Request $request)
    {
        $domain = Auth::user()->currentDomain;

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:2',
            'tax_id' => 'nullable|string|max:255',
            'billing_cycle' => 'required|in:monthly,yearly,unit',
            'units' => 'required_if:billing_cycle,unit|nullable|integer|min:1',
            'subscription_start' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Lekérjük a csomagot
            $package = Package::findOrFail($request->package_id);

            // Ellenőrizzük, hogy a csomag a jelenlegi domainhez tartozik-e
            if ($package->domain_id !== $domain->domain) {
                return redirect()->back()
                    ->with('error', 'A kiválasztott csomag nem ehhez a domainhez tartozik.')
                    ->withInput();
            }

            // Létrehozzuk az ügyfelet a Stripe-ban
            $customerData = [
                'name' => $request->company_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'tax_id' => $request->tax_id,
                'metadata' => [
                    'domain_id' => $domain->domain,
                    'created_by' => Auth::id()
                ]
            ];

            $customerResult = $this->stripeService->createStripeCustomer($customerData);

            if (!$customerResult['success']) {
                return redirect()->back()
                    ->with('error', 'Sikertelen ügyfél létrehozás a Stripe-ban: ' . $customerResult['error'])
                    ->withInput();
            }

            $customerId = $customerResult['customer_id'];

            // Ha a számlázási ciklus nem egységalapú, akkor előfizetést hozunk létre
            if ($request->billing_cycle !== 'unit') {
                $subscriptionData = [
                    'customer_id' => $customerId,
                    'package' => $package,
                    'billing_cycle' => $request->billing_cycle,
                    'subscription_start' => $request->subscription_start,
                    'metadata' => [
                        'domain_id' => $domain->domain,
                        'created_by' => Auth::id()
                    ]
                ];

                $subscriptionResult = $this->stripeService->createStripeSubscription($subscriptionData);

                if (!$subscriptionResult['success']) {
                    // Ha sikertelen az előfizetés létrehozása, töröljük az ügyfelet is
                    $this->stripeService->deleteStripeCustomer($customerId);

                    return redirect()->back()
                        ->with('error', 'Sikertelen előfizetés létrehozás a Stripe-ban: ' . $subscriptionResult['error'])
                        ->withInput();
                }
            } else {
                // Egységalapú fizetés esetén számlát hozunk létre
                $invoiceData = [
                    'customer_id' => $customerId,
                    'package' => $package,
                    'units' => $request->units,
                    'metadata' => [
                        'domain_id' => $domain->domain,
                        'created_by' => Auth::id()
                    ]
                ];

                $invoiceResult = $this->stripeService->createStripeInvoice($invoiceData);

                if (!$invoiceResult['success']) {
                    // Ha sikertelen a számla létrehozása, töröljük az ügyfelet is
                    $this->stripeService->deleteStripeCustomer($customerId);

                    return redirect()->back()
                        ->with('error', 'Sikertelen számla létrehozás a Stripe-ban: ' . $invoiceResult['error'])
                        ->withInput();
                }
            }

            // Naplózzuk a tevékenységet
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => $domain->id,
                'action' => 'create',
                'description' => 'Új előfizető létrehozva: ' . $request->company_name,
                'model_type' => 'StripeCustomer',
                'model_id' => $customerId,
                'new_values' => json_encode([
                    'customer_id' => $customerId,
                    'name' => $request->company_name,
                    'email' => $request->email,
                    'package_id' => $package->id,
                    'billing_cycle' => $request->billing_cycle
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.subscribers.index')
                ->with('success', 'Előfizető sikeresen létrehozva a Stripe-ban.');

        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Hiba történt: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Előfizető részletes adatainak megjelenítése a Stripe-ból
     */
    public function show($customerId)
    {
        $domain = Auth::user()->currentDomain;

        // Lekérjük az ügyfél adatait a Stripe-ból
        $customerResult = $this->stripeService->getStripeCustomer($customerId);

        if (!$customerResult['success']) {
            return redirect()->route('admin.subscribers.index')
                ->with('error', 'Az előfizető nem található a Stripe-ban: ' . $customerResult['error']);
        }

        $customer = $customerResult['data'];

        // Ellenőrizzük, hogy az ügyfél ehhez a domainhez tartozik-e
        if (!isset($customer->metadata->domain_id) || $customer->metadata->domain_id != $domain->domain) {
            return redirect()->route('admin.subscribers.index')
                ->with('error', 'Ez az előfizető nem ehhez a domainhez tartozik.');
        }

        // Lekérjük az ügyfél előfizetéseit
        $subscriptionsResult = $this->stripeService->getCustomerSubscriptions($customerId);
        $subscriptions = $subscriptionsResult['success'] ? $subscriptionsResult['data'] : [];

        // Lekérjük az ügyfél számláit
        $invoicesResult = $this->stripeService->getCustomerInvoices($customerId);
        $invoices = $invoicesResult['success'] ? $invoicesResult['data'] : [];

        // Lekérjük a csomagokat
        $packages = Package::where('domain_id', $domain->id)->get()->keyBy('stripe_product_id');

        return view('admin.subscribers.show', [
            'customer' => $customer,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'packages' => $packages
        ]);
    }

    /**
     * Előfizető szerkesztésének űrlapja
     */
    public function edit($customerId)
    {
        $domain = Auth::user()->currentDomain;

        // Lekérjük az ügyfél adatait a Stripe-ból
        $customerResult = $this->stripeService->getStripeCustomer($customerId);

        if (!$customerResult['success']) {
            return redirect()->route('admin.subscribers.index')
                ->with('error', 'Az előfizető nem található a Stripe-ban: ' . $customerResult['error']);
        }

        $customer = $customerResult['data'];

        // Ellenőrizzük, hogy az ügyfél ehhez a domainhez tartozik-e
        if (!isset($customer->metadata->domain_id) || $customer->metadata->domain_id != $domain->domain) {
            return redirect()->route('admin.subscribers.index')
                ->with('error', 'Ez az előfizető nem ehhez a domainhez tartozik.');
        }

        // Lekérjük az elérhető csomagokat
        $packages = Package::where('domain_id', $domain->id)
            ->where('is_active', true)
            ->get();

        return view('admin.subscribers.edit', [
            'customer' => $customer,
            'packages' => $packages
        ]);
    }

    /**
     * Előfizető adatainak frissítése a Stripe-ban
     */
    public function update(Request $request, $customerId)
    {
        $domain = Auth::user()->currentDomain;

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:2',
            'tax_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Először lekérjük az ügyfél aktuális adatait
            $customerResult = $this->stripeService->getStripeCustomer($customerId);

            if (!$customerResult['success']) {
                return redirect()->route('admin.subscribers.index')
                    ->with('error', 'Az előfizető nem található a Stripe-ban: ' . $customerResult['error']);
            }

            $customer = $customerResult['data'];

            // Ellenőrizzük, hogy az ügyfél ehhez a domainhez tartozik-e
            if (!isset($customer->metadata->domain_id) || $customer->metadata->domain_id != $domain->domain) {
                return redirect()->route('admin.subscribers.index')
                    ->with('error', 'Ez az előfizető nem ehhez a domainhez tartozik.');
            }

            // Frissítjük az ügyfél adatait a Stripe-ban
            $customerData = [
                'name' => $request->company_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'tax_id' => $request->tax_id,
                'metadata' => [
                    'domain_id' => $domain->domain,
                    'updated_by' => Auth::id(),
                    'updated_at' => now()->toIso8601String()
                ]
            ];

            $updateResult = $this->stripeService->updateStripeCustomer($customerId, $customerData);

            if (!$updateResult['success']) {
                return redirect()->back()
                    ->with('error', 'Sikertelen ügyfél frissítés a Stripe-ban: ' . $updateResult['error'])
                    ->withInput();
            }

            // Naplózzuk a tevékenységet
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => $domain->id,
                'action' => 'update',
                'description' => 'Előfizető frissítve: ' . $request->company_name,
                'model_type' => 'StripeCustomer',
                'model_id' => $customerId,
                'old_values' => json_encode([
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone
                ]),
                'new_values' => json_encode([
                    'name' => $request->company_name,
                    'email' => $request->email,
                    'phone' => $request->phone
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.subscribers.show', $customerId)
                ->with('success', 'Előfizető adatai sikeresen frissítve a Stripe-ban.');

        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Hiba történt: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Előfizetés lemondása/törlése a Stripe-ban
     */
    public function cancelSubscription(Request $request, $subscriptionId)
    {
        $domain = Auth::user()->currentDomain;

        try {
            // Lekérjük az előfizetés adatait
            $subscriptionResult = $this->stripeService->getStripeSubscription($subscriptionId);

            if (!$subscriptionResult['success']) {
                return redirect()->route('admin.subscribers.index')
                    ->with('error', 'Az előfizetés nem található a Stripe-ban: ' . $subscriptionResult['error']);
            }

            $subscription = $subscriptionResult['data'];

            // Ellenőrizzük, hogy az előfizetés ehhez a domainhez tartozik-e
            if (!isset($subscription->metadata->domain_id) || $subscription->metadata->domain_id != $domain->domain) {
                return redirect()->route('admin.subscribers.index')
                    ->with('error', 'Ez az előfizetés nem ehhez a domainhez tartozik.');
            }

            // Lemondás azonnal vagy a számlázási időszak végén
            $cancelImmediately = $request->has('cancel_immediately');

            $cancelResult = $this->stripeService->cancelStripeSubscription($subscriptionId, $cancelImmediately);

            if (!$cancelResult['success']) {
                return redirect()->back()
                    ->with('error', 'Sikertelen előfizetés lemondás a Stripe-ban: ' . $cancelResult['error']);
            }

            // Naplózzuk a tevékenységet
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => $domain->id,
                'action' => 'cancel',
                'description' => 'Előfizetés lemondva: ' . $subscriptionId,
                'model_type' => 'StripeSubscription',
                'model_id' => $subscriptionId,
                'old_values' => json_encode([
                    'status' => $subscription->status,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end
                ]),
                'new_values' => json_encode([
                    'cancelled_immediately' => $cancelImmediately
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->back()
                ->with('success', 'Előfizetés sikeresen lemondva a Stripe-ban.');

        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Hiba történt: ' . $e->getMessage());
        }
    }

    /**
     * Előfizető és összes kapcsolódó adatának törlése a Stripe-ban
     */
    public function destroy(Request $request, $customerId)
    {
        $domain = Auth::user()->currentDomain;

        try {
            // Lekérjük az ügyfél adatait
            $customerResult = $this->stripeService->getStripeCustomer($customerId);

            if (!$customerResult['success']) {
                return redirect()->route('admin.subscribers.index')
                    ->with('error', 'Az előfizető nem található a Stripe-ban: ' . $customerResult['error']);
            }

            $customer = $customerResult['data'];

            // Ellenőrizzük, hogy az ügyfél ehhez a domainhez tartozik-e
            if (!isset($customer->metadata->domain_id) || $customer->metadata->domain_id != $domain->domain) {
                return redirect()->route('admin.subscribers.index')
                    ->with('error', 'Ez az előfizető nem ehhez a domainhez tartozik.');
            }

            // Lekérjük és lemondjuk az összes aktív előfizetést
            $subscriptionsResult = $this->stripeService->getCustomerSubscriptions($customerId);

            if ($subscriptionsResult['success'] && count($subscriptionsResult['data']) > 0) {
                foreach ($subscriptionsResult['data'] as $subscription) {
                    if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                        $this->stripeService->cancelStripeSubscription($subscription->id, true);
                    }
                }
            }

            // Töröljük az ügyfelet a Stripe-ban
            $deleteResult = $this->stripeService->deleteStripeCustomer($customerId);

            if (!$deleteResult['success']) {
                return redirect()->back()
                    ->with('error', 'Sikertelen ügyfél törlés a Stripe-ban: ' . $deleteResult['error']);
            }

            // Naplózzuk a tevékenységet
            ActivityLog::create([
                'user_id' => Auth::id(),
                'domain_id' => $domain->id,
                'action' => 'delete',
                'description' => 'Előfizető törölve: ' . $customer->name,
                'model_type' => 'StripeCustomer',
                'model_id' => $customerId,
                'old_values' => json_encode([
                    'name' => $customer->name,
                    'email' => $customer->email
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.subscribers.index')
                ->with('success', 'Előfizető sikeresen törölve a Stripe-ban.');

        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Hiba történt: ' . $e->getMessage());
        }
    }
}
