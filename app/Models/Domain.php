<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'database_name',
        'database_host',
        'database_username',
        'database_password',
        'is_active',
        'currency',
        'country_code',
        'language_code',
    ];

    protected $hidden = [
        'database_password',
    ];

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'current_domain_id');
    }
}
