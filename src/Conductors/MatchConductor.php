<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Conductors;

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Events\SuspensionCreated;
use Cline\Suspend\Events\SuspensionLifted;
use Cline\Suspend\Exceptions\InvalidMatcherException;
use Cline\Suspend\Matchers\Contracts\Matcher;
use Cline\Suspend\SuspendManager;
use DateInterval;
use DateTimeInterface;

use function event;
use function now;

/**
 * Fluent conductor for context-based (transient) suspensions.
 *
 * Usage:
 *   Suspend::match('email', 'spam@example.com')->suspend('Fraudulent');
 *   Suspend::match('ip', '192.168.1.0/24')->suspend('Abuse');
 *   Suspend::match('phone', '+15551234567')->lift();
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class MatchConductor
{
    /**
     * Create a new match conductor.
     *
     * @param SuspendManager $manager Suspension manager for accessing matchers
     * @param string         $type    Match type identifier (e.g., 'email', 'ip')
     * @param string         $value   Value to match against
     */
    public function __construct(
        private SuspendManager $manager,
        private string $type,
        private string $value,
    ) {}

    /**
     * Create a suspension for this match.
     *
     * @param null|string            $reason    Optional reason for the suspension
     * @param null|DateTimeInterface $expiresAt Optional expiration time
     * @param array<string, mixed>   $metadata  Additional metadata to store
     *
     * @throws InvalidMatcherException If the matcher for this type is invalid
     *
     * @return Suspension The created suspension record
     */
    public function suspend(
        ?string $reason = null,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
    ): Suspension {
        $normalizedValue = $this->normalizeValue();

        $suspension = Suspension::query()->create([
            'match_type' => $this->type,
            'match_value' => $normalizedValue,
            'match_metadata' => $metadata ?: null,
            'reason' => $reason,
            'suspended_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        event(
            new SuspensionCreated($suspension),
        );

        return $suspension;
    }

    /**
     * Schedule a suspension for the future.
     *
     * @param  DateTimeInterface      $startsAt  When the suspension should begin
     * @param  null|string            $reason    Optional reason for the suspension
     * @param  null|DateTimeInterface $expiresAt Optional expiration time
     * @param  array<string, mixed>   $metadata  Additional metadata to store
     * @return Suspension             The created suspension record
     */
    public function suspendAt(
        DateTimeInterface $startsAt,
        ?string $reason = null,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
    ): Suspension {
        $normalizedValue = $this->normalizeValue();

        $suspension = Suspension::query()->create([
            'match_type' => $this->type,
            'match_value' => $normalizedValue,
            'match_metadata' => $metadata ?: null,
            'reason' => $reason,
            'suspended_at' => now(),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);

        event(
            new SuspensionCreated($suspension),
        );

        return $suspension;
    }

    /**
     * Suspend for a specific duration.
     *
     * @param  DateInterval         $duration Duration of the suspension
     * @param  null|string          $reason   Optional reason for the suspension
     * @param  array<string, mixed> $metadata Additional metadata to store
     * @return Suspension           The created suspension record
     */
    public function suspendFor(
        DateInterval $duration,
        ?string $reason = null,
        array $metadata = [],
    ): Suspension {
        return $this->suspend($reason, now()->add($duration), $metadata);
    }

    /**
     * Lift all active suspensions for this match.
     *
     * @param  null|string $reason Optional reason for lifting
     * @return int         Number of suspensions lifted
     */
    public function lift(?string $reason = null): int
    {
        $normalizedValue = $this->normalizeValue();
        $count = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<int, Suspension> $suspensions */
        /** @phpstan-ignore-next-line method.notFound, method.nonObject */
        $suspensions = Suspension::query()
            ->forMatchValue($this->type, $normalizedValue)
            ->active()
            ->get();

        foreach ($suspensions as $suspension) {
            $suspension->revoke(null, $reason);
            ++$count;
        }

        if ($count > 0) {
            event(
                new SuspensionLifted(null, $this->type, $normalizedValue, $count),
            );
        }

        return $count;
    }

    /**
     * Check if this match has any active suspensions.
     *
     * @return bool True if active suspension exists for this match, false otherwise
     */
    public function isSuspended(): bool
    {
        $normalizedValue = $this->normalizeValue();

        /** @phpstan-ignore-next-line method.notFound, method.nonObject, return.type */
        return Suspension::query()
            ->forMatchValue($this->type, $normalizedValue)
            ->active()
            ->exists();
    }

    /**
     * Normalize the value using the appropriate matcher.
     *
     * @throws InvalidMatcherException If the matcher validation fails
     *
     * @return string Normalized value ready for database storage and comparison
     */
    private function normalizeValue(): string
    {
        $matcher = $this->manager->getMatcher($this->type);

        if (!$matcher instanceof Matcher) {
            // No matcher registered, use value as-is
            return $this->value;
        }

        return $matcher->normalize($this->value);
    }
}
