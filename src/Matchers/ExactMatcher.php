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
use function mb_trim;
use function method_exists;

/**
 * Simple exact string matcher for custom identifiers.
 *
 * Performs case-sensitive exact string matching with whitespace trimming.
 * This is the most basic matcher implementation, suitable for unique
 * identifiers that don't require special normalization or pattern matching.
 *
 * Use cases:
 * - Order IDs or transaction numbers
 * - Account numbers or customer IDs
 * - API keys or tokens
 * - Custom business identifiers
 * - Any string that requires exact matching
 *
 * The matcher preserves case sensitivity, so 'ABC123' and 'abc123' are
 * treated as different values. Only leading and trailing whitespace is
 * removed during normalization.
 *
 * ```php
 * $matcher = new ExactMatcher();
 * $matcher->matches('ORDER-123', 'ORDER-123'); // true
 * $matcher->matches('ORDER-123', 'order-123'); // false (case-sensitive)
 * $matcher->matches('ABC', ' ABC '); // true (whitespace trimmed)
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ExactMatcher implements Matcher
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'exact';
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        return $this->normalize($suspendedValue) === $this->normalize($checkValue);
    }

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value): bool
    {
        return $this->normalize($value) !== '';
    }

    /**
     * {@inheritDoc}
     *
     * Returns null for exact values (no meaningful extraction).
     */
    public function extract(mixed $value): ?string
    {
        return null;
    }
}
