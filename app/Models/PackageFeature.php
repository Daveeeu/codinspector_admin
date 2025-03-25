<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageFeature extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'package_id',
        'name',
        'is_included',
        'order'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_included' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the package that owns the feature.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
