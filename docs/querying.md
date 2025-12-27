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
