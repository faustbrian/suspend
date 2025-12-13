<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Concerns;

use Cline\Suspend\Database\Models;
use Cline\Suspend\Database\Models\Suspension;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

use function now;

/**
 * Provides suspension capabilities to Eloquent models.
 *
 * Add this trait to any model that should be suspendable (User, Team, etc.).
 * Implements the Suspendable contract with default behavior.
 *
 * @phpstan-require-extends Model
 *
 * @mixin Model
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @phpstan-ignore-next-line
 */
trait HasSuspensions
{
    /**
     * Get all suspensions for this entity.
     *
     * @return MorphMany<Suspension, $this>
     */
    public function suspensions(): MorphMany
    {
        return $this->morphMany(
            Models::classname(Suspension::class),
            'context',
        );
    }

    /**
     * Check if the entity is currently suspended.
     *
     * @return bool True if at least one active suspension exists, false otherwise
     */
    public function isSuspended(): bool
    {
        return $this->activeSuspensions()->isNotEmpty();
    }

    /**
     * Check if the entity is not currently suspended.
     *
     * @return bool True if no active suspensions exist, false otherwise
     */
    public function isNotSuspended(): bool
    {
        return !$this->isSuspended();
    }

    /**
     * Get all active suspensions for this entity.
     *
     * @return Collection<int, Suspension>
     */
    public function activeSuspensions(): Collection
    {
        return $this->suspensions()->active()->get();
    }

    /**
     * Suspend this entity.
     *
     * @param  null|string            $reason    Optional reason for the suspension
     * @param  null|DateTimeInterface $expiresAt Optional expiration time
     * @param  array<string, mixed>   $metadata  Additional metadata to store
     * @return Suspension             The created suspension record
     */
    public function suspend(
        ?string $reason = null,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
    ): Suspension {
        return $this->suspensions()->create([
            'reason' => $reason,
            'suspended_at' => now(),
            'expires_at' => $expiresAt,
            'strategy_metadata' => $metadata ?: null,
        ]);
    }

    /**
     * Lift all active suspensions for this entity.
     *
     * @param  null|string $reason Optional reason for lifting
     * @return int         Number of suspensions lifted
     */
    public function lift(?string $reason = null): int
    {
        $count = 0;

        foreach ($this->activeSuspensions() as $suspension) {
            $suspension->revoke(null, $reason);
            ++$count;
        }

        return $count;
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
        return $this->suspensions()->create([
            'reason' => $reason,
            'suspended_at' => now(),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'strategy_metadata' => $metadata ?: null,
        ]);
    }

    /**
     * Suspend this entity for a specific duration.
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
        return $this->suspend(
            $reason,
            now()->add($duration),
            $metadata,
        );
    }

    /**
     * Get the suspension history for this entity.
     *
     * @return Collection<int, Suspension>
     */
    public function suspensionHistory(): Collection
    {
        return $this->suspensions()
            ->orderByDesc('suspended_at')
            ->get();
    }
}
