<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\NullGeoResolver;
use Cline\Suspend\Resolvers\Ip\StandardIpResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The type of primary key to use for Suspend tables. Supported values:
    | 'id' (auto-increment), 'uuid', 'ulid'
    |
    */

    'primary_key_type' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Context Morph Type
    |--------------------------------------------------------------------------
    |
    | The morph type for context (suspendable entity) relationships.
    | Supported: 'string', 'numeric', 'uuid', 'ulid'
    |
    */

    'context_morph_type' => 'string',

    /*
    |--------------------------------------------------------------------------
    | Actor Morph Type
    |--------------------------------------------------------------------------
    |
    | The morph type for actor (suspended_by, revoked_by) relationships.
    | Supported: 'string', 'numeric', 'uuid', 'ulid'
    |
    */

    'actor_morph_type' => 'string',

    /*
    |--------------------------------------------------------------------------
    | IP Resolver
    |--------------------------------------------------------------------------
    |
    | The IP resolver class to use for extracting client IP addresses.
    | Choose based on your infrastructure:
    |
    | - StandardIpResolver: Uses Laravel's request->ip()
    | - CloudflareIpResolver: For Cloudflare-proxied requests
    | - AwsApiGatewayIpResolver: For AWS API Gateway/ALB
    | - FastlyIpResolver: For Fastly CDN
    | - AkamaiIpResolver: For Akamai CDN
    |
    */

    'ip_resolver' => StandardIpResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Geo Resolver
    |--------------------------------------------------------------------------
    |
    | The geo resolver class or configuration for IP geolocation.
    | Use a single class or an array for chain resolver with fallbacks.
    |
    | Options:
    | - Single class: CloudflareGeoResolver::class
    | - Chain: ['cloudflare', 'maxmind_local', 'ip_api']
    |
    */

    'geo_resolver' => NullGeoResolver::class,

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for various geo services. Only needed if using API-based
    | geo resolvers.
    |
    */

    'api_keys' => [
        'ip_api' => env('SUSPEND_IP_API_KEY'),
        'ipstack' => env('SUSPEND_IPSTACK_KEY'),
        'ipinfo' => env('SUSPEND_IPINFO_TOKEN'),
        'ipgeolocation_io' => env('SUSPEND_IPGEOLOCATION_IO_KEY'),
        'ip2location' => env('SUSPEND_IP2LOCATION_KEY'),
        'abstractapi' => env('SUSPEND_ABSTRACTAPI_KEY'),
        'dbip' => env('SUSPEND_DBIP_KEY'),
        'ipdata' => env('SUSPEND_IPDATA_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MaxMind Local Database
    |--------------------------------------------------------------------------
    |
    | Path to the MaxMind GeoIP2/GeoLite2 database file if using
    | MaxMindLocalGeoResolver.
    |
    */

    'maxmind_database' => storage_path('geoip/GeoLite2-City.mmdb'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Time-to-live in seconds for caching geo API responses.
    | Set to 0 to disable caching.
    |
    */

    'geo_cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Custom table names for Suspend. Leave empty to use defaults.
    |
    */

    'tables' => [
        // 'suspensions' => 'custom_suspensions_table',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Configuration for the suspension check middleware.
    |
    */

    'middleware' => [
        // Automatically check IP against suspensions
        'check_ip' => true,

        // Automatically check country against suspensions
        'check_country' => false,

        // Response code when suspended
        'response_code' => 403,

        // Response message when suspended
        'response_message' => 'Access denied. Your access has been suspended.',

        // Routes to exclude from suspension checks
        'except' => [
            // 'login',
            // 'logout',
        ],
    ],
];
