## Publishing Configuration

```bash
php artisan vendor:publish --tag="suspend-config"
```

This creates `config/suspend.php`.

## Primary Key Type

Configure the primary key type for Suspend tables:

```php
'primary_key_type' => 'id', // 'id', 'uuid', or 'ulid'
```

| Type | Description |
|------|-------------|
| `id` | Auto-incrementing integer (default) |
| `uuid` | UUID v4 strings |
| `ulid` | ULID strings (sortable) |

**Note:** Set this before running migrations.

## Morph Types

Configure how polymorphic relationships store model types:

```php
// For context (suspended entities)
'context_morph_type' => 'string', // 'string', 'numeric', 'uuid', 'ulid'

// For actors (suspended_by, revoked_by)
'actor_morph_type' => 'string',
```

Using `numeric` reduces storage but requires a morph map:

```php
// In AppServiceProvider
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    1 => App\Models\User::class,
    2 => App\Models\Organization::class,
]);
```

## IP Resolvers

Choose how client IP addresses are extracted:

```php
use Cline\Suspend\Resolvers\Ip\StandardIpResolver;
use Cline\Suspend\Resolvers\Ip\CloudflareIpResolver;
use Cline\Suspend\Resolvers\Ip\AwsApiGatewayIpResolver;
use Cline\Suspend\Resolvers\Ip\FastlyIpResolver;
use Cline\Suspend\Resolvers\Ip\AkamaiIpResolver;
use Cline\Suspend\Resolvers\Ip\TrustedProxyIpResolver;

'ip_resolver' => StandardIpResolver::class,
```

| Resolver | Use When |
|----------|----------|
| `StandardIpResolver` | Direct connections, standard proxies |
| `CloudflareIpResolver` | Behind Cloudflare CDN |
| `AwsApiGatewayIpResolver` | Behind AWS API Gateway/ALB |
| `FastlyIpResolver` | Behind Fastly CDN |
| `AkamaiIpResolver` | Behind Akamai CDN |
| `TrustedProxyIpResolver` | Custom trusted proxy setup |

### CDN-Specific Headers

Each CDN resolver reads the appropriate header:

- **Cloudflare:** `CF-Connecting-IP`
- **AWS:** `X-Forwarded-For` (first IP)
- **Fastly:** `Fastly-Client-IP`
- **Akamai:** `True-Client-IP`

## Geo Resolvers

Configure IP geolocation for country-based suspensions:

### CDN-Based (Recommended)

Free, fast, no API limits - use headers from your CDN:

```php
use Cline\Suspend\Resolvers\Geo\Cdn\CloudflareGeoResolver;
use Cline\Suspend\Resolvers\Geo\Cdn\AwsCloudFrontGeoResolver;
use Cline\Suspend\Resolvers\Geo\Cdn\FastlyGeoResolver;
use Cline\Suspend\Resolvers\Geo\Cdn\AkamaiGeoResolver;
use Cline\Suspend\Resolvers\Geo\Cdn\VercelGeoResolver;

'geo_resolver' => CloudflareGeoResolver::class,
```

### Local Database

Use MaxMind GeoLite2 or GeoIP2 database:

```php
use Cline\Suspend\Resolvers\Geo\Local\MaxMindLocalGeoResolver;

'geo_resolver' => MaxMindLocalGeoResolver::class,

'maxmind_database' => storage_path('geoip/GeoLite2-City.mmdb'),
```

Download the database from [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data).

**Requires:** `composer require geoip2/geoip2`

### API-Based

Use third-party geolocation APIs:

```php
use Cline\Suspend\Resolvers\Geo\Api\IpApiGeoResolver;
use Cline\Suspend\Resolvers\Geo\Api\IpStackGeoResolver;
use Cline\Suspend\Resolvers\Geo\Api\IpInfoGeoResolver;
use Cline\Suspend\Resolvers\Geo\Api\IpDataGeoResolver;
use Cline\Suspend\Resolvers\Geo\Api\Ip2LocationApiGeoResolver;
use Cline\Suspend\Resolvers\Geo\Api\DbIpApiGeoResolver;
use Cline\Suspend\Resolvers\Geo\Api\IpGeolocationIoGeoResolver;

'geo_resolver' => IpApiGeoResolver::class,
```

