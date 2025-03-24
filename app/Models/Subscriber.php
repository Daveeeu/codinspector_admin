<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'domain_id',
        'package_id',
        'company_name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'tax_id',
        'billing_cycle',
        'units',
        'amount',
        'subscription_start',
        'subscription_end',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subscription_start' => 'datetime',
        'subscription_end' => 'datetime',
        'amount' => 'float',
        'units' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'formatted_amount',
        'status_label',
    ];

    /**
     * Get the domain that owns the subscriber.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the package that the subscriber is subscribed to.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the formatted amount.
     *
     * @return string
     */
    public function getFormattedAmountAttribute()
    {
        if (!$this->domain) {
            return number_format($this->amount, 2);
        }

        return $this->domain->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Get the status label with appropriate styling.
     *
     * @return array
     */
    public function getStatusLabelAttribute()
    {
        $label = ucfirst($this->status);
        $class = 'secondary';

        switch ($this->status) {
            case 'active':
                $class = 'success';
                break;
            case 'pending':
                $class = 'warning';
                break;
            case 'cancelled':
                $class = 'danger';
                break;
            case 'inactive':
                $class = 'secondary';
                break;
        }

        return [
            'label' => $label,
            'class' => $class
        ];
    }

    /**
     * Check if the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if the subscription is cancelled.
     *
     * @return bool
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the subscription is pending.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the subscription uses monthly billing.
     *
     * @return bool
     */
    public function isMonthlyBilling()
    {
        return $this->billing_cycle === 'monthly';
    }

    /**
     * Check if the subscription uses yearly billing.
     *
     * @return bool
     */
    public function isYearlyBilling()
    {
        return $this->billing_cycle === 'yearly';
    }

    /**
     * Check if the subscription uses unit-based billing.
     *
     * @return bool
     */
    public function isUnitBilling()
    {
        return $this->billing_cycle === 'unit';
    }

    /**
     * Get the remaining days in the current billing period.
     *
     * @return int|null
     */
    public function getRemainingDays()
    {
        if (!$this->subscription_end) {
            return null;
        }

        return now()->diffInDays($this->subscription_end, false);
    }

    /**
     * Calculate the next billing amount.
     *
     * @return float|null
     */
    public function getNextBillingAmount()
    {
        if ($this->isUnitBilling() || $this->isCancelled()) {
            return null;
        }

        return $this->amount;
    }

    /**
     * Get subscription period in human-readable format.
     *
     * @return string
     */
    public function getSubscriptionPeriod()
    {
        if (!$this->subscription_start) {
            return 'N/A';
        }

        $start = $this->subscription_start->format('Y-m-d');

        if (!$this->subscription_end) {
            return "{$start} - ongoing";
        }

        $end = $this->subscription_end->format('Y-m-d');

        return "{$start} - {$end}";
    }

    /**
     * Scope a query to only include active subscribers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include subscribers for a specific domain.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $domainId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDomain($query, $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    /**
     * Scope a query to only include subscribers for a specific package.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $packageId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPackage($query, $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    /**
     * Scope a query to only include subscribers with a specific billing cycle.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $billingCycle
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithBillingCycle($query, $billingCycle)
    {
        return $query->where('billing_cycle', $billingCycle);
    }

    /**
     * Scope a query to include only subscribers that are due for renewal.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $daysThreshold Number of days until renewal to include
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDueForRenewal($query, $daysThreshold = 7)
    {
        return $query->where('status', 'active')
            ->whereNotNull('subscription_end')
            ->whereDate('subscription_end', '<=', now()->addDays($daysThreshold))
            ->whereDate('subscription_end', '>=', now());
    }
}
