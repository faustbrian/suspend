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
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function array_merge;
use function event;
use function now;

/**
 * Fluent conductor for entity-based suspensions.
 *
 * Usage:
 *   Suspend::for($user)->suspend('Spam');
 *   Suspend::for($user)->isSuspended();
 *   Suspend::for($user)->lift();
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SuspensionConductor
{
    /**
     * Strategy identifier to apply to new suspensions.
     */
    private ?string $strategy = null;

    /**
     * Strategy-specific metadata to attach to new suspensions.
     *
     * @var array<string, mixed>
     */
    private array $strategyMetadata = [];

    /**
     * Create a new suspension conductor.
     *
     * @param Model $context The model instance to suspend
     */
    public function __construct(
        private readonly Model $context,
    ) {}

    /**
     * Set the strategy to use for this suspension.
     *
     * @param  string               $strategy Strategy identifier
     * @param  array<string, mixed> $metadata Strategy-specific metadata
     * @return $this
     */
    public function using(string $strategy, array $metadata = []): self
    {
        $this->strategy = $strategy;
        $this->strategyMetadata = $metadata;

        return $this;
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
        // @phpstan-ignore-next-line - Eloquent scopes (forContext, active) are defined in IsSuspension trait
        return Suspension::query()
            ->forContext($this->context)
            ->active()
            ->get();
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
        $suspension = Suspension::query()->create([
            'context_type' => $this->context->getMorphClass(),
            'context_id' => $this->context->getKey(),
            'strategy' => $this->strategy,
            'strategy_metadata' => array_merge($this->strategyMetadata, $metadata) ?: null,
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
        $suspension = Suspension::query()->create([
            'context_type' => $this->context->getMorphClass(),
            'context_id' => $this->context->getKey(),
            'strategy' => $this->strategy,
            'strategy_metadata' => array_merge($this->strategyMetadata, $metadata) ?: null,
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

        if ($count > 0) {
            event(
                new SuspensionLifted($this->context, null, null, $count),
            );
        }

        return $count;
    }

    /**
     * Get all suspensions for this entity.
     *
     * @return Collection<int, Suspension>
     */
    public function all(): Collection
    {
        // @phpstan-ignore-next-line - Eloquent scope (forContext) is defined in IsSuspension trait
        return Suspension::query()
            ->forContext($this->context)
            ->orderByDesc('suspended_at')
            ->get();
    }

    /**
     * Get suspension history for this entity.
     *
     * @return Collection<int, Suspension>
     */
    public function history(): Collection
    {
        return $this->all();
    }
}
