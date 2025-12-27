---
title: Context Matching
description: Suspend by patterns rather than specific entities - block email domains, IP ranges, phone prefixes, and more.
---

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
