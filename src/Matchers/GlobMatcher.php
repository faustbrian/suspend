<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use const FNM_CASEFOLD;

use function fnmatch;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_trim;
use function method_exists;

/**
 * Matcher using shell-style glob patterns (fnmatch).
 *
 * Provides simple pattern matching without the complexity of regex:
 * - `*` matches any sequence of characters
 * - `?` matches any single character
 * - `[abc]` matches any character in the set
 * - `[!abc]` matches any character not in the set
 *
 * Examples:
 * - `spam@*` matches any email from spam@
 * - `192.168.*.*` matches any IP in 192.168.x.x
 * - `+1555*` matches any phone starting with +1555
 * - `*.example.com` matches any subdomain of example.com
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class GlobMatcher implements Matcher
{
    /**
     * Get the matcher type identifier.
     *
     * @return string The type identifier 'glob'
     */
    public function type(): string
    {
        return 'glob';
    }

    /**
     * Normalize a value into a consistent string format.
     *
     * Converts various input types (strings, scalars, objects with __toString)
     * into a trimmed string representation. Non-convertible values return empty string.
     *
     * @param  mixed  $value The value to normalize (string, scalar, or object with __toString)
     * @return string The normalized string value, or empty string if not convertible
     */
    public function normalize(mixed $value): string
    {
        if (is_string($value)) {
            return mb_trim($value);
        }

        if (is_scalar($value)) {
            return mb_trim((string) $value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return mb_trim((string) $value);
        }

        return '';
    }

    /**
     * Check if a value matches the glob pattern.
     *
     * Uses PHP's fnmatch() with case-insensitive matching for better user experience.
     * Supports standard glob wildcards: * (any sequence), ? (single char), [abc] (character sets).
     *
     * @param  string $suspendedValue The glob pattern stored in the suspension record
     * @param  mixed  $checkValue     The value to match against the pattern (will be normalized)
     * @return bool   True if the value matches the pattern, false otherwise
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        $pattern = $this->normalize($suspendedValue);
        $value = $this->normalize($checkValue);

        return fnmatch($pattern, $value, FNM_CASEFOLD);
    }

    /**
     * Validate that a value is an acceptable glob pattern.
     *
     * Accepts any non-empty string as a valid pattern. Both plain strings
     * (exact match) and patterns with glob metacharacters are valid.
     *
     * @param  mixed $value The value to validate as a glob pattern
     * @return bool  True if the pattern is non-empty, false otherwise
     */
    public function validate(mixed $value): bool
    {
        $pattern = $this->normalize($value);

        return $pattern !== '';
    }

    /**
     * Extract meaningful information from a glob pattern.
     *
     * Glob patterns are matching rules without extractable data components,
     * so this method always returns null.
     *
     * @param mixed $value The glob pattern value
     */
    public function extract(mixed $value): ?string
    {
        return null;
    }
}
