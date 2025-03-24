<?php


// app/Services/DomainConnectionService.php
namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DomainConnectionService
{
    public function setConnection(Domain $domain)
    {
        // Create a new database connection for the selected domain
        Config::set('database.connections.domain_connection', [
            'driver' => 'mysql',
            'host' => $domain->database_host,
            'database' => $domain->database_name,
            'username' => $domain->database_username,
            'password' => $domain->database_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        // Set as default connection for domain-specific operations
        DB::purge('domain_connection');

        // Store domain info in session for later use
        session(['current_domain' => $domain]);

        return true;
    }

    public function getDomainConnection()
    {
        return DB::connection('domain_connection');
    }
}
