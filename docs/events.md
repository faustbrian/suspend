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
