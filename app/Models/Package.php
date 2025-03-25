<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'domain_id',
        'name',
        'description',
        'billing_type',
        'monthly_price',
        'yearly_price',
        'unit_price',
        'stripe_product_id',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'stripe_unit_price_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'monthly_price' => 'float',
        'yearly_price' => 'float',
        'unit_price' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Get the domain that owns the package.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the subscribers for the package.
     */
    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }

    /**
     * Determine if this package is a monthly subscription.
     *
     * @return bool
     */
    public function isMonthly()
    {
        return $this->billing_type === 'monthly';
    }

    /**
     * Determine if this package is a yearly subscription.
     *
     * @return bool
     */
    public function isYearly()
    {
        return $this->billing_type === 'yearly';
    }

    /**
     * Determine if this package is unit-based.
     *
     * @return bool
     */
    public function isUnitBased()
    {
        return $this->billing_type === 'unit';
    }

    /**
     * Get formatted price for display.
     *
     * @return string
     */
    public function getFormattedPriceAttribute()
    {
        if ($this->isMonthly()) {
            return $this->domain->currency . ' ' . number_format($this->monthly_price, 2) . '/month';
        } elseif ($this->isYearly()) {
            return $this->domain->currency . ' ' . number_format($this->yearly_price, 2) . '/year';
        } elseif ($this->isUnitBased()) {
            return $this->domain->currency . ' ' . number_format($this->unit_price, 2) . '/unit';
        }

        return 'N/A';
    }

    /**
     * Scope a query to only include active packages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by billing type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('billing_type', $type);
    }

    /**
     * Get the features for the package.
     */
    public function features()
    {
        return $this->hasMany(PackageFeature::class)->orderBy('order');
    }

    /**
     * Get the features metadata as an array.
     */
    public function getFeaturesAttribute()
    {
        if ($this->features_metadata) {
            return json_decode($this->features_metadata, true);
        }

        return [];
    }
}
