<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use function is_object;
use function is_scalar;
use function is_string;
use function mb_strlen;
use function mb_trim;
use function method_exists;
use function preg_match;

/**
 * Matcher for device fingerprints.
 *
 * Handles various fingerprint formats from libraries like FingerprintJS.
 * Supports exact matching only (no patterns) for security.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class FingerprintMatcher implements Matcher
{
    /**
     * Get the matcher type identifier.
     *
     * @return string The type identifier 'fingerprint'
     */
    public function type(): string
    {
        return 'fingerprint';
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
     * Check if a fingerprint matches the suspended value.
     *
     * Uses exact string matching only (no pattern matching) for security reasons.
     * Fingerprints must match precisely to prevent false positives.
     *
     * @param  string $suspendedValue The fingerprint value stored in the suspension record
     * @param  mixed  $checkValue     The fingerprint value to check against (will be normalized)
     * @return bool   True if fingerprints match exactly, false otherwise
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        return $this->normalize($suspendedValue) === $this->normalize($checkValue);
    }

    /**
     * Validate that a value is an acceptable fingerprint format.
     *
     * Fingerprints must be:
     * - Non-empty
     * - Between 8 and 128 characters long
     * - Contain only alphanumeric characters, hyphens, and underscores
     *
     * These constraints accommodate common fingerprinting libraries like FingerprintJS
     * while preventing excessively short or long values.
     *
     * @param  mixed $value The value to validate as a fingerprint
     * @return bool  True if the value is a valid fingerprint format, false otherwise
     */
    public function validate(mixed $value): bool
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return false;
        }

        $length = mb_strlen($normalized);

        if ($length < 8 || $length > 128) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $normalized);
    }

    /**
     * Extract meaningful information from a fingerprint value.
     *
     * Fingerprints are opaque identifiers with no extractable components,
     * so this method always returns null.
     *
     * @param mixed $value The fingerprint value
     */
    public function extract(mixed $value): ?string
    {
        return null;
    }
}
