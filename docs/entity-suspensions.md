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

See [Events](./events.md) for detailed event handling.

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

See [Querying](./querying.md) for more query examples.
