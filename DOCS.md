## Table of Contents

1. [Overview](#doc-docs-readme) (`docs/README.md`)
2. [Configuration](#doc-docs-configuration) (`docs/configuration.md`)
3. [Context Matching](#doc-docs-context-matching) (`docs/context-matching.md`)
4. [Entity Suspensions](#doc-docs-entity-suspensions) (`docs/entity-suspensions.md`)
5. [Events](#doc-docs-events) (`docs/events.md`)
6. [Middleware](#doc-docs-middleware) (`docs/middleware.md`)
7. [Querying](#doc-docs-querying) (`docs/querying.md`)
8. [Strategies](#doc-docs-strategies) (`docs/strategies.md`)
<a id="doc-docs-readme"></a>

## Requirements

Suspend requires PHP 8.4+ and Laravel 11+.

## Installation

Install Suspend with composer:

```bash
composer require cline/suspend
```

## Run Migrations

First publish the migrations:

```bash
php artisan vendor:publish --tag="suspend-migrations"
```

Then run the migrations:

```bash
php artisan migrate
```

## Add the Trait (Optional)

For eager loading suspensions, add the trait to your models:

```php
use Cline\Suspend\Concerns\HasSuspensions;

class User extends Model
{
    use HasSuspensions;
}
```

This provides the `suspensions` relationship for eager loading:

```php
$users = User::with('suspensions')->get();
```

## Using the Facade

Import the facade in your files:

```php
use Cline\Suspend\Facades\Suspend;
```

## Quick Start

### Entity-Based Suspensions

Suspend specific users, accounts, or any Eloquent model:

```php
// Suspend a user
Suspend::for($user)->suspend('Terms of Service violation');

// Suspend with expiration
Suspend::for($user)->suspend('Temporary ban', now()->addDays(7));

// Check if suspended
if (Suspend::for($user)->isSuspended()) {
    // Handle suspended user
}

// Lift all suspensions
Suspend::for($user)->lift();
```

### Context-Based Suspensions

Suspend by email, IP address, phone number, or any pattern:

```php
// Block an email domain
Suspend::match('email', '*@spam.com')->suspend('Spam domain');

// Block an IP range
Suspend::match('ip', '192.168.1.0/24')->suspend('Network abuse');

// Block a phone number pattern
Suspend::match('phone', '+1555*')->suspend('Fraud prevention');
```

### Checking Multiple Contexts

Check if any context matches active suspensions:

```php
$isSuspended = Suspend::check()
    ->email($request->input('email'))
    ->ip($request->ip())
    ->phone($request->input('phone'))
    ->matches();

if ($isSuspended) {
    abort(403, 'Access denied');
}
```

## Middleware

Suspend registers a middleware alias automatically. Use it to protect routes:

```php
// In routes/web.php
Route::middleware(['auth', 'suspended'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

The middleware checks if the authenticated user is suspended and returns a 403 response if so.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="suspend-config"
```

This creates `config/suspend.php` where you can customize:

- Primary key type (id, uuid, ulid)
- IP resolution strategy
- Geo-location resolver
- Custom table names
- Middleware behavior

## Next Steps

- [Entity Suspensions](#doc-docs-entity-suspensions) - Deep dive into model-based suspensions
- [Context Matching](#doc-docs-context-matching) - Pattern-based suspension with matchers
- [Strategies](#doc-docs-strategies) - Conditional suspension strategies
- [Middleware](#doc-docs-middleware) - Protecting routes and handling suspended users
- [Events](#doc-docs-events) - Reacting to suspension lifecycle events
- [Querying](#doc-docs-querying) - Finding and filtering suspensions
- [Configuration](#doc-docs-configuration) - Full configuration reference

<a id="doc-docs-configuration"></a>

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

<a id="doc-docs-context-matching"></a>

## Built-in Matchers

Suspend includes these matchers out of the box:

| Matcher | Type | Description |
|---------|------|-------------|
| `EmailMatcher` | `email` | Email addresses with wildcard support |
| `IpMatcher` | `ip` | IPv4/IPv6 addresses with CIDR support |
| `PhoneMatcher` | `phone` | Phone numbers (normalized) |
| `DomainMatcher` | `domain` | Domain names with subdomain matching |
| `CountryMatcher` | `country` | ISO country codes |
| `FingerprintMatcher` | `fingerprint` | Device/browser fingerprints |
| `RegexMatcher` | `regex` | Regular expression patterns |
| `GlobMatcher` | `glob` | Shell-style glob patterns |
| `ExactMatcher` | `exact` | Exact string matching |

## Creating Context Suspensions

### Email Matching

```php
use Cline\Suspend\Facades\Suspend;

// Block specific email
Suspend::match('email', 'spammer@example.com')->suspend('Known spammer');

// Block entire domain (wildcard)
Suspend::match('email', '*@spam-domain.com')->suspend('Spam domain');

// Block pattern
Suspend::match('email', 'bot*@*')->suspend('Bot pattern');
```

### IP Address Matching

```php
// Block specific IP
Suspend::match('ip', '1.2.3.4')->suspend('Malicious IP');

// Block CIDR range
Suspend::match('ip', '192.168.1.0/24')->suspend('Internal network');

// Block IPv6
Suspend::match('ip', '2001:db8::/32')->suspend('IPv6 range');
```

### Phone Number Matching

```php
// Block specific number (formatting is normalized)
Suspend::match('phone', '+1-555-123-4567')->suspend();

// Numbers are normalized, so these are equivalent:
// +15551234567, (555) 123-4567, 555.123.4567
```

### Domain Matching

```php
// Block domain and all subdomains
Suspend::match('domain', 'malware.com')->suspend('Malware source');

// Matches: malware.com, www.malware.com, sub.malware.com
```

### Country Matching

```php
// Block by ISO country code
Suspend::match('country', 'XX')->suspend('Restricted country');
```

### Glob Patterns

Simple wildcard matching without regex complexity:

```php
// * matches any characters
Suspend::match('glob', 'spam@*')->suspend();

// ? matches single character
Suspend::match('glob', 'user?@example.com')->suspend();

// Character classes
Suspend::match('glob', 'user[123]@*')->suspend();

// Negated classes
Suspend::match('glob', 'test[!0-9]@*')->suspend();
```

### Regular Expressions

For complex patterns:

```php
// Regex pattern (include delimiters)
Suspend::match('regex', '/^bot\d+@/i')->suspend('Bot pattern');

// Match disposable email providers
Suspend::match('regex', '/@(tempmail|throwaway)\./i')
    ->suspend('Disposable email');
```

## Checking Context Matches

### Single Context Check

```php
$suspended = Suspend::check()
    ->email('user@example.com')
    ->matches();
```

### Multiple Context Check

Check multiple contexts at once - returns true if ANY match:

```php
$suspended = Suspend::check()
    ->email($request->input('email'))
    ->ip($request->ip())
    ->phone($request->input('phone'))
    ->domain($request->getHost())
    ->fingerprint($request->header('X-Device-Fingerprint'))
    ->matches();
```

### Get Matching Suspensions

```php
$suspensions = Suspend::check()
    ->email($email)
    ->ip($ip)
    ->getSuspensions();

// Returns Collection of matching Suspension models
foreach ($suspensions as $suspension) {
    echo "Blocked by: {$suspension->reason}";
}
```

### Check with Request

Automatically extract context from the current request:

```php
$suspended = Suspend::check()
    ->fromRequest($request)
    ->matches();
```

## Pattern Validation

Matchers validate patterns before creating suspensions:

```php
// This will fail - invalid email pattern
Suspend::match('email', 'not-an-email')->suspend();

// This will fail - invalid CIDR
Suspend::match('ip', '192.168.1.0/33')->suspend();

// This will fail - invalid regex
Suspend::match('regex', '/unclosed[/')->suspend();
```

## Custom Matchers

Create your own matcher for custom context types:

```php
use Cline\Suspend\Matchers\Contracts\Matcher;

class UsernamePatternMatcher implements Matcher
{
    public function type(): string
    {
        return 'username';
    }

    public function normalize(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public function matches(string $pattern, mixed $value): bool
    {
        return fnmatch($pattern, $this->normalize($value));
    }

    public function validate(mixed $value): bool
    {
        $normalized = $this->normalize($value);
        return $normalized !== '' && strlen($normalized) <= 255;
    }

    public function extract(mixed $value): ?string
    {
        return null; // No extraction for usernames
    }
}
```

Register your custom matcher:

```php
// In a service provider
use Cline\Suspend\Facades\Suspend;

public function boot()
{
    Suspend::registerMatcher(new UsernamePatternMatcher());
}
```

Use it:

```php
Suspend::match('username', 'bot_*')->suspend('Bot username pattern');

Suspend::check()->add('username', $username)->matches();
```

## Real-World Examples

### Block Disposable Email Providers

```php
$disposableProviders = [
    '*@tempmail.com',
    '*@throwaway.io',
    '*@10minutemail.com',
    '*@guerrillamail.com',
];

foreach ($disposableProviders as $pattern) {
    Suspend::match('email', $pattern)->suspend('Disposable email provider');
}
```

### Block Known Bad IP Ranges

```php
$badRanges = [
    '192.0.2.0/24',    // TEST-NET-1
    '198.51.100.0/24', // TEST-NET-2
    '203.0.113.0/24',  // TEST-NET-3
];

foreach ($badRanges as $range) {
    Suspend::match('ip', $range)->suspend('Reserved/test IP range');
}
```

### Geographic Restrictions

```php
$restrictedCountries = ['XX', 'YY', 'ZZ'];

foreach ($restrictedCountries as $country) {
    Suspend::match('country', $country)
        ->suspend('Service not available in this region');
}
```

### Fraud Prevention Patterns

```php
// Block suspicious email patterns
Suspend::match('regex', '/^[a-z]{20,}@/i')
    ->suspend('Suspicious random email');

// Block VoIP phone prefixes
Suspend::match('phone', '+1800*')->suspend('Toll-free number');
Suspend::match('phone', '+1888*')->suspend('Toll-free number');
```

<a id="doc-docs-entity-suspensions"></a>

## Basic Usage

### Suspending an Entity

```php
use Cline\Suspend\Facades\Suspend;

// Simple suspension
Suspend::for($user)->suspend();

// With a reason
Suspend::for($user)->suspend('Violated community guidelines');

// With expiration
Suspend::for($user)->suspend('Temporary ban', now()->addDays(7));

// With metadata
Suspend::for($user)->suspend('Spam', null, [
    'reported_by' => $admin->id,
    'ticket_id' => 'TKT-12345',
]);
```

### Checking Suspension Status

```php
// Check if currently suspended
if (Suspend::for($user)->isSuspended()) {
    return redirect('/suspended');
}

// Inverse check
if (Suspend::for($user)->isNotSuspended()) {
    // User can proceed
}
```

### Lifting Suspensions

```php
// Lift all active suspensions
$count = Suspend::for($user)->lift();

// With a reason
$count = Suspend::for($user)->lift('Appeal approved');
```

## The HasSuspensions Trait

The trait provides only the `suspensions` relationship for eager loading:

```php
use Cline\Suspend\Concerns\HasSuspensions;

class User extends Model
{
    use HasSuspensions;
}
```

Usage:

```php
// Eager load suspensions
$users = User::with('suspensions')->get();

// Access relationship
$allSuspensions = $user->suspensions;

// Query through relationship
$activeSuspensions = $user->suspensions()->active()->get();
```

**Note:** All suspension operations should use `Suspend::for($model)`, not methods on the model itself. This keeps business logic in the service layer.

## Time-Based Suspensions

### Suspend for a Duration

```php
// Using DateInterval
Suspend::for($user)->suspendFor(
    new DateInterval('P30D'), // 30 days
    'Monthly suspension'
);

// The suspension automatically expires
```

### Schedule Future Suspensions

```php
// Suspension starts in the future
Suspend::for($user)->suspendAt(
    startsAt: now()->addHours(2),
    reason: 'Scheduled maintenance ban',
    expiresAt: now()->addHours(4),
);
```

## Using Strategies

Apply conditional logic to suspensions:

```php
// Time-window strategy - only active during specific hours
Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [1, 2, 3, 4, 5], // Mon-Fri
        'timezone' => 'America/New_York',
    ])
    ->suspend('Business hours restriction');

// IP-based strategy
Suspend::for($user)
    ->using('ip_address', [
        'allowed_ips' => ['192.168.1.0/24'],
    ])
    ->suspend('Office-only access');

// Country-based strategy
Suspend::for($user)
    ->using('country', [
        'blocked_countries' => ['XX', 'YY'],
    ])
    ->suspend('Geographic restriction');
```

## Bulk Operations

### Suspend Multiple Entities

```php
$spammers = User::where('spam_score', '>', 90)->get();

$suspensions = Suspend::suspendMany(
    $spammers,
    'Automated spam detection',
    now()->addDays(30),
);

// Returns Collection of Suspension models
```

### Revoke Multiple Suspensions

```php
$users = User::whereIn('id', $userIds)->get();

$count = Suspend::revokeMany($users, 'Bulk pardon');
```

## Suspension History

### Get All Suspensions

```php
$allSuspensions = Suspend::for($user)->all();

// Or via the relationship (if using trait)
$allSuspensions = $user->suspensions;
```

### Get Active Suspensions Only

```php
$active = Suspend::for($user)->activeSuspensions();
```

### Detailed History

```php
$history = Suspend::for($user)->history();

foreach ($history as $suspension) {
    echo $suspension->reason;
    echo $suspension->suspended_at;
    echo $suspension->expires_at;
    echo $suspension->revoked_at;
    echo $suspension->status(); // Active, Expired, Revoked, Pending
}
```

## Events

Suspend dispatches events you can listen for:

```php
// In EventServiceProvider
protected $listen = [
    \Cline\Suspend\Events\SuspensionCreated::class => [
        SendSuspensionNotification::class,
    ],
    \Cline\Suspend\Events\SuspensionRevoked::class => [
        LogSuspensionRevocation::class,
    ],
    \Cline\Suspend\Events\SuspensionLifted::class => [
        NotifyUserReinstated::class,
    ],
];
```

See [Events](#doc-docs-events) for detailed event handling.

## Querying Suspensions

```php
use Cline\Suspend\Database\Models\Suspension;

// All active suspensions
$active = Suspension::active()->get();

// Suspensions for a specific model type
$userSuspensions = Suspension::query()
    ->where('context_type', User::class)
    ->active()
    ->get();

// Expired suspensions
$expired = Suspension::expired()->get();

// Suspensions expiring soon
$expiringSoon = Suspension::query()
    ->active()
    ->where('expires_at', '<=', now()->addDay())
    ->get();
```

See [Querying](#doc-docs-querying) for more query examples.

<a id="doc-docs-events"></a>

## Available Events

| Event | Dispatched When |
|-------|-----------------|
| `SuspensionCreated` | A new suspension is created |
| `SuspensionRevoked` | A suspension is manually revoked |
| `SuspensionLifted` | All suspensions are lifted from an entity |

## Listening to Events

### Register Listeners

```php
// app/Providers/EventServiceProvider.php
use Cline\Suspend\Events\SuspensionCreated;
use Cline\Suspend\Events\SuspensionRevoked;
use Cline\Suspend\Events\SuspensionLifted;

protected $listen = [
    SuspensionCreated::class => [
        \App\Listeners\NotifyUserSuspended::class,
        \App\Listeners\LogSuspension::class,
    ],
    SuspensionRevoked::class => [
        \App\Listeners\LogSuspensionRevocation::class,
    ],
    SuspensionLifted::class => [
        \App\Listeners\NotifyUserReinstated::class,
    ],
];
```

### SuspensionCreated

Dispatched when a new suspension is created via any method.

```php
namespace App\Listeners;

use Cline\Suspend\Events\SuspensionCreated;

class NotifyUserSuspended
{
    public function handle(SuspensionCreated $event): void
    {
        $suspension = $event->suspension;

        // For entity-based suspensions
        if ($suspension->isEntityBased()) {
            $user = $suspension->context;
            $user->notify(new AccountSuspendedNotification(
                reason: $suspension->reason,
                expiresAt: $suspension->expires_at,
            ));
        }

        // Log the suspension
        Log::info('Suspension created', [
            'suspension_id' => $suspension->id,
            'context_type' => $suspension->context_type,
            'context_id' => $suspension->context_id,
            'match_type' => $suspension->match_type,
            'match_value' => $suspension->match_value,
            'reason' => $suspension->reason,
            'expires_at' => $suspension->expires_at,
        ]);
    }
}
```

### SuspensionRevoked

Dispatched when a suspension is manually revoked (not expired).

```php
namespace App\Listeners;

use Cline\Suspend\Events\SuspensionRevoked;

class LogSuspensionRevocation
{
    public function handle(SuspensionRevoked $event): void
    {
        $suspension = $event->suspension;
        $reason = $event->reason;

        Log::info('Suspension revoked', [
            'suspension_id' => $suspension->id,
            'revoked_by' => $suspension->revoked_by_id,
            'revocation_reason' => $reason,
            'original_reason' => $suspension->reason,
        ]);

        // Notify the user their suspension was lifted
        if ($suspension->isEntityBased() && $suspension->context) {
            $suspension->context->notify(new SuspensionRevokedNotification());
        }
    }
}
```

### SuspensionLifted

Dispatched when `lift()` is called and at least one suspension was lifted.

```php
namespace App\Listeners;

use Cline\Suspend\Events\SuspensionLifted;

class NotifyUserReinstated
{
    public function handle(SuspensionLifted $event): void
    {
        $context = $event->context;
        $count = $event->count;

        Log::info('Suspensions lifted', [
            'context_type' => get_class($context),
            'context_id' => $context->getKey(),
            'count' => $count,
        ]);

        if ($context instanceof User) {
            $context->notify(new AccountReinstatedNotification());
        }
    }
}
```

## Event Payloads

### SuspensionCreated

```php
class SuspensionCreated
{
    public function __construct(
        public readonly Suspension $suspension,
    ) {}
}
```

### SuspensionRevoked

```php
class SuspensionRevoked
{
    public function __construct(
        public readonly Suspension $suspension,
        public readonly ?string $reason = null,
    ) {}
}
```

### SuspensionLifted

```php
class SuspensionLifted
{
    public function __construct(
        public readonly Model $context,
        public readonly ?Model $liftedBy = null,
        public readonly ?string $reason = null,
        public readonly int $count = 0,
    ) {}
}
```

## Queued Listeners

For time-consuming operations, use queued listeners:

```php
namespace App\Listeners;

use Cline\Suspend\Events\SuspensionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSuspensionEmail implements ShouldQueue
{
    public $queue = 'notifications';

    public function handle(SuspensionCreated $event): void
    {
        $suspension = $event->suspension;

        if ($suspension->context instanceof User) {
            Mail::to($suspension->context->email)
                ->send(new SuspensionMail($suspension));
        }
    }
}
```

## Event Subscribers

Group related event handling in a subscriber:

```php
namespace App\Listeners;

use Cline\Suspend\Events\SuspensionCreated;
use Cline\Suspend\Events\SuspensionRevoked;
use Cline\Suspend\Events\SuspensionLifted;
use Illuminate\Events\Dispatcher;

class SuspensionEventSubscriber
{
    public function handleCreated(SuspensionCreated $event): void
    {
        // Log creation
    }

    public function handleRevoked(SuspensionRevoked $event): void
    {
        // Log revocation
    }

    public function handleLifted(SuspensionLifted $event): void
    {
        // Log lifting
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            SuspensionCreated::class => 'handleCreated',
            SuspensionRevoked::class => 'handleRevoked',
            SuspensionLifted::class => 'handleLifted',
        ];
    }
}
```

Register the subscriber:

```php
// app/Providers/EventServiceProvider.php
protected $subscribe = [
    \App\Listeners\SuspensionEventSubscriber::class,
];
```

## Common Use Cases

### Audit Logging

```php
class AuditSuspensionChanges
{
    public function handle(SuspensionCreated|SuspensionRevoked $event): void
    {
        $suspension = $event->suspension;

        AuditLog::create([
            'action' => $event instanceof SuspensionCreated ? 'suspended' : 'revoked',
            'subject_type' => $suspension->context_type ?? 'match',
            'subject_id' => $suspension->context_id ?? $suspension->match_value,
            'actor_id' => auth()->id(),
            'metadata' => [
                'suspension_id' => $suspension->id,
                'reason' => $suspension->reason,
            ],
        ]);
    }
}
```

### Slack Notifications

```php
class NotifySlackOnSuspension
{
    public function handle(SuspensionCreated $event): void
    {
        $suspension = $event->suspension;

        if ($suspension->isEntityBased()) {
            Notification::route('slack', config('services.slack.moderation'))
                ->notify(new SlackSuspensionNotification($suspension));
        }
    }
}
```

### Analytics Tracking

```php
class TrackSuspensionMetrics
{
    public function handle(SuspensionCreated $event): void
    {
        $suspension = $event->suspension;

        Metrics::increment('suspensions.created', tags: [
            'type' => $suspension->isEntityBased() ? 'entity' : 'context',
            'match_type' => $suspension->match_type ?? 'none',
            'has_expiration' => $suspension->expires_at ? 'yes' : 'no',
        ]);
    }
}
```

### Cascading Actions

```php
class HandleUserSuspension
{
    public function handle(SuspensionCreated $event): void
    {
        $suspension = $event->suspension;

        if (!$suspension->isEntityBased()) {
            return;
        }

        $user = $suspension->context;

        if (!$user instanceof User) {
            return;
        }

        // Revoke API tokens
        $user->tokens()->delete();

        // Cancel active sessions
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        // Pause subscriptions
        if ($user->subscribed()) {
            $user->subscription()->pause();
        }
    }
}
```

<a id="doc-docs-middleware"></a>

## Available Middleware

Suspend provides several middleware options:

| Middleware | Alias | Description |
|------------|-------|-------------|
| `CheckSuspendedMiddleware` | `suspended` | Check if authenticated user is suspended |
| `CheckIpMiddleware` | `suspended.ip` | Check if request IP is suspended |
| `CheckContextMiddleware` | `suspended.context` | Check multiple contexts at once |

## Basic Usage

### Protecting Routes

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

// Check authenticated user
Route::middleware(['auth', 'suspended'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/profile', ProfileController::class);
});

// Check IP address
Route::middleware(['suspended.ip'])->group(function () {
    Route::post('/api/signup', SignupController::class);
    Route::post('/api/contact', ContactController::class);
});
```

### Controller-Level Middleware

```php
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('suspended');
    }
}
```

## Middleware Configuration

Configure middleware behavior in `config/suspend.php`:

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

## Custom Responses

### JSON API Response

For API routes, return JSON instead of redirecting:

```php
// Create a custom middleware
class ApiSuspendedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && Suspend::for(auth()->user())->isSuspended()) {
            return response()->json([
                'error' => 'suspended',
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        return $next($request);
    }
}
```

### Redirect to Suspension Page

```php
class SuspendedRedirectMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $suspension = Suspend::for(auth()->user())->getActiveSuspension();

            if ($suspension) {
                return redirect()->route('suspended', [
                    'reason' => $suspension->reason,
                    'expires' => $suspension->expires_at?->diffForHumans(),
                ]);
            }
        }

        return $next($request);
    }
}
```

### Display Suspension Details

```php
class SuspensionDetailsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $suspensions = Suspend::for(auth()->user())->activeSuspensions();

        if ($suspensions->isEmpty()) {
            return $next($request);
        }

        $suspension = $suspensions->first();

        return response()->view('suspended', [
            'reason' => $suspension->reason,
            'expires_at' => $suspension->expires_at,
            'suspended_by' => $suspension->suspendedBy,
            'can_appeal' => $this->canAppeal($suspension),
        ], 403);
    }

    private function canAppeal($suspension): bool
    {
        return !$suspension->metadata['permanent'] ?? false;
    }
}
```

## Checking Multiple Contexts

The context middleware checks multiple factors at once:

```php
// Register in app/Http/Kernel.php
protected $routeMiddleware = [
    'suspended.context' => CheckContextMiddleware::class,
];
```

```php
Route::middleware(['suspended.context:email,ip,fingerprint'])->group(function () {
    Route::post('/register', RegisterController::class);
});
```

## Excluding Routes

### Via Configuration

```php
'middleware' => [
    'except' => [
        'login',
        'logout',
        'password/*',
        'suspended',
        'api/health',
    ],
],
```

### Via Middleware Parameters

```php
Route::middleware(['suspended:except:login,logout'])->group(function () {
    // Routes here
});
```

### Programmatic Exclusion

```php
class CheckSuspendedMiddleware
{
    protected array $except = [
        'login',
        'logout',
        'suspended',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check suspension...
    }

    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern) || $request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
```

## IP-Based Middleware

Check suspensions by IP address for unauthenticated requests:

```php
use Cline\Suspend\Facades\Suspend;

class CheckIpMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        if (Suspend::check()->ip($ip)->matches()) {
            abort(403, 'Your IP address has been blocked.');
        }

        return $next($request);
    }
}
```

## Country-Based Middleware

Block requests from specific countries:

```php
use Cline\Suspend\Facades\Suspend;

class CheckCountryMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $country = app('suspend.geo_resolver')->resolve($request->ip());

        if ($country && Suspend::check()->country($country)->matches()) {
            abort(403, 'Service not available in your region.');
        }

        return $next($request);
    }
}
```

## Registration Protection

Prevent suspended contexts from registering new accounts:

```php
class RegistrationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, Closure $next) {
            $isSuspended = Suspend::check()
                ->email($request->input('email'))
                ->ip($request->ip())
                ->matches();

            if ($isSuspended) {
                return back()->withErrors([
                    'email' => 'Registration is not available.',
                ]);
            }

            return $next($request);
        });
    }
}
```

## Rate Limiting Integration

Combine suspension checks with rate limiting:

```php
RateLimiter::for('api', function (Request $request) {
    $ip = $request->ip();

    // Suspended IPs get no rate limit quota
    if (Suspend::check()->ip($ip)->matches()) {
        return Limit::none();
    }

    return Limit::perMinute(60)->by($request->user()?->id ?: $ip);
});
```

## Middleware Groups

Create middleware groups for common patterns:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
    ],

    'protected' => [
        'auth',
        'suspended',
        'verified',
    ],

    'api.protected' => [
        'auth:sanctum',
        'suspended',
        'throttle:api',
    ],
];
```

Use the groups:

```php
Route::middleware('protected')->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

Route::middleware('api.protected')->prefix('api')->group(function () {
    Route::get('/user', UserController::class);
});
```

<a id="doc-docs-querying"></a>

## Query Scopes

The Suspension model includes several query scopes for common filtering needs.

### Status-Based Scopes

```php
use Cline\Suspend\Database\Models\Suspension;

// All currently active suspensions
$active = Suspension::active()->get();

// All expired suspensions
$expired = Suspension::expired()->get();

// All revoked suspensions
$revoked = Suspension::revoked()->get();

// All pending suspensions (future start date)
$pending = Suspension::pending()->get();
```

### Context-Based Queries

```php
// Suspensions for a specific model type
$userSuspensions = Suspension::query()
    ->where('context_type', User::class)
    ->get();

// Suspensions for a specific entity
$suspension = Suspension::query()
    ->where('context_type', User::class)
    ->where('context_id', $userId)
    ->active()
    ->first();
```

### Match-Based Queries

```php
// All email-based suspensions
$emailBlocks = Suspension::query()
    ->where('match_type', 'email')
    ->get();

// All IP-based suspensions
$ipBlocks = Suspension::query()
    ->where('match_type', 'ip')
    ->get();

// Find suspension by match value
$suspension = Suspension::query()
    ->where('match_type', 'email')
    ->where('match_value', '*@spam.com')
    ->first();
```

## Time-Based Queries

### Expiring Soon

```php
// Suspensions expiring in the next 24 hours
$expiringSoon = Suspension::query()
    ->active()
    ->where('expires_at', '<=', now()->addDay())
    ->where('expires_at', '>', now())
    ->get();
```

### Recently Created

```php
// Suspensions created today
$today = Suspension::query()
    ->whereDate('created_at', today())
    ->get();

// Suspensions created in the last week
$lastWeek = Suspension::query()
    ->where('created_at', '>=', now()->subWeek())
    ->get();
```

### Historical Analysis

```php
// Suspensions by month
$monthlyStats = Suspension::query()
    ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
    ->groupByRaw('YEAR(created_at), MONTH(created_at)')
    ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
    ->get();
```

## Facade Methods

### For Entity Suspensions

```php
use Cline\Suspend\Facades\Suspend;

// Get all suspensions for a user
$all = Suspend::for($user)->all();

// Get only active suspensions
$active = Suspend::for($user)->activeSuspensions();

// Get the first active suspension
$first = Suspend::for($user)->getActiveSuspension();

// Get suspension history (all statuses)
$history = Suspend::for($user)->history();
```

### For Context Matching

```php
// Get matching suspensions for a context
$suspensions = Suspend::check()
    ->email('user@example.com')
    ->ip('1.2.3.4')
    ->getSuspensions();

// Check if any matches exist
$hasMatches = Suspend::check()
    ->email('user@example.com')
    ->matches();
```

## Complex Queries

### Multiple Conditions

```php
// Active entity suspensions with expiration
$temporary = Suspension::query()
    ->active()
    ->whereNotNull('context_type')
    ->whereNotNull('expires_at')
    ->get();

// Permanent context suspensions
$permanent = Suspension::query()
    ->active()
    ->whereNotNull('match_type')
    ->whereNull('expires_at')
    ->get();
```

### With Strategy Filtering

```php
// Time-window strategy suspensions
$timeWindow = Suspension::query()
    ->where('strategy_type', 'time_window')
    ->active()
    ->get();

// Country-based strategy suspensions
$countryBased = Suspension::query()
    ->where('strategy_type', 'country')
    ->active()
    ->get();
```

### With Actor Information

```php
// Suspensions created by a specific admin
$byAdmin = Suspension::query()
    ->where('suspended_by_type', User::class)
    ->where('suspended_by_id', $adminId)
    ->get();

// Suspensions with the actor relationship
$withActor = Suspension::query()
    ->with('suspendedBy')
    ->latest()
    ->get();

foreach ($withActor as $suspension) {
    echo "Suspended by: {$suspension->suspendedBy?->name}";
}
```

## Aggregation Queries

### Counts by Type

```php
// Count by suspension type
$counts = Suspension::query()
    ->selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN context_type IS NOT NULL THEN 1 ELSE 0 END) as entity_based,
        SUM(CASE WHEN match_type IS NOT NULL THEN 1 ELSE 0 END) as context_based
    ')
    ->active()
    ->first();
```

### Match Type Distribution

```php
// Distribution of match types
$distribution = Suspension::query()
    ->selectRaw('match_type, COUNT(*) as count')
    ->whereNotNull('match_type')
    ->groupBy('match_type')
    ->get();
```

## Pagination

```php
// Paginated active suspensions
$paginated = Suspension::query()
    ->active()
    ->latest()
    ->paginate(25);

// Cursor pagination for large datasets
$cursor = Suspension::query()
    ->active()
    ->orderBy('id')
    ->cursorPaginate(100);
```

## Eager Loading

```php
// Load related models
$suspensions = Suspension::query()
    ->with(['context', 'suspendedBy', 'revokedBy'])
    ->active()
    ->get();

foreach ($suspensions as $suspension) {
    if ($suspension->isEntityBased()) {
        echo "User: {$suspension->context->name}";
        echo "Suspended by: {$suspension->suspendedBy?->name}";
    }
}
```

## Admin Dashboard Queries

### Recent Activity

```php
// Recent suspension activity for admin dashboard
$recentActivity = Suspension::query()
    ->with(['context', 'suspendedBy'])
    ->latest()
    ->take(10)
    ->get();
```

### Summary Statistics

```php
// Dashboard summary
$stats = [
    'active' => Suspension::active()->count(),
    'pending' => Suspension::pending()->count(),
    'expiring_soon' => Suspension::active()
        ->where('expires_at', '<=', now()->addDay())
        ->count(),
    'created_today' => Suspension::whereDate('created_at', today())->count(),
    'revoked_today' => Suspension::revoked()
        ->whereDate('revoked_at', today())
        ->count(),
];
```

### Search

```php
// Search suspensions
$search = $request->input('search');

$results = Suspension::query()
    ->where(function ($query) use ($search) {
        $query->where('reason', 'like', "%{$search}%")
            ->orWhere('match_value', 'like', "%{$search}%")
            ->orWhere('context_id', $search);
    })
    ->with(['context', 'suspendedBy'])
    ->paginate(25);
```

<a id="doc-docs-strategies"></a>

## Overview

Strategies add conditional logic to suspensions. Instead of a simple on/off suspension, strategies evaluate conditions at check time to determine if the suspension is currently active.

## Built-in Strategies

| Strategy | Description |
|----------|-------------|
| `SimpleStrategy` | Always active (default) |
| `TimeWindowStrategy` | Active during specific hours/days |
| `IpAddressStrategy` | Active based on IP address |
| `CountryStrategy` | Active based on geographic location |
| `DeviceFingerprintStrategy` | Active based on device fingerprint |
| `ConditionalStrategy` | Custom callback-based logic |

## Time Window Strategy

Restrict suspension to specific hours and days:

```php
use Cline\Suspend\Facades\Suspend;

// Active only during business hours
Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [1, 2, 3, 4, 5], // Mon-Fri (1=Mon, 7=Sun)
        'timezone' => 'America/New_York',
    ])
    ->suspend('Restricted during business hours');

// Active only on weekends
Suspend::for($user)
    ->using('time_window', [
        'start' => '00:00',
        'end' => '23:59',
        'days' => [6, 7], // Sat-Sun
        'timezone' => 'UTC',
    ])
    ->suspend('Weekend restriction');
```

### Time Window Options

| Option | Type | Description |
|--------|------|-------------|
| `start` | string | Start time in `HH:MM` format |
| `end` | string | End time in `HH:MM` format |
| `days` | array | Days of week (1=Monday through 7=Sunday) |
| `timezone` | string | Timezone for evaluation |

## IP Address Strategy

Restrict suspension based on IP address:

```php
// Only suspended when outside allowed IPs
Suspend::for($user)
    ->using('ip_address', [
        'allowed_ips' => [
            '192.168.1.0/24',
            '10.0.0.0/8',
        ],
    ])
    ->suspend('Office network only');

// Suspended when matching blocked IPs
Suspend::for($user)
    ->using('ip_address', [
        'blocked_ips' => [
            '1.2.3.4',
            '5.6.7.0/24',
        ],
    ])
    ->suspend('Blocked IP range');
```

### IP Strategy Options

| Option | Type | Description |
|--------|------|-------------|
| `allowed_ips` | array | Only suspended when NOT matching these IPs |
| `blocked_ips` | array | Only suspended when matching these IPs |

Supports CIDR notation for both IPv4 and IPv6.

## Country Strategy

Restrict suspension based on geographic location:

```php
// Only suspended in specific countries
Suspend::for($user)
    ->using('country', [
        'blocked_countries' => ['XX', 'YY', 'ZZ'],
    ])
    ->suspend('Service not available in your region');

// Suspended everywhere except allowed countries
Suspend::for($user)
    ->using('country', [
        'allowed_countries' => ['US', 'CA', 'GB'],
    ])
    ->suspend('Available only in select countries');
```

### Country Strategy Options

| Option | Type | Description |
|--------|------|-------------|
| `blocked_countries` | array | Suspended only in these countries |
| `allowed_countries` | array | Suspended everywhere except these countries |

Countries use ISO 3166-1 alpha-2 codes.

## Device Fingerprint Strategy

Restrict suspension based on device fingerprinting:

```php
// Only suspended on specific devices
Suspend::for($user)
    ->using('device_fingerprint', [
        'blocked_fingerprints' => [
            'abc123def456',
            'xyz789ghi012',
        ],
    ])
    ->suspend('Blocked device');

// Suspended except on trusted devices
Suspend::for($user)
    ->using('device_fingerprint', [
        'allowed_fingerprints' => ['trusted-device-hash'],
    ])
    ->suspend('Untrusted device');
```

### Device Fingerprint Options

| Option | Type | Description |
|--------|------|-------------|
| `blocked_fingerprints` | array | Suspended only on these devices |
| `allowed_fingerprints` | array | Suspended except on these devices |

## Conditional Strategy

Create custom conditions with callbacks:

```php
Suspend::for($user)
    ->using('conditional', [
        'callback' => function ($context) {
            // Custom logic here
            return $context['subscription_expired'] ?? false;
        },
    ])
    ->suspend('Subscription required');
```

## Combining Strategies

You can't directly combine multiple strategies on a single suspension, but you can create multiple suspensions with different strategies:

```php
// Time-based restriction
Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'America/New_York',
    ])
    ->suspend('Work hours restriction');

// Geographic restriction
Suspend::for($user)
    ->using('country', [
        'blocked_countries' => ['XX'],
    ])
    ->suspend('Geographic restriction');

// User is suspended if ANY suspension is active
```

## Custom Strategies

Create your own strategy for complex business logic:

```php
use Cline\Suspend\Contracts\Strategy;

class SubscriptionStrategy implements Strategy
{
    public function type(): string
    {
        return 'subscription';
    }

    public function isActive(array $metadata, array $context): bool
    {
        $requiredPlan = $metadata['required_plan'] ?? 'premium';
        $userPlan = $context['plan'] ?? 'free';

        $plans = ['free' => 0, 'basic' => 1, 'premium' => 2, 'enterprise' => 3];

        return ($plans[$userPlan] ?? 0) < ($plans[$requiredPlan] ?? 0);
    }

    public function validate(array $metadata): bool
    {
        return isset($metadata['required_plan']);
    }
}
```

Register your strategy:

```php
// In a service provider
use Cline\Suspend\Facades\Suspend;

public function boot()
{
    Suspend::registerStrategy(new SubscriptionStrategy());
}
```

Use it:

```php
Suspend::for($user)
    ->using('subscription', [
        'required_plan' => 'premium',
    ])
    ->suspend('Premium feature');

// Check with context
$isSuspended = Suspend::for($user)
    ->withContext(['plan' => auth()->user()->plan])
    ->isSuspended();
```

## Providing Context

Strategies receive context when checking suspensions:

```php
$isSuspended = Suspend::for($user)
    ->withContext([
        'ip' => $request->ip(),
        'country' => $geoResolver->resolve($request->ip()),
        'fingerprint' => $request->header('X-Device-Fingerprint'),
        'plan' => $user->subscription?->plan,
    ])
    ->isSuspended();
```

The middleware automatically provides IP and country context.

## Strategy Metadata Storage

Strategy metadata is stored with the suspension and evaluated at check time:

```php
$suspension = Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'UTC',
    ])
    ->suspend('Test');

// Metadata is stored in the suspension
$suspension->strategy_type;    // 'time_window'
$suspension->strategy_metadata; // ['start' => '09:00', ...]
```

## Strategy Validation

Strategies validate their metadata before suspension creation:

```php
// This will fail - missing required metadata
Suspend::for($user)
    ->using('time_window', [])
    ->suspend('Invalid');

// MissingStrategyMetadataException: Strategy 'time_window' requires 'start' metadata
```
