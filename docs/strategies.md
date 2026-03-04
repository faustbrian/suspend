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
