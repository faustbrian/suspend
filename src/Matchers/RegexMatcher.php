<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use function ini_get;
use function ini_set;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_trim;
use function method_exists;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;

/**
 * Matcher using regular expressions.
 *
 * The suspended value is treated as a PCRE pattern.
 * Patterns should include delimiters (e.g., /pattern/i).
 *
 * Security: This matcher includes protection against ReDoS attacks
 * by limiting PCRE backtracking and recursion.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RegexMatcher implements Matcher
{
    /**
     * Maximum backtrack limit to prevent ReDoS attacks.
     *
     * Conservative limit prevents catastrophic backtracking on malicious patterns
     * while allowing most legitimate regex operations to complete successfully.
     */
    private const int BACKTRACK_LIMIT = 100_000;

    /**
     * Maximum recursion limit to prevent stack overflow.
     *
     * Prevents deeply nested regex patterns from consuming excessive stack space
     * and potentially crashing the PHP process.
     */
    private const int RECURSION_LIMIT = 10_000;

    /**
     * Get the matcher type identifier.
     *
     * @return string The type identifier 'regex'
     */
    public function type(): string
    {
        return 'regex';
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
     * Check if a value matches the regex pattern.
     *
     * Executes the suspended value as a PCRE pattern against the check value.
     * Uses ReDoS protection via safePreg() to prevent catastrophic backtracking attacks.
     *
     * @param  string $suspendedValue The PCRE pattern stored in the suspension record
     * @param  mixed  $checkValue     The value to match against the pattern
     * @return bool   True if the pattern matches the value, false otherwise
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        $pattern = $this->normalize($suspendedValue);

        if (is_string($checkValue)) {
            $value = $checkValue;
        } elseif (is_scalar($checkValue)) {
            $value = (string) $checkValue;
        } elseif (is_object($checkValue) && method_exists($checkValue, '__toString')) {
            $value = (string) $checkValue;
        } else {
            $value = '';
        }

        return $this->safePreg(static fn (): int|false => preg_match($pattern, $value)) === 1;
    }

    /**
     * Validate that a value is a valid PCRE pattern.
     *
     * Attempts to execute the pattern against an empty string to verify syntax.
     * Returns false for empty patterns or patterns with syntax errors.
     *
     * @param  mixed $value The value to validate as a regex pattern
     * @return bool  True if the pattern is valid PCRE syntax, false otherwise
     */
    public function validate(mixed $value): bool
    {
        $pattern = $this->normalize($value);

        if ($pattern === '') {
            return false;
        }

        return $this->safePreg(static fn (): int|false => preg_match($pattern, '')) !== false;
    }

    /**
     * Extract meaningful information from a regex pattern.
     *
     * Regex patterns are matching rules without extractable data components,
     * so this method always returns null.
     *
     * @param mixed $value The regex pattern value
     */
    public function extract(mixed $value): ?string
    {
        return null;
    }

    /**
     * Execute a PCRE function with ReDoS protection.
     *
     * Temporarily sets conservative limits on backtracking and recursion to prevent
     * Regular Expression Denial of Service (ReDoS) attacks, then restores the original
     * INI settings. Suppresses warnings from invalid patterns and ensures cleanup
     * occurs even if the callback throws an exception.
     *
     * @template T
     *
     * @param  callable(): T $callback The PCRE function to execute (e.g., preg_match closure)
     * @return T             The return value from the callback function
     */
    private function safePreg(callable $callback): mixed
    {
        $originalBacktrack = ini_get('pcre.backtrack_limit');
        $originalRecursion = ini_get('pcre.recursion_limit');

        ini_set('pcre.backtrack_limit', (string) self::BACKTRACK_LIMIT);
        ini_set('pcre.recursion_limit', (string) self::RECURSION_LIMIT);

        set_error_handler(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();

            if ($originalBacktrack !== false) {
                ini_set('pcre.backtrack_limit', $originalBacktrack);
            }

            if ($originalRecursion !== false) {
                ini_set('pcre.recursion_limit', $originalRecursion);
            }
        }
    }
}
