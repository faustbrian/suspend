<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use const FILTER_VALIDATE_EMAIL;

use function count;
use function explode;
use function filter_var;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_strtolower;
use function mb_trim;
use function method_exists;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_replace;

/**
 * Matcher for email addresses with wildcard pattern support.
 *
 * Validates and matches email addresses with support for exact matching
 * and flexible wildcard patterns. Useful for blocking specific users,
 * entire domains, or suspicious email patterns from registration, orders,
 * or other user actions.
 *
 * All email matching is case-insensitive following RFC 5321 local-part
 * handling common in modern systems. Normalization converts to lowercase
 * and trims whitespace.
 *
 * Supported patterns:
 * - `user@example.com` - Exact email match
 *
 * - `*@example.com` - Any user at specific domain
 * - `user@*` - Specific user at any domain (uncommon but supported)
 *
 * - `*@*.example.com` - Any user at any subdomain of example.com
 *
 * ```php
 * $matcher = new EmailMatcher();
 *
 * $matcher->matches('*@example.com', 'john@example.com'); // true
 * $matcher->matches('admin@*', 'admin@anywhere.com'); // true
 * $matcher->extract('user@example.com'); // 'example.com'
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class EmailMatcher implements Matcher
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'email';
    }

    /**
     * {@inheritDoc}
     */
    public function normalize(mixed $value): string
    {
        if (is_string($value)) {
            return mb_strtolower(mb_trim($value));
        }

        if (is_scalar($value)) {
            return mb_strtolower(mb_trim((string) $value));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return mb_strtolower(mb_trim((string) $value));
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        $normalizedCheck = $this->normalize($checkValue);
        $normalizedSuspended = $this->normalize($suspendedValue);

        // Exact match
        if ($normalizedSuspended === $normalizedCheck) {
            return true;
        }

        // Wildcard pattern matching
        if (str_contains($normalizedSuspended, '*')) {
            return $this->matchesPattern($normalizedSuspended, $normalizedCheck);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value): bool
    {
        $normalized = $this->normalize($value);

        // Allow wildcard patterns
        if (str_contains($normalized, '*')) {
            return $this->validatePattern($normalized);
        }

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * {@inheritDoc}
     *
     * Extracts the domain from an email address.
     */
    public function extract(mixed $value): ?string
    {
        $normalized = $this->normalize($value);
        $parts = explode('@', $normalized);

        if (count($parts) !== 2) {
            return null;
        }

        return $parts[1];
    }

    /**
     * Match email against a wildcard pattern.
     *
     * Converts wildcard patterns to regular expressions for matching.
     * Wildcards can appear in the local part (before @), domain part
     * (after @), or both.
     *
     * @param  string $pattern Wildcard pattern (e.g., '*@example.com')
     * @param  string $email   Normalized email address to check
     * @return bool   True if the email matches the pattern
     */
    private function matchesPattern(string $pattern, string $email): bool
    {
        // Convert pattern to regex
        $regex = '/^'.str_replace(
            ['\\*', '\\@'],
            ['[^@]*', '@'],
            preg_quote($pattern, '/'),
        ).'$/i';

        return (bool) preg_match($regex, $email);
    }

    /**
     * Validate a wildcard pattern.
     *
     * Ensures the pattern has the basic email structure (contains @)
     * and at least one side has meaningful content beyond just wildcards.
     *
     * Prevents degenerate patterns like '*@*' from being stored.
     *
     * @param  string $pattern The wildcard pattern to validate
     * @return bool   True if the pattern is structurally valid
     */
    private function validatePattern(string $pattern): bool
    {
        // Must contain @ and at least one side must have content
        if (!str_contains($pattern, '@')) {
            return false;
        }

        $parts = explode('@', $pattern);

        if (count($parts) !== 2) {
            return false;
        }

        // Both sides cannot be just wildcards
        $local = $parts[0];
        $domain = $parts[1];

        // At least one side must have meaningful content
        return ($local !== '' && $local !== '*') || ($domain !== '' && $domain !== '*');
    }
}
