# Middleware

Suspend provides middleware to automatically check and block suspended users or contexts.

## Built-in Middleware

The `CheckSuspension` middleware is automatically registered with the alias `suspended`.

### Basic Usage

```php
// routes/web.php
Route::middleware(['auth', 'suspended'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/profile', ProfileController::class);
});
```

### Single Route

```php
Route::get('/api/data', DataController::class)
    ->middleware(['auth', 'suspended']);
```

### Controller Constructor

```php
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'suspended']);
    }
}
```

## Default Behavior

The middleware checks if the authenticated user is suspended:

1. Gets the authenticated user from `$request->user()`
2. Checks if user implements `Suspendable` or has active suspensions
3. Returns 403 Forbidden if suspended
4. Continues to next middleware if not suspended

## Customizing the Response

### Custom Response Handler

Create a custom exception handler for suspended users:

```php
// app/Exceptions/Handler.php
use Cline\Suspend\Exceptions\SuspendedException;

public function render($request, Throwable $exception)
{
    if ($exception instanceof SuspendedException) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Account suspended',
                'reason' => $exception->getReason(),
                'expires_at' => $exception->getExpiresAt(),
            ], 403);
        }

        return redirect()->route('suspended')
            ->with('suspension', $exception->getSuspension());
    }

    return parent::render($request, $exception);
}
```

### Custom Suspended Page

```php
// routes/web.php
Route::get('/suspended', function () {
    return view('suspended', [
        'suspension' => session('suspension'),
    ]);
})->name('suspended');
```

```blade
{{-- resources/views/suspended.blade.php --}}
<h1>Account Suspended</h1>

@if($suspension)
    <p>Reason: {{ $suspension->reason }}</p>

    @if($suspension->expires_at)
        <p>Expires: {{ $suspension->expires_at->diffForHumans() }}</p>
    @else
        <p>This suspension is permanent.</p>
    @endif
@endif

<a href="/contact">Contact Support</a>
```

## Context-Based Middleware

Check multiple contexts, not just the authenticated user:

```php
// app/Http/Middleware/CheckSuspendedContext.php
namespace App\Http\Middleware;

use Closure;
use Cline\Suspend\Facades\Suspend;
use Illuminate\Http\Request;

class CheckSuspendedContext
{
    public function handle(Request $request, Closure $next)
    {
        $isSuspended = Suspend::check()
            ->email($request->input('email'))
            ->ip($request->ip())
            ->fingerprint($request->header('X-Device-Fingerprint'))
            ->matches();

        if ($isSuspended) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
```

Register it:

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'check-context' => \App\Http\Middleware\CheckSuspendedContext::class,
    ]);
})
```

Use it:

```php
Route::post('/register', RegisterController::class)
    ->middleware('check-context');
```

## Middleware Parameters

Pass parameters to customize behavior:

```php
// app/Http/Middleware/CheckSuspension.php
namespace App\Http\Middleware;

use Closure;
use Cline\Suspend\Facades\Suspend;
use Illuminate\Http\Request;

class CheckSuspension
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        foreach ($guards as $guard) {
            $user = $request->user($guard);

            if ($user && Suspend::for($user)->isSuspended()) {
                abort(403, 'Account suspended');
            }
        }

        return $next($request);
    }
}
```

Usage with parameters:

```php
Route::middleware('suspended:web,api')->group(function () {
    // Routes that check both web and api guards
});
```

## Soft Suspension Middleware

Allow read-only access for suspended users:

```php
// app/Http/Middleware/SoftSuspension.php
namespace App\Http\Middleware;

use Closure;
use Cline\Suspend\Facades\Suspend;
use Illuminate\Http\Request;

class SoftSuspension
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && Suspend::for($user)->isSuspended()) {
            // Allow GET requests (read-only)
            if ($request->isMethod('GET')) {
                // Add a flag for views to show warning
                $request->attributes->set('user_suspended', true);
                return $next($request);
            }

            // Block write operations
            abort(403, 'Your account is suspended. Read-only access.');
        }

        return $next($request);
    }
}
```

In your views:

```blade
@if(request()->attributes->get('user_suspended'))
    <div class="alert alert-warning">
        Your account is suspended. Some features are disabled.
    </div>
@endif
```

## API Rate Limit + Suspension

Combine suspension checks with rate limiting:

```php
// app/Http/Middleware/ApiSuspensionCheck.php
namespace App\Http\Middleware;

use Closure;
use Cline\Suspend\Facades\Suspend;
use Illuminate\Http\Request;

class ApiSuspensionCheck
{
    public function handle(Request $request, Closure $next)
    {
        // Check IP-based suspensions first (before auth)
        if (Suspend::check()->ip($request->ip())->matches()) {
            return response()->json([
                'error' => 'IP address blocked',
            ], 403);
        }

        // Check user suspension if authenticated
        if ($user = $request->user()) {
            $suspensions = Suspend::for($user)->activeSuspensions();

            if ($suspensions->isNotEmpty()) {
                $suspension = $suspensions->first();

                return response()->json([
                    'error' => 'Account suspended',
                    'reason' => $suspension->reason,
                    'expires_at' => $suspension->expires_at?->toIso8601String(),
                ], 403);
            }
        }

        return $next($request);
    }
}
```

## Global Middleware

Apply suspension checks to all routes:

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\GlobalSuspensionCheck::class);
})
```

```php
// app/Http/Middleware/GlobalSuspensionCheck.php
namespace App\Http\Middleware;

use Closure;
use Cline\Suspend\Facades\Suspend;
use Illuminate\Http\Request;

class GlobalSuspensionCheck
{
    protected array $except = [
        'suspended',
        'logout',
        'contact',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Skip excluded routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && Suspend::for($user)->isSuspended()) {
            return redirect()->route('suspended');
        }

        return $next($request);
    }

    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }

        return false;
    }
}
```

## Testing Middleware

```php
use Cline\Suspend\Facades\Suspend;

test('suspended users cannot access dashboard', function () {
    $user = User::factory()->create();
    Suspend::for($user)->suspend('Test suspension');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertForbidden();
});

test('active users can access dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('expired suspensions allow access', function () {
    $user = User::factory()->create();
    Suspend::for($user)->suspend('Test', now()->subDay()); // Already expired

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
```
