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
use function mb_ltrim;
use function mb_strlen;
use function mb_substr;
use function method_exists;
use function preg_match;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;

/**
 * Matcher for phone numbers.
 *
 * Normalizes phone numbers by removing formatting characters and
 * optionally adding country code prefix. Supports:
 * - Exact number matching
 * - Prefix matching (e.g., +1555* for area code)
 * - Various input formats: (555) 123-4567, 555.123.4567, etc.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class PhoneMatcher implements Matcher
{
    /**
     * Get the matcher type identifier.
     *
     * @return string The type identifier 'phone'
     */
    public function type(): string
    {
        return 'phone';
    }

    /**
     * Normalize a phone number into a consistent format.
     *
     * Removes all formatting characters (spaces, hyphens, parentheses, dots) and
     * retains only digits. Preserves a leading '+' for international format.
     * This allows matching phone numbers regardless of input formatting.
     *
     * @param  mixed  $value The phone number to normalize (various formats accepted)
     * @return string The normalized phone number with digits only (and optional leading +)
     */
    public function normalize(mixed $value): string
    {
        if (is_string($value)) {
            $phone = $value;
        } elseif (is_scalar($value)) {
            $phone = (string) $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $phone = (string) $value;
        } else {
            $phone = '';
        }

        $hasPlus = str_starts_with($phone, '+');

        $normalized = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($hasPlus) {
            return '+'.$normalized;
        }

        return $normalized;
    }

    /**
     * Check if a phone number matches the suspended value.
     *
     * Supports three matching strategies:
     * - Exact match: Full number comparison
     * - Prefix match: When suspended value ends with *, matches number prefix
     * - Flexible match: Compares numbers with/without + prefix to handle variations
     *
     * @param  string $suspendedValue The phone number or pattern stored in the suspension record
     * @param  mixed  $checkValue     The phone number to check (will be normalized)
     * @return bool   True if the phone numbers match or pattern matches, false otherwise
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        $normalizedCheck = $this->normalize($checkValue);
        $normalizedSuspended = $this->normalize($suspendedValue);

        if ($normalizedSuspended === $normalizedCheck) {
            return true;
        }

        if (str_ends_with($normalizedSuspended, '*')) {
            $prefix = mb_substr($normalizedSuspended, 0, -1);

            return str_starts_with($normalizedCheck, $prefix);
        }

        $checkWithoutPlus = mb_ltrim($normalizedCheck, '+');
        $suspendedWithoutPlus = mb_ltrim($normalizedSuspended, '+');

        return $suspendedWithoutPlus === $checkWithoutPlus;
    }

    /**
     * Validate that a value is an acceptable phone number format.
     *
     * Phone numbers must contain between 7 and 15 digits (excluding the + prefix).
     * This range accommodates most international phone number formats while
     * preventing invalid values.
     *
     * @param  mixed $value The value to validate as a phone number
     * @return bool  True if the value is a valid phone number length, false otherwise
     */
    public function validate(mixed $value): bool
    {
        $normalized = $this->normalize($value);

        $digitsOnly = mb_ltrim($normalized, '+');

        $length = mb_strlen($digitsOnly);

        return $length >= 7 && $length <= 15;
    }

    /**
     * Extract the country code from a phone number.
     *
     * Attempts to extract the country calling code from E.164 format numbers
     * (those starting with +). Returns the first 1-3 digits after the plus sign.
     * Returns null for numbers without international prefix.
     *
     * @param  mixed       $value The phone number value
     * @return null|string The country code (1-3 digits), or null if not in international format
     */
    public function extract(mixed $value): ?string
    {
        $normalized = $this->normalize($value);

        if (!str_starts_with($normalized, '+')) {
            return null;
        }

        if (preg_match('/^\+(\d{1,3})/', $normalized, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
