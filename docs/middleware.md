---
title: Middleware
description: Protect routes and handle suspended users with Suspend's built-in middleware.
---

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
