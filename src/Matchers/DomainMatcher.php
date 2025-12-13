<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use function array_slice;
use function count;
use function explode;
use function implode;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_strpos;
use function mb_strtolower;
use function mb_substr;
use function mb_trim;
use function method_exists;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Matcher for domain names with wildcard pattern support.
 *
 * Validates and matches domain names with support for exact matching,
 * subdomain wildcards, and pattern matching. Useful for blocking entire
 * domains or specific subdomain patterns in email addresses, URLs, or
 * referrer headers.
 *
 * Normalization removes protocols, trailing dots, and paths for clean
 * domain-only matching. All matching is case-insensitive.
 *
 * Supported patterns:
 * - `example.com` - Exact match only
 * - `*.example.com` - Matches any subdomain of example.com
 * - `mail.*.com` - Matches mail.anything.com pattern
 * - `sub.example.com` - Matches exactly or as a base for deeper subdomains
 *
 * ```php
 * $matcher = new DomainMatcher();
 * $matcher->matches('example.com', 'www.example.com'); // false (exact match only)
 * $matcher->matches('*.example.com', 'www.example.com'); // true (wildcard match)
 * $matcher->extract('www.example.com'); // 'example.com' (root domain)
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class DomainMatcher implements Matcher
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'domain';
    }

    /**
     * {@inheritDoc}
     */
    public function normalize(mixed $value): string
    {
        if (is_string($value)) {
            $stringValue = $value;
        } elseif (is_scalar($value)) {
            $stringValue = (string) $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $stringValue = (string) $value;
        } else {
            $stringValue = '';
        }

        $domain = mb_strtolower(mb_trim($stringValue));

        // Remove trailing dot if present
        if (str_ends_with($domain, '.')) {
            $domain = mb_substr($domain, 0, -1);
        }

        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;

        // Remove path if present
        $slashPos = mb_strpos($domain, '/');

        if ($slashPos !== false) {
            return mb_substr($domain, 0, $slashPos);
        }

        return $domain;
    }

    /**
     * {@inheritDoc}
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        $checkDomain = $this->normalize($checkValue);
        $suspended = $this->normalize($suspendedValue);

        // Exact match
        if ($suspended === $checkDomain) {
            return true;
        }

        // Wildcard pattern matching
        if (str_contains($suspended, '*')) {
            return $this->matchesPattern($suspended, $checkDomain);
        }

        // Check if check domain is a subdomain of suspended domain
        return str_ends_with($checkDomain, '.'.$suspended);
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

        // Basic domain validation
        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
            $normalized,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Extracts the root domain (e.g., example.com from sub.example.com).
     */
    public function extract(mixed $value): ?string
    {
        $normalized = $this->normalize($value);
        $parts = explode('.', $normalized);

        if (count($parts) < 2) {
            return null;
        }

        // Return last two parts as root domain (simplified)
        return implode('.', array_slice($parts, -2));
    }

    /**
     * Match domain against a wildcard pattern.
     *
     * Handles wildcard matching for domain patterns. Special case handling
     * for the common `*.domain.com` pattern, with regex fallback for
     * wildcards in other positions.
     *
     * @param  string $pattern Pattern containing wildcards (e.g., '*.example.com')
     * @param  string $domain  Normalized domain to check against the pattern
     * @return bool   True if the domain matches the pattern
     */
    private function matchesPattern(string $pattern, string $domain): bool
    {
        // Handle *.example.com pattern
        if (str_starts_with($pattern, '*.')) {
            $baseDomain = mb_substr($pattern, 2);

            // Match the base domain exactly or as subdomain
            return $domain === $baseDomain || str_ends_with($domain, '.'.$baseDomain);
        }

        // Convert pattern to regex for other wildcard positions
        $regex = '/^'.str_replace(
            ['\\*', '\\.'],
            ['[a-z0-9-]+', '\\.'],
            preg_quote($pattern, '/'),
        ).'$/i';

        return (bool) preg_match($regex, $domain);
    }

    /**
     * Validate a wildcard pattern.
     *
     * Ensures wildcard patterns contain at least one dot and have
     * meaningful content beyond just wildcards. Prevents invalid
     * patterns like '*' or '.*' from being stored.
     *
     * @param  string $pattern The wildcard pattern to validate
     * @return bool   True if the pattern is valid
     */
    private function validatePattern(string $pattern): bool
    {
        // Must contain at least one dot
        if (!str_contains($pattern, '.')) {
            return false;
        }

        // Cannot be just wildcards
        $withoutWildcards = str_replace('*', '', $pattern);

        return $withoutWildcards !== '' && $withoutWildcards !== '.';
    }
}
