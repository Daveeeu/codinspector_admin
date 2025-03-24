<?php
// config/domains.php
return [
    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the multiple domain system.
    |
    */

    // Default connection name for domain-specific operations
    'connection' => 'domain_connection',

    // Session key to store current domain
    'session_key' => 'current_domain',
];