### Chain Resolver

Use multiple resolvers with fallback:

```php
use Cline\Suspend\Resolvers\ChainResolver;

'geo_resolver' => [
    CloudflareGeoResolver::class,     // Try CDN first (free)
    MaxMindLocalGeoResolver::class,   // Fallback to local DB
    IpApiGeoResolver::class,          // Fallback to API
],
```

### Null Resolver

Disable geolocation entirely:

```php
use Cline\Suspend\Resolvers\Geo\NullGeoResolver;

'geo_resolver' => NullGeoResolver::class, // Default
```

## API Keys

Configure API keys for geo services:

```php
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
```

Add to your `.env`:

```env
SUSPEND_IP_API_KEY=your-key-here
SUSPEND_IPSTACK_KEY=your-key-here
```

## Geo Cache TTL

Cache geolocation results to reduce API calls:

```php
'geo_cache_ttl' => 3600, // 1 hour, set to 0 to disable
```

## Custom Table Names

Override default table names:

```php
'tables' => [
    'suspensions' => 'my_suspensions',
],
```

**Note:** Set this before running migrations.

## Middleware Configuration

Configure the built-in suspension middleware:

```php
'middleware' => [
    // Automatically check IP against suspensions
    'check_ip' => true,

    // Automatically check country against suspensions
    'check_country' => false,

    // HTTP response code when suspended
    'response_code' => 403,

    // Response message when suspended
    'response_message' => 'Access denied. Your access has been suspended.',

    // Routes to exclude from checks
    'except' => [
        'login',
        'logout',
        'contact',
        'suspended',
    ],
],
```

## Environment Variables

Recommended environment variables:

```env
# Primary key type (set before migrations)
SUSPEND_PRIMARY_KEY_TYPE=id

# IP Resolution
SUSPEND_IP_RESOLVER=standard

# Geo Resolution
SUSPEND_GEO_RESOLVER=cloudflare

# MaxMind Database Path
SUSPEND_MAXMIND_DATABASE=/path/to/GeoLite2-City.mmdb

# API Keys (only needed for API-based resolvers)
SUSPEND_IP_API_KEY=
SUSPEND_IPSTACK_KEY=
SUSPEND_IPINFO_TOKEN=

# Cache
SUSPEND_GEO_CACHE_TTL=3600
```

Then in config:

```php
'ip_resolver' => match(env('SUSPEND_IP_RESOLVER', 'standard')) {
    'cloudflare' => CloudflareIpResolver::class,
    'aws' => AwsApiGatewayIpResolver::class,
    'fastly' => FastlyIpResolver::class,
    default => StandardIpResolver::class,
},

'geo_resolver' => match(env('SUSPEND_GEO_RESOLVER', 'null')) {
    'cloudflare' => CloudflareGeoResolver::class,
    'maxmind' => MaxMindLocalGeoResolver::class,
    'ip_api' => IpApiGeoResolver::class,
    default => NullGeoResolver::class,
},
```

## Example Configurations

### Simple Setup (No Geo)

```php
return [
    'primary_key_type' => 'id',
    'ip_resolver' => StandardIpResolver::class,
    'geo_resolver' => NullGeoResolver::class,
];
```

### Cloudflare Setup

```php
return [
    'primary_key_type' => 'ulid',
    'ip_resolver' => CloudflareIpResolver::class,
    'geo_resolver' => CloudflareGeoResolver::class,
];
```

### Enterprise Setup with Fallbacks

```php
return [
    'primary_key_type' => 'uuid',
    'context_morph_type' => 'uuid',
    'actor_morph_type' => 'uuid',

    'ip_resolver' => CloudflareIpResolver::class,

    'geo_resolver' => [
        CloudflareGeoResolver::class,
        MaxMindLocalGeoResolver::class,
    ],

    'maxmind_database' => storage_path('geoip/GeoIP2-City.mmdb'),

    'geo_cache_ttl' => 86400, // 24 hours

    'middleware' => [
        'check_ip' => true,
        'check_country' => true,
        'response_code' => 403,
        'except' => ['login', 'logout', 'suspended', 'api/health'],
    ],
];
```
