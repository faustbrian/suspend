# Querying Suspensions

Suspend provides Eloquent scopes and methods for querying suspension records.

## The Suspension Model

```php
use Cline\Suspend\Database\Models\Suspension;
```

## Query Scopes

### Active Suspensions

Suspensions that are currently in effect:

```php
// All currently active suspensions
$active = Suspension::active()->get();

// Active suspensions for a specific user
$userActive = Suspension::query()
    ->forContext($user)
    ->active()
    ->get();
```

A suspension is active when:
- Not revoked (`revoked_at` is null)
- Start time has passed (or no start time set)
- Not expired (or no expiration set)

### Expired Suspensions

Suspensions that have passed their expiration:

```php
$expired = Suspension::expired()->get();

// Expired in the last 7 days
$recentlyExpired = Suspension::expired()
    ->where('expires_at', '>=', now()->subDays(7))
    ->get();
```

### Revoked Suspensions

Suspensions that were manually revoked:

```php
$revoked = Suspension::revoked()->get();

// Revoked by a specific admin
$revokedByAdmin = Suspension::revoked()
    ->where('revoked_by_id', $admin->id)
    ->get();
```

### Pending Suspensions

Scheduled suspensions that haven't started yet:

```php
$pending = Suspension::pending()->get();

// Pending suspensions starting today
$startingToday = Suspension::pending()
    ->whereDate('starts_at', today())
    ->get();
```

### Context-Based Queries

```php
// All suspensions for a specific entity
$userSuspensions = Suspension::forContext($user)->get();

// Active suspensions for an organization
$orgActive = Suspension::query()
    ->forContext($organization)
    ->active()
    ->get();
```

### Match Type Queries

```php
// All email-based suspensions
$emailSuspensions = Suspension::forMatchType('email')->get();

// Active IP suspensions
$activeIpBlocks = Suspension::query()
    ->forMatchType('ip')
    ->active()
    ->get();

// Specific pattern
$domainBlock = Suspension::forMatchValue('email', '*@spam.com')->first();
```

## Combining Scopes

Scopes can be chained:

```php
// Active email suspensions created this week
$recentEmailBlocks = Suspension::query()
    ->forMatchType('email')
    ->active()
    ->where('created_at', '>=', now()->startOfWeek())
    ->get();

// Expired entity suspensions
$expiredUserSuspensions = Suspension::query()
    ->whereNotNull('context_type')
    ->expired()
    ->get();
```

## Status Checking

### On Suspension Instances

```php
$suspension = Suspension::find($id);

$suspension->isActive();   // Currently in effect
$suspension->isExpired();  // Past expiration date
$suspension->isRevoked();  // Manually revoked
$suspension->isPending();  // Scheduled for future

// Get status enum
$status = $suspension->status();
// Returns: SuspensionStatus::Active, Expired, Revoked, or Pending
```

### Type Checking

```php
// Check suspension type
$suspension->isEntityBased();   // Has context_type (suspended model)
$suspension->isContextBased();  // Has match_type (pattern-based)
```

## Relationships

### Context (Suspended Entity)

```php
$suspension = Suspension::find($id);

// Get the suspended model (User, Organization, etc.)
$user = $suspension->context;

// Eager load
$suspensions = Suspension::with('context')->active()->get();

foreach ($suspensions as $suspension) {
    if ($suspension->context instanceof User) {
        echo $suspension->context->email;
    }
}
```

### Suspended By

```php
// Who created the suspension
$admin = $suspension->suspendedBy;

// Eager load
$suspensions = Suspension::with('suspendedBy')->get();
```

### Revoked By

```php
// Who revoked the suspension
$revoker = $suspension->revokedBy;

// Only for revoked suspensions
$suspensions = Suspension::query()
    ->revoked()
    ->with('revokedBy')
    ->get();
```

## Common Queries

### Recently Created

```php
$recent = Suspension::query()
    ->orderByDesc('created_at')
    ->limit(50)
    ->get();
```

### Expiring Soon

```php
$expiringSoon = Suspension::query()
    ->active()
    ->whereNotNull('expires_at')
    ->where('expires_at', '<=', now()->addDay())
    ->get();
```

### Permanent Suspensions

```php
$permanent = Suspension::query()
    ->active()
    ->whereNull('expires_at')
    ->get();
```

### By Model Type

```php
// All user suspensions
$userSuspensions = Suspension::query()
    ->where('context_type', User::class)
    ->get();

// All organization suspensions
$orgSuspensions = Suspension::query()
    ->where('context_type', Organization::class)
    ->get();
```

### With Specific Reason

```php
$spamSuspensions = Suspension::query()
    ->where('reason', 'like', '%spam%')
    ->get();
```

### By Strategy

```php
// Time-window suspensions
$timeWindowSuspensions = Suspension::query()
    ->where('strategy', 'time_window')
    ->get();

// Country-based suspensions
$countryBlocks = Suspension::query()
    ->where('strategy', 'country')
    ->active()
    ->get();
```

## Aggregations

### Count by Type

```php
$counts = Suspension::query()
    ->selectRaw('match_type, count(*) as total')
    ->groupBy('match_type')
    ->pluck('total', 'match_type');

// Result: ['email' => 50, 'ip' => 120, 'phone' => 30]
```

### Count by Status

```php
$active = Suspension::active()->count();
$expired = Suspension::expired()->count();
$revoked = Suspension::revoked()->count();
$pending = Suspension::pending()->count();
```

### Suspensions Over Time

```php
$dailyCounts = Suspension::query()
    ->selectRaw('DATE(created_at) as date, count(*) as total')
    ->where('created_at', '>=', now()->subDays(30))
    ->groupBy('date')
    ->orderBy('date')
    ->get();
```

## Pagination

```php
$suspensions = Suspension::query()
    ->active()
    ->orderByDesc('created_at')
    ->paginate(25);
```

## Cleaning Up

### Delete Expired Suspensions

```php
// Delete suspensions expired more than 90 days ago
Suspension::query()
    ->expired()
    ->where('expires_at', '<', now()->subDays(90))
    ->delete();
```

### Archive Old Revoked Suspensions

```php
// Move old revoked suspensions to archive
$old = Suspension::query()
    ->revoked()
    ->where('revoked_at', '<', now()->subYear())
    ->get();

foreach ($old as $suspension) {
    SuspensionArchive::create($suspension->toArray());
    $suspension->delete();
}
```
