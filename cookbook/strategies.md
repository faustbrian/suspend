# Strategies

Strategies add conditional logic to suspensions. A suspension only takes effect when its strategy's conditions are met.

## Built-in Strategies

| Strategy | Identifier | Description |
|----------|------------|-------------|
| `SimpleStrategy` | `simple` | Always matches (default) |
| `TimeWindowStrategy` | `time_window` | Active only during specific times/days |
| `IpAddressStrategy` | `ip_address` | Matches specific IP addresses/ranges |
| `CountryStrategy` | `country` | Matches based on geo-location |
| `DeviceFingerprintStrategy` | `device_fingerprint` | Matches device fingerprints |
| `ConditionalStrategy` | `conditional` | Custom callback-based conditions |

## Using Strategies

Apply a strategy when creating a suspension:

```php
use Cline\Suspend\Facades\Suspend;

Suspend::for($user)
    ->using('strategy_identifier', ['metadata' => 'here'])
    ->suspend('Reason');
```

## Time Window Strategy

Restrict suspension to specific time periods:

### Business Hours Only

```php
Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'America/New_York',
    ])
    ->suspend('Restricted during business hours');
```

### Specific Days

```php
Suspend::for($user)
    ->using('time_window', [
        'days' => [1, 2, 3, 4, 5], // Monday-Friday (0=Sunday)
    ])
    ->suspend('Weekday restriction');
```

### Combined Time and Days

```php
Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [1, 2, 3, 4, 5],
        'timezone' => 'Europe/London',
    ])
    ->suspend('UK business hours only');
```

### Date Range

```php
Suspend::for($user)
    ->using('time_window', [
        'start' => '2024-12-20 00:00:00',
        'end' => '2025-01-02 23:59:59',
    ])
    ->suspend('Holiday maintenance period');
```

## IP Address Strategy

Restrict suspension based on IP address:

### Whitelist IPs

```php
// Suspension does NOT apply from these IPs
Suspend::for($user)
    ->using('ip_address', [
        'allowed_ips' => [
            '192.168.1.100',
            '10.0.0.0/8',
        ],
    ])
    ->suspend('Blocked except from office');
```

### Blacklist IPs

```php
// Suspension ONLY applies from these IPs
Suspend::for($user)
    ->using('ip_address', [
        'blocked_ips' => [
            '1.2.3.4',
            '5.6.7.0/24',
        ],
    ])
    ->suspend('Blocked from specific IPs');
```

## Country Strategy

Restrict based on geographic location:

### Block Specific Countries

```php
Suspend::for($user)
    ->using('country', [
        'blocked_countries' => ['RU', 'CN', 'KP'],
    ])
    ->suspend('Geographic restriction');
```

### Allow Only Specific Countries

```php
Suspend::for($user)
    ->using('country', [
        'allowed_countries' => ['US', 'CA', 'GB'],
    ])
    ->suspend('Allowed regions only');
```

## Device Fingerprint Strategy

Match specific device signatures:

```php
Suspend::for($user)
    ->using('device_fingerprint', [
        'fingerprints' => [
            'abc123def456',
            'xyz789*', // Wildcard support
        ],
    ])
    ->suspend('Blocked device');
```

## Conditional Strategy

For custom logic, use the conditional strategy with a callback:

```php
// Register a named condition
Suspend::registerStrategy(new ConditionalStrategy(
    'premium_only',
    fn($request, $metadata) => !$request->user()?->isPremium()
));

// Use it
Suspend::for($user)
    ->using('premium_only')
    ->suspend('Feature restricted to premium users');
```

## Creating Custom Strategies

Implement the `Strategy` contract:

```php
use Cline\Suspend\Contracts\Strategy;
use Illuminate\Http\Request;

class RateLimitStrategy implements Strategy
{
    public function matches(Request $request, array $metadata = []): bool
    {
        $key = $metadata['key'] ?? 'default';
        $limit = $metadata['limit'] ?? 100;
        $window = $metadata['window'] ?? 3600;

        $current = Cache::get("rate_limit:{$key}", 0);

        return $current >= $limit;
    }

    public function identifier(): string
    {
        return 'rate_limit';
    }
}
```

Register your strategy:

```php
// In a service provider
use Cline\Suspend\Facades\Suspend;

public function boot()
{
    Suspend::registerStrategy(new RateLimitStrategy());
}
```

Use it:

```php
Suspend::for($user)
    ->using('rate_limit', [
        'key' => "user:{$user->id}",
        'limit' => 1000,
        'window' => 3600,
    ])
    ->suspend('Rate limit exceeded');
```

## Combining Strategies

You can only use one strategy per suspension, but you can create compound strategies:

```php
class BusinessHoursFromOfficeStrategy implements Strategy
{
    public function __construct(
        private TimeWindowStrategy $timeStrategy,
        private IpAddressStrategy $ipStrategy,
    ) {}

    public function matches(Request $request, array $metadata = []): bool
    {
        $timeMatches = $this->timeStrategy->matches($request, [
            'start' => '09:00',
            'end' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ]);

        $ipMatches = $this->ipStrategy->matches($request, [
            'allowed_ips' => $metadata['office_ips'] ?? [],
        ]);

        // Both must match
        return $timeMatches && $ipMatches;
    }

    public function identifier(): string
    {
        return 'business_hours_office';
    }
}
```

## Strategy Metadata Storage

Strategy metadata is stored with the suspension and passed to the strategy when checking:

```php
// Creating
Suspend::for($user)
    ->using('time_window', [
        'start' => '09:00',
        'end' => '17:00',
    ])
    ->suspend();

// The metadata is stored in the suspension record
$suspension = Suspension::first();
$suspension->strategy; // 'time_window'
$suspension->strategy_metadata; // ['start' => '09:00', 'end' => '17:00']
```

## Checking Strategy Conditions

When checking suspensions, strategies are automatically evaluated:

```php
// Only returns true if:
// 1. An active suspension exists for the user
// 2. The suspension's strategy conditions are met
$isSuspended = Suspend::for($user)->isSuspended();
```

For context matching, strategies are evaluated per-suspension:

```php
$suspended = Suspend::check()
    ->email($email)
    ->matches(); // Strategies evaluated for each matching suspension
```
