<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend;

use Cline\Suspend\Conductors\CheckConductor;
use Cline\Suspend\Conductors\MatchConductor;
use Cline\Suspend\Conductors\SuspensionConductor;
use Cline\Suspend\Contracts\Strategy;
use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Events\SuspensionCreated;
use Cline\Suspend\Events\SuspensionLifted;
use Cline\Suspend\Matchers\Contracts\Matcher;
use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function event;
use function now;

/**
 * Main manager class for the Suspend package.
 *
 * Provides the entry point for creating and checking suspensions through
 * a fluent conductor-based API. Manages the registration and retrieval
 * of matchers (for value comparison), strategies (for request evaluation),
 * and resolvers (for extracting data from requests).
 *
 * ```php
 * // Suspend a user
 * suspend()->for($user)->suspend('Violated ToS');
 *
 * // Suspend by email pattern
 * suspend()->match('email', 'spam@*.com')->suspend();
 *
 * // Check if suspended
 * $suspension = suspend()->check()->context($user)->first();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SuspendManager
{
    /**
     * Registered matchers by type.
     *
     * Maps matcher type identifiers (e.g., 'email', 'ip') to their
     * respective matcher implementations for value comparison logic.
     *
     * @var array<string, Matcher>
     */
    private array $matchers = [];

    /**
     * Registered strategies by identifier.
     *
     * Maps strategy identifiers (e.g., 'simple', 'country', 'ip_address')
     * to their respective strategy implementations for request evaluation.
     *
     * @var array<string, Strategy>
     */
    private array $strategies = [];

    /**
     * Create a new suspend manager.
     *
     * @param IpResolver  $ipResolver  Resolver for extracting client IP addresses from requests
     * @param GeoResolver $geoResolver Resolver for performing geolocation lookups from IP addresses
     */
    public function __construct(
        private readonly IpResolver $ipResolver,
        private readonly GeoResolver $geoResolver,
    ) {}

    /**
     * Start a suspension flow for an entity.
     *
     * Creates a conductor for suspending a specific Eloquent model instance
     * (e.g., User, Account). This is the most common entry point for creating
     * entity-based suspensions.
     *
     * @param  Model               $context The Eloquent model instance to suspend
     * @return SuspensionConductor Fluent interface for configuring and creating the suspension
     */
    public function for(Model $context): SuspensionConductor
    {
        return new SuspensionConductor($context);
    }

    /**
     * Start a context-based suspension flow.
     *
     * Creates a conductor for suspending a value pattern using a specific
     * matcher (e.g., email wildcard, IP CIDR range). Useful for suspending
     * patterns rather than specific entities.
     *
     * @param  string         $type  Matcher type identifier (e.g., 'email', 'phone', 'ip', 'domain')
     * @param  string         $value Value or pattern to suspend (supports wildcards and CIDR notation)
     * @return MatchConductor Fluent interface for configuring and creating the pattern-based suspension
     */
    public function match(string $type, string $value): MatchConductor
    {
        return new MatchConductor($this, $type, $value);
    }

    /**
     * Start a suspension check flow.
     *
     * Creates a conductor for querying existing suspensions. Use this to check
     * if an entity or context is suspended, retrieve active suspensions, or
     * filter suspensions by various criteria.
     *
     * @return CheckConductor Fluent interface for querying suspensions
     */
    public function check(): CheckConductor
    {
        return new CheckConductor($this);
    }

    /**
     * Register a matcher.
     *
     * Adds a matcher implementation to the manager's registry, making it
     * available for pattern-based suspensions via the match() method.
     *
     * @param  Matcher $matcher Matcher instance to register
     * @return $this   Manager instance for method chaining
     */
    public function registerMatcher(Matcher $matcher): self
    {
        $this->matchers[$matcher->type()] = $matcher;

        return $this;
    }

    /**
     * Get a registered matcher by type.
     *
     * Retrieves a matcher implementation by its type identifier.
     *
     * @param  string       $type Matcher type identifier (e.g., 'email', 'phone', 'ip')
     * @return null|Matcher Matcher instance if found, null otherwise
     */
    public function getMatcher(string $type): ?Matcher
    {
        return $this->matchers[$type] ?? null;
    }

    /**
     * Get all registered matchers.
     *
     * Returns the complete registry of matcher implementations indexed by type.
     *
     * @return array<string, Matcher> Array of matcher instances keyed by their type identifiers
     */
    public function getMatchers(): array
    {
        return $this->matchers;
    }

    /**
     * Register a strategy.
     *
     * Adds a strategy implementation to the manager's registry, making it
     * available for request-based suspension evaluation.
     *
     * @param  Strategy $strategy Strategy instance to register
     * @return $this    Manager instance for method chaining
     */
    public function registerStrategy(Strategy $strategy): self
    {
        $this->strategies[$strategy->identifier()] = $strategy;

        return $this;
    }

    /**
     * Get a registered strategy by identifier.
     *
     * Retrieves a strategy implementation by its unique identifier.
     *
     * @param  string        $identifier Strategy identifier (e.g., 'simple', 'country', 'ip_address')
     * @return null|Strategy Strategy instance if found, null otherwise
     */
    public function getStrategy(string $identifier): ?Strategy
    {
        return $this->strategies[$identifier] ?? null;
    }

    /**
     * Get all registered strategies.
     *
     * Returns the complete registry of strategy implementations indexed by identifier.
     *
     * @return array<string, Strategy> Array of strategy instances keyed by their identifiers
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Get the IP resolver.
     *
     * Returns the configured resolver for extracting client IP addresses from requests.
     *
     * @return IpResolver The active IP resolver instance
     */
    public function getIpResolver(): IpResolver
    {
        return $this->ipResolver;
    }

    /**
     * Get the geo resolver.
     *
     * Returns the configured resolver for performing geolocation lookups.
     *
     * @return GeoResolver The active geolocation resolver instance
     */
    public function getGeoResolver(): GeoResolver
    {
        return $this->geoResolver;
    }

    /**
     * Suspend multiple entities at once.
     *
     * Efficiently creates suspension records for multiple Eloquent models
     * in a single operation. Dispatches SuspensionCreated events for each
     * suspension created. Useful for bulk suspension operations.
     *
     * @param  iterable<Model>             $contexts  Iterable collection of Eloquent models to suspend
     * @param  null|string                 $reason    Optional human-readable reason for the suspension
     * @param  null|DateTimeInterface      $expiresAt Optional expiration date/time for automatic suspension lift
     * @param  array<string, mixed>        $metadata  Additional strategy-specific metadata for the suspension
     * @return Collection<int, Suspension> Collection of created suspension records
     */
    public function suspendMany(
        iterable $contexts,
        ?string $reason = null,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
    ): Collection {
        $suspensions = new Collection();

        foreach ($contexts as $context) {
            $suspension = Suspension::query()->create([
                'context_type' => $context->getMorphClass(),
                'context_id' => $context->getKey(),
                'strategy_metadata' => $metadata ?: null,
                'reason' => $reason,
                'suspended_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            event(
                new SuspensionCreated($suspension),
            );
            $suspensions->push($suspension);
        }

        return $suspensions;
    }

    /**
     * Revoke all active suspensions for multiple entities.
     *
     * Finds and revokes all active suspensions for the provided entities in
     * a single operation. Dispatches SuspensionLifted events for each entity
     * that had suspensions revoked. Useful for bulk unsuspension operations.
     *
     * @param  iterable<Model> $contexts Iterable collection of Eloquent models to unsuspend
     * @param  null|string     $reason   Optional human-readable reason for revocation
     * @return int             Total number of suspension records revoked across all entities
     */
    public function revokeMany(iterable $contexts, ?string $reason = null): int
    {
        $totalCount = 0;

        foreach ($contexts as $context) {
            /** @var Collection<int, Suspension> $suspensions */
            // @phpstan-ignore-next-line method.notFound, method.nonObject (Eloquent query scopes)
            $suspensions = Suspension::query()->forContext($context)->active()->get();

            $count = 0;

            foreach ($suspensions as $suspension) {
                $suspension->revoke(null, $reason);
                ++$count;
            }

            if ($count <= 0) {
                continue;
            }

            event(
                new SuspensionLifted($context, null, null, $count),
            );
            $totalCount += $count;
        }

        return $totalCount;
    }
}
