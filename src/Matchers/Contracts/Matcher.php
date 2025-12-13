<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers\Contracts;

/**
 * Contract for context-based suspension matching.
 *
 * Matchers handle specific types of transient/contextual data (email, phone,
 * IP, etc.) for suspension checks without requiring a database entity. They
 * provide normalization, validation, and pattern matching capabilities.
 *
 * This enables scenarios like:
 * - Blocking orders from specific email addresses
 * - Blocking shipments to certain phone numbers
 * - Blocking requests from IP ranges
 * - Blocking users from specific domains
 *
 * Each matcher is responsible for its own type of data and can support
 * patterns (wildcards, CIDR ranges) beyond simple equality matching.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Matcher
{
    /**
     * Get the context type this matcher handles.
     *
     * Returns a unique identifier for the type of data this matcher
     * processes. Used for routing suspension checks to the correct
     * matcher and storing/retrieving suspension records.
     *
     * @return string The type identifier (e.g., 'email', 'phone', 'ip')
     */
    public function type(): string;

    /**
     * Normalize a value for storage and comparison.
     *
     * Transforms input values into a canonical format for consistent
     * matching. For example:
     * - Emails: lowercase, trim whitespace
     * - Phones: E.164 format (+15551234567)
     * - IPs: Expand IPv6, normalize IPv4
     *
     * @param  mixed  $value The raw input value
     * @return string The normalized value for storage
     */
    public function normalize(mixed $value): string;

    /**
     * Check if a value matches a suspended pattern/value.
     *
     * Performs the actual matching logic between a stored suspension
     * value and a value being checked. Supports pattern matching
     * where applicable (wildcards, CIDR ranges, etc.).
     *
     * @param  string $suspendedValue The value/pattern from the suspension record
     * @param  mixed  $checkValue     The value being checked against
     * @return bool   True if the values match, false otherwise
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool;

    /**
     * Validate that a value is acceptable for this matcher.
     *
     * Ensures the input value is in a valid format before storing
     * or matching. Implementations should check format, not existence.
     *
     * @param  mixed $value The value to validate
     * @return bool  True if valid, false otherwise
     */
    public function validate(mixed $value): bool;

    /**
     * Extract the matchable portion from a value.
     *
     * For matchers that support partial matching (e.g., email domain),
     * this extracts the relevant portion. Returns null if extraction
     * is not applicable or fails.
     *
     * @param  mixed       $value The value to extract from
     * @return null|string The extracted portion, or null
     */
    public function extract(mixed $value): ?string;
}
