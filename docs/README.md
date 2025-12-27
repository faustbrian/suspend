---
title: Getting Started
description: Suspend is a flexible, context-aware suspension and banning system for Laravel with entity-based and pattern-matched suspensions, pluggable strategies, and geo resolvers.
---

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

- [Entity Suspensions](./entity-suspensions.md) - Deep dive into model-based suspensions
- [Context Matching](./context-matching.md) - Pattern-based suspension with matchers
- [Strategies](./strategies.md) - Conditional suspension strategies
- [Middleware](./middleware.md) - Protecting routes and handling suspended users
- [Events](./events.md) - Reacting to suspension lifecycle events
- [Querying](./querying.md) - Finding and filtering suspensions
- [Configuration](./configuration.md) - Full configuration reference
