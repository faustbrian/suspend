<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Database\Concerns;

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Enums\SuspensionStatus;
use Cline\Suspend\Events\SuspensionRevoked;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use function assert;
use function event;
use function is_int;
use function is_string;
use function now;

/**
 * Provides core suspension model behavior.
 *
 * This trait contains all the business logic for the Suspension model,
 * including relationships, scopes, and status determination.
 *
 * @property null|string               $context_type
 * @property null|int|string           $context_id
 * @property null|string               $match_type
 * @property null|string               $match_value
 * @property null|array<string, mixed> $match_metadata
 * @property null|string               $strategy
 * @property null|array<string, mixed> $strategy_metadata
 * @property null|string               $reason
 * @property DateTimeInterface         $suspended_at
 * @property null|DateTimeInterface    $expires_at
 * @property null|string               $suspended_by_type
 * @property null|int|string           $suspended_by_id
 * @property null|DateTimeInterface    $revoked_at
 * @property null|string               $revoked_by_type
 * @property null|int|string           $revoked_by_id
 * @property null|DateTimeInterface    $starts_at
 * @property DateTimeInterface         $created_at
 * @property DateTimeInterface         $updated_at
 *
 * @method static Builder<static> active()
 * @method static Builder<static> expired()
 * @method static Builder<static> revoked()
 * @method static Builder<static> pending()
 * @method static Builder<static> forContext(Model $context)
 * @method static Builder<static> forMatchType(string $type)
 * @method static Builder<static> forMatchValue(string $type, string $value)
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait IsSuspension
{
    /**
     * Get the context (entity) that is suspended.
     *
     * @return MorphTo<Model, $this>
     */
    public function context(): MorphTo
    {
        return $this->morphTo('context');
    }

    /**
     * Get the user/entity who created the suspension.
     *
     * @return MorphTo<Model, $this>
     */
    public function suspendedBy(): MorphTo
    {
        return $this->morphTo('suspended_by');
    }

    /**
     * Get the user/entity who revoked the suspension.
     *
     * @return MorphTo<Model, $this>
     */
    public function revokedBy(): MorphTo
    {
        return $this->morphTo('revoked_by');
    }

    /**
     * Determine the current status of the suspension.
     *
     * @return SuspensionStatus Current status: Active, Pending, Expired, or Revoked
     */
    public function status(): SuspensionStatus
    {
        if ($this->revoked_at !== null) {
            return SuspensionStatus::Revoked;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at > $now) {
            return SuspensionStatus::Pending;
        }

        if ($this->expires_at !== null && $this->expires_at < $now) {
            return SuspensionStatus::Expired;
        }

        return SuspensionStatus::Active;
    }

    /**
     * Check if the suspension is currently active.
     *
     * @return bool True if suspension is active (not revoked, started, and not expired)
     */
    public function isActive(): bool
    {
        return $this->status() === SuspensionStatus::Active;
    }

    /**
     * Check if the suspension has expired.
     *
     * @return bool True if suspension has expired (expires_at is in the past)
     */
    public function isExpired(): bool
    {
        return $this->status() === SuspensionStatus::Expired;
    }

    /**
     * Check if the suspension has been revoked.
     *
     * @return bool True if suspension has been manually revoked
     */
    public function isRevoked(): bool
    {
        return $this->status() === SuspensionStatus::Revoked;
    }

    /**
     * Check if the suspension is pending (scheduled for future).
     *
     * @return bool True if suspension is scheduled but not yet started
     */
    public function isPending(): bool
    {
        return $this->status() === SuspensionStatus::Pending;
    }

    /**
     * Check if this is a context-based (transient) suspension.
     *
     * @return bool True if this is a match-based suspension (email, IP, etc.)
     */
    public function isContextBased(): bool
    {
        return $this->match_type !== null;
    }

    /**
     * Check if this is an entity-based suspension.
     *
     * @return bool True if this suspension is attached to a model (User, Team, etc.)
     */
    public function isEntityBased(): bool
    {
        return $this->context_type !== null;
    }

    /**
     * Revoke the suspension.
     *
     * @param  null|Model  $revokedBy The user/entity revoking the suspension
     * @param  null|string $reason    Optional reason for revocation
     * @return bool        True if the revocation was saved successfully
     */
    public function revoke(?Model $revokedBy = null, ?string $reason = null): bool
    {
        $this->revoked_at = now();

        if ($revokedBy instanceof Model) {
            $this->revoked_by_type = $revokedBy->getMorphClass();
            $key = $revokedBy->getKey();
            assert(is_int($key) || is_string($key));
            $this->revoked_by_id = $key;
        }

        if ($reason !== null) {
            $this->reason = $reason;
        }

        $saved = $this->save();

        if ($saved) {
            event(
                new SuspensionRevoked($this, $reason),
            );
        }

        return $saved;
    }

    /**
     * Scope to active suspensions only.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     *
     * @phpstan-ignore return.type
     */
    protected function scopeActive(Builder $query): Builder
    {
        /** @var Builder<Suspension> */
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to expired suspensions only.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     *
     * @phpstan-ignore return.type
     */
    protected function scopeExpired(Builder $query): Builder
    {
        /** @var Builder<Suspension> */
        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope to revoked suspensions only.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     *
     * @phpstan-ignore return.type
     */
    protected function scopeRevoked(Builder $query): Builder
    {
        /** @var Builder<Suspension> */
        return $query->whereNotNull('revoked_at');
    }

    /**
     * Scope to pending suspensions only.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     *
     * @phpstan-ignore return.type
     */
    protected function scopePending(Builder $query): Builder
    {
        /** @var Builder<Suspension> */
        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    /**
     * Scope to suspensions for a specific context model.
     *
     * @param  Builder<static> $query   Query builder instance
     * @param  Model           $context The model to filter suspensions by
     * @return Builder<static>
     */
    protected function scopeForContext(Builder $query, Model $context): Builder
    {
        /** @var Builder<Suspension> */
        return $query
            ->where('context_type', $context->getMorphClass())
            ->where('context_id', $context->getKey());
    }

    /**
     * Scope to suspensions for a specific match type.
     *
     * @param  Builder<static> $query Query builder instance
     * @param  string          $type  Match type identifier (e.g., 'email', 'ip')
     * @return Builder<static>
     */
    protected function scopeForMatchType(Builder $query, string $type): Builder
    {
        return $query->where('match_type', $type);
    }

    /**
     * Scope to suspensions matching a specific type and value.
     *
     * @param  Builder<static> $query Query builder instance
     * @param  string          $type  Match type identifier
     * @param  string          $value Match value to filter by
     * @return Builder<static>
     */
    protected function scopeForMatchValue(Builder $query, string $type, string $value): Builder
    {
        /** @var Builder<Suspension> */
        return $query
            ->where('match_type', $type)
            ->where('match_value', $value);
    }
}
