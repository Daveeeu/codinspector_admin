<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'package_id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'package_id';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'query_limit',
        'cost',
        'cost_per_query',
        'cost_yearly',
        'stripe_price_id',
        'stripe_price_yearly_id',
        'permissions',
        'premium'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'query_limit' => 'integer',
        'cost' => 'decimal:2',
        'cost_per_query' => 'decimal:2',
        'cost_yearly' => 'decimal:2',
        'premium' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all package features.
     */
    public function features()
    {
        return $this->hasMany(PackageFeature::class, 'package_id', 'package_id');
    }

    /**
     * Get package subscribers.
     */
    public function subscribers()
    {
        // If you have a subscriptions table
        return $this->hasMany(Subscription::class, 'package_id', 'package_id');
    }

    /**
     * Get features from permissions JSON.
     */
    public function getExtractedFeaturesAttribute()
    {
        $permissions = json_decode($this->permissions, true) ?: [];
        return $permissions['features'] ?? [];
    }

    /**
     * Get the Stripe product ID from permissions JSON.
     */
    public function getStripeProductIdAttribute()
    {
        $permissions = json_decode($this->permissions, true) ?: [];
        return $permissions['stripe_product_id'] ?? null;
    }

    /**
     * Get the Stripe unit price ID from permissions JSON.
     */
    public function getStripeUnitPriceIdAttribute()
    {
        $permissions = json_decode($this->permissions, true) ?: [];
        return $permissions['stripe_unit_price_id'] ?? null;
    }
}
