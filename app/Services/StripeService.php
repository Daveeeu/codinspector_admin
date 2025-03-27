<?php

namespace App\Services;

use App\Models\Package;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Price;
use Exception;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $stripe;

    /**
     * Konstruktor - beállítja a Stripe API kulcsot
     */
    public function __construct()
    {
        try {
            // Stripe API kulcs beállítása a konfigurációból
            Stripe::setApiKey(config('stripe.secret_key'));
            Stripe::setApiVersion('2023-10-16'); // Használjuk a legújabb stabil API verziót
            $this->stripe = new StripeClient('sk_test_51MUKdoFZvGbKbKDMfD994YTMiaFtG7mqlaxpJcSEhqnrtHOkKV45mAB7b9f1r5Mt3HwvXRczm3X7XuXGlzrhYTyu00PBr1oDvZ');
        } catch (Exception $e) {
            Log::error('Stripe inicializálási hiba: ' . $e->getMessage());
        }
    }

    /**
     * Create a product in Stripe
     *
     * @param Package $package
     * @return array
     */
    public function createProduct(Package $package)
    {
        try {
            // Create product
            $product = $this->stripe->products->create([
                'name' => $package->name,
                'description' => $package->description ?: $package->name,
                'metadata' => [
                    'package_id' => $package->package_id,
                    'premium' => $package->premium ? 'yes' : 'no',
                    'billing_type' => $package->billing_type,
                ],
            ]);

            $result = [
                'success' => true,
                'product_id' => $product->id,
                'prices' => []
            ];

            // Create prices based on billing type
            if ($package->billing_type === 'monthly') {
                // Create monthly price
                $monthlyPrice = $this->stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => round($package->monthly_price * 100), // Convert to cents
                    'currency' => $package->domain->currency,
                    'recurring' => [
                        'interval' => 'month',
                    ],
                    'metadata' => [
                        'package_id' => $package->package_id,
                        'type' => 'monthly',
                    ],
                ]);

                $result['prices']['monthly'] = $monthlyPrice->id;
            }
            else if ($package->billing_type === 'yearly') {
                // Create yearly price
                $yearlyPrice = $this->stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => round($package->yearly_price * 100), // Convert to cents
                    'currency' => $package->domain->currency,
                    'recurring' => [
                        'interval' => 'year',
                    ],
                    'metadata' => [
                        'package_id' => $package->package_id,
                        'type' => 'yearly',
                    ],
                ]);

                $result['prices']['yearly'] = $yearlyPrice->id;
            }
            else if ($package->billing_type === 'unit') {
                // Create unit-based price
                $unitPrice = $this->stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => round($package->unit_price * 100), // Convert to cents
                    'currency' => $package->domain->currency,
                    'recurring' => [
                        'interval' => 'month', // Használjunk havi ismétlődést
                        'usage_type' => 'metered', // Mérés alapú használat
                    ],
                    'metadata' => [
                        'package_id' => $package->package_id,
                        'type' => 'unit',
                    ],
                ]);

                $result['prices']['unit'] = $unitPrice->id;
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Stripe product creation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update a product in Stripe
     *
     * @param Package $package
     * @return array
     */
    public function updateProduct(Package $package)
    {
        try {
            // Get stripe product ID from permissions or directly from package
            $stripeProductId = $package->stripe_product_id ?? null;

            if (!$stripeProductId) {
                $permissions = json_decode($package->permissions, true) ?: [];
                $stripeProductId = $permissions['stripe_product_id'] ?? null;

                if (!$stripeProductId) {
                    throw new Exception('No Stripe product ID found for this package.');
                }
            }

            // Update product
            $product = Product::update($stripeProductId, [
                'name' => $package->name,
                'description' => $package->description,
                'active' => true, // Always active when updating
            ]);

            return [
                'success' => true,
                'product_id' => $product->id,
            ];
        } catch (Exception $e) {
            Log::error('Stripe product update error: ' . $e->getMessage(), [
                'package_id' => $package->package_id,
                'package_name' => $package->name
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Archive a product in Stripe
     *
     * @param Package $package
     * @return array
     */
    public function archiveProduct(Package $package)
    {
        try {
            // Get stripe product ID from permissions or directly from package
            $stripeProductId = $package->stripe_product_id ?? null;

            if (!$stripeProductId) {
                $permissions = json_decode($package->permissions, true) ?: [];
                $stripeProductId = $permissions['stripe_product_id'] ?? null;

                if (!$stripeProductId) {
                    throw new Exception('No Stripe product ID found for this package.');
                }
            }

            // Archive product
            $product = Product::update($stripeProductId, [
                'active' => false,
            ]);

            return [
                'success' => true,
                'product_id' => $product->id,
            ];
        } catch (Exception $e) {
            Log::error('Stripe product archive error: ' . $e->getMessage(), [
                'package_id' => $package->package_id,
                'package_name' => $package->name
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ügyfél lekérdezése a Stripe-ból
     *
     * @param string $customerId
     * @return array
     */
    public function getStripeCustomer($customerId)
    {
        try {
            $customer = Customer::retrieve([
                'id' => $customerId,
                'expand' => ['subscriptions', 'tax_ids']
            ]);

            return [
                'success' => true,
                'data' => $customer,
            ];
        } catch (Exception $e) {
            Log::error('Stripe ügyfél lekérdezési hiba: ' . $e->getMessage(), [
                'customer_id' => $customerId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ügyfél előfizetéseinek lekérdezése
     *
     * @param string $customerId
     * @return array
     */
    public function getCustomerSubscriptions($customerId)
    {
        try {
            $subscriptions = Subscription::all([
                'customer' => $customerId,
                'expand' => ['data.plan.product'],
                'status' => 'all'
            ]);

            return [
                'success' => true,
                'data' => $subscriptions->data,
            ];
        } catch (Exception $e) {
            Log::error('Stripe ügyfél előfizetések lekérdezési hiba: ' . $e->getMessage(), [
                'customer_id' => $customerId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ügyfél számláinak lekérdezése
     *
     * @param string $customerId
     * @return array
     */
    public function getCustomerInvoices($customerId)
    {
        try {
            $invoices = Invoice::all([
                'customer' => $customerId,
                'limit' => 100,
                'status' => 'all',
            ]);

            return [
                'success' => true,
                'data' => $invoices->data,
            ];
        } catch (Exception $e) {
            Log::error('Stripe számlák lekérdezési hiba: ' . $e->getMessage(), [
                'customer_id' => $customerId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetés lekérdezése a Stripe-ból
     *
     * @param string $subscriptionId
     * @return array
     */
    public function getStripeSubscription($subscriptionId)
    {
        try {
            $subscription = Subscription::retrieve([
                'id' => $subscriptionId,
                'expand' => ['plan.product', 'customer', 'latest_invoice']
            ]);

            return [
                'success' => true,
                'data' => $subscription,
            ];
        } catch (Exception $e) {
            Log::error('Stripe előfizetés lekérdezési hiba: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Új ügyfél létrehozása a Stripe-ban
     *
     * @param array $customerData
     * @return array
     */
    public function createStripeCustomer($customerData)
    {
        try {
            $customerParams = [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'metadata' => $customerData['metadata'] ?? [],
            ];

            // Opcionális mezők hozzáadása
            if (!empty($customerData['phone'])) {
                $customerParams['phone'] = $customerData['phone'];
            }

            if (!empty($customerData['address']) || !empty($customerData['city']) ||
                !empty($customerData['postal_code']) || !empty($customerData['country'])) {
                $customerParams['address'] = [];

                if (!empty($customerData['address'])) {
                    $customerParams['address']['line1'] = $customerData['address'];
                }

                if (!empty($customerData['city'])) {
                    $customerParams['address']['city'] = $customerData['city'];
                }

                if (!empty($customerData['postal_code'])) {
                    $customerParams['address']['postal_code'] = $customerData['postal_code'];
                }

                if (!empty($customerData['country'])) {
                    $customerParams['address']['country'] = $customerData['country'];
                }
            }

            // Adószám hozzáadása, ha meg van adva
            if (!empty($customerData['tax_id'])) {
                $customerParams['tax_id_data'] = [
                    'type' => 'eu_vat',
                    'value' => $customerData['tax_id']
                ];
            }

            $customer = Customer::create($customerParams);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'data' => $customer,
            ];
        } catch (Exception $e) {
            Log::error('Stripe ügyfél létrehozási hiba: ' . $e->getMessage(), [
                'customer_data' => json_encode($customerData)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ügyfél adatainak frissítése a Stripe-ban
     *
     * @param string $customerId
     * @param array $customerData
     * @return array
     */
    public function updateStripeCustomer($customerId, $customerData)
    {
        try {
            $customerParams = [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
            ];

            // Opcionális mezők hozzáadása
            if (isset($customerData['phone'])) {
                $customerParams['phone'] = $customerData['phone'];
            }

            // Cím adatok frissítése
            $hasAddressData = false;
            $addressData = [];

            if (isset($customerData['address'])) {
                $addressData['line1'] = $customerData['address'];
                $hasAddressData = true;
            }

            if (isset($customerData['city'])) {
                $addressData['city'] = $customerData['city'];
                $hasAddressData = true;
            }

            if (isset($customerData['postal_code'])) {
                $addressData['postal_code'] = $customerData['postal_code'];
                $hasAddressData = true;
            }

            if (isset($customerData['country'])) {
                $addressData['country'] = $customerData['country'];
                $hasAddressData = true;
            }

            if ($hasAddressData) {
                $customerParams['address'] = $addressData;
            }

            // Metadata hozzáadása, ha van
            if (isset($customerData['metadata']) && is_array($customerData['metadata'])) {
                $customerParams['metadata'] = $customerData['metadata'];
            }

            $customer = Customer::update($customerId, $customerParams);

            // Adószám külön frissítése, ha szükséges
            if (isset($customerData['tax_id']) && !empty($customerData['tax_id'])) {
                // Először lekérjük az összes adószámot
                $existingTaxIds = $customer->tax_ids->data;

                // Ha már van adószám, akkor módosítjuk
                if (count($existingTaxIds) > 0) {
                    $taxId = $existingTaxIds[0];
                    \Stripe\TaxId::update($taxId->id, [
                        'value' => $customerData['tax_id']
                    ]);
                } else {
                    // Ha nincs, akkor újat hozunk létre
                    \Stripe\TaxId::create([
                        'customer' => $customerId,
                        'type' => 'eu_vat',
                        'value' => $customerData['tax_id']
                    ]);
                }

                // Frissítjük a customer objektumot a legújabb adatokkal
                $customer = Customer::retrieve([
                    'id' => $customerId,
                    'expand' => ['tax_ids']
                ]);
            }

            return [
                'success' => true,
                'data' => $customer,
            ];
        } catch (Exception $e) {
            Log::error('Stripe ügyfél frissítési hiba: ' . $e->getMessage(), [
                'customer_id' => $customerId,
                'customer_data' => json_encode($customerData)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ügyfél törlése a Stripe-ban
     *
     * @param string $customerId
     * @return array
     */
    public function deleteStripeCustomer($customerId)
    {
        try {
            $customer = Customer::retrieve($customerId);
            $customer->delete();

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            Log::error('Stripe ügyfél törlési hiba: ' . $e->getMessage(), [
                'customer_id' => $customerId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetés létrehozása a Stripe-ban
     *
     * @param array $subscriptionData
     * @return array
     */
    public function createStripeSubscription($subscriptionData)
    {
        try {
            $package = $subscriptionData['package'];

            // Ár azonosító meghatározása a számlázási ciklus alapján
            $priceId = null;
            if ($subscriptionData['billing_cycle'] == 'monthly' && $package->stripe_price_id) {
                $priceId = $package->stripe_price_id;
            } elseif ($subscriptionData['billing_cycle'] == 'yearly' && $package->stripe_price_yearly_id) {
                $priceId = $package->stripe_price_yearly_id;
            }

            if (!$priceId) {
                throw new Exception('Nincs érvényes ár azonosító ehhez a számlázási ciklushoz.');
            }

            // Előfizetés paramétereinek összeállítása
            $subscriptionParams = [
                'customer' => $subscriptionData['customer_id'],
                'items' => [
                    ['price' => $priceId],
                ],
                'metadata' => $subscriptionData['metadata'] ?? [],
            ];

            // Számlázási horgony beállítása, ha meg van adva
            if (!empty($subscriptionData['subscription_start'])) {
                $startDate = strtotime($subscriptionData['subscription_start']);
                $subscriptionParams['billing_cycle_anchor'] = $startDate;
                $subscriptionParams['proration_behavior'] = 'none';
            }

            // Előfizetés létrehozása
            $subscription = Subscription::create($subscriptionParams);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'data' => $subscription,
            ];
        } catch (Exception $e) {
            Log::error('Stripe előfizetés létrehozási hiba: ' . $e->getMessage(), [
                'subscription_data' => json_encode($subscriptionData)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetés lemondása a Stripe-ban
     *
     * @param string $subscriptionId
     * @param bool $cancelImmediately Azonnal lemondás vagy a számlázási időszak végén
     * @return array
     */
    public function cancelStripeSubscription($subscriptionId, $cancelImmediately = false)
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);

            if ($cancelImmediately) {
                // Azonnali lemondás
                $subscription->cancel();
            } else {
                // Lemondás a számlázási időszak végén
                $subscription->cancel_at_period_end = true;
                $subscription->save();
            }

            return [
                'success' => true,
                'data' => $subscription,
            ];
        } catch (Exception $e) {
            Log::error('Stripe előfizetés lemondási hiba: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId,
                'cancel_immediately' => $cancelImmediately
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetés újraaktiválása a Stripe-ban (ha lemondásra volt állítva a számlázási időszak végén)
     *
     * @param string $subscriptionId
     * @return array
     */
    public function reactivateStripeSubscription($subscriptionId)
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);

            // Csak akkor aktiváljuk újra, ha lemondásra van állítva a számlázási időszak végén
            if ($subscription->cancel_at_period_end && $subscription->status !== 'canceled') {
                $subscription->cancel_at_period_end = false;
                $subscription->save();

                return [
                    'success' => true,
                    'data' => $subscription,
                ];
            } else if ($subscription->status === 'canceled') {
                return [
                    'success' => false,
                    'error' => 'A már lemondott előfizetést nem lehet újraaktiválni. Hozzon létre egy újat helyette.',
                ];
            }

            return [
                'success' => true,
                'data' => $subscription,
                'message' => 'Az előfizetés nem volt lemondva vagy már aktív.',
            ];
        } catch (Exception $e) {
            Log::error('Stripe előfizetés újraaktiválási hiba: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Egység alapú számla létrehozása a Stripe-ban
     *
     * @param array $invoiceData
     * @return array
     */
    public function createStripeInvoice($invoiceData)
    {
        try {
            $package = $invoiceData['package'];
            $customerId = $invoiceData['customer_id'];
            $units = $invoiceData['units'];

            // Ellenőrizzük, hogy van-e egységár
            $permissions = json_decode($package->permissions, true) ?: [];
            $unitPriceId = $permissions['stripe_unit_price_id'] ?? null;

            if (!$unitPriceId) {
                throw new Exception('A kiválasztott csomag nem támogatja az egységalapú számlázást.');
            }

            // Összeg kiszámítása
            $amount = $package->cost_per_query * $units;

            // Számlatétel létrehozása
            $invoiceItem = InvoiceItem::create([
                'customer' => $customerId,
                'amount' => (int)($amount * 100), // Centekre váltás
                'currency' => $package->domain->currency ?? 'usd',
                'description' => "{$units} egység - {$package->name}",
                'metadata' => $invoiceData['metadata'] ?? [],
            ]);

            // Számla létrehozása és azonnali küldése
            $invoice = Invoice::create([
                'customer' => $customerId,
                'auto_advance' => true, // Automatikusan váltson státuszt
                'collection_method' => 'send_invoice',
                'days_until_due' => 30, // 30 napos fizetési határidő
                'metadata' => $invoiceData['metadata'] ?? [],
            ]);

            // Számla véglegesítése
            $invoice->finalizeInvoice();

            return [
                'success' => true,
                'invoice_id' => $invoice->id,
                'data' => $invoice,
            ];
        } catch (Exception $e) {
            Log::error('Stripe számla létrehozási hiba: ' . $e->getMessage(), [
                'invoice_data' => json_encode($invoiceData)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetés módosítása (pl. csomag váltás) a Stripe-ban
     *
     * @param string $subscriptionId
     * @param Package $newPackage
     * @param string $billingCycle
     * @return array
     */
    public function changeSubscriptionPackage($subscriptionId, Package $newPackage, $billingCycle)
    {
        try {
            // Ár azonosító meghatározása a számlázási ciklus alapján
            $priceId = null;
            if ($billingCycle == 'monthly' && $newPackage->stripe_price_id) {
                $priceId = $newPackage->stripe_price_id;
            } elseif ($billingCycle == 'yearly' && $newPackage->stripe_price_yearly_id) {
                $priceId = $newPackage->stripe_price_yearly_id;
            }

            if (!$priceId) {
                throw new Exception('Nincs érvényes ár azonosító ehhez a csomaghoz és számlázási ciklushoz.');
            }

            // Lekérjük az előfizetés tételeit
            $subscription = Subscription::retrieve([
                'id' => $subscriptionId,
                'expand' => ['items']
            ]);

            // Az első előfizetési tétel azonosítója
            $subscriptionItemId = $subscription->items->data[0]->id;

            // Előfizetés módosítása
            $subscription = Subscription::update($subscriptionId, [
                'items' => [
                    [
                        'id' => $subscriptionItemId,
                        'price' => $priceId,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
                'metadata' => [
                    'package_id' => $newPackage->package_id,
                    'updated_at' => now()->toIso8601String(),
                ]
            ]);

            return [
                'success' => true,
                'data' => $subscription,
            ];
        } catch (Exception $e) {
            Log::error('Stripe előfizetés csomag módosítási hiba: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId,
                'new_package_id' => $newPackage->package_id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetések lekérdezése a Stripe-ból
     *
     * @param int $limit
     * @return array
     */
    public function listSubscriptions($limit = 100)
    {
        try {
            $subscriptions = Subscription::all([
                'limit' => $limit,
                'expand' => ['data.customer', 'data.plan.product']
            ]);

            return [
                'success' => true,
                'data' => $subscriptions->data,
                'has_more' => $subscriptions->has_more,
            ];
        } catch (Exception $e) {
            Log::error('Stripe előfizetések lekérdezési hiba: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Adott domain felhasználóinak lekérdezése
     *
     * @param int $domainId
     * @param int $limit
     * @return array
     */
    public function listCustomersForDomain($domainId, $limit = 100)
    {
        try {
            // A Search API használata a metadata.domain_id alapján történő szűréshez
            $search = \Stripe\Customer::search([
                'query' => "metadata['domain_id']:'{$domainId}'",
                'limit' => $limit,
                'expand' => ['data.subscriptions']
            ]);

            return [
                'success' => true,
                'data' => $search->data,
                'has_more' => $search->has_more,
            ];
        } catch (Exception $e) {
            Log::error('Stripe ügyfelek lekérdezési hiba: ' . $e->getMessage(), [
                'domain_id' => $domainId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
