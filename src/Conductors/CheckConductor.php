<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Conductors;

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Matchers\Contracts\Matcher;
use Cline\Suspend\SuspendManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use function array_keys;

/**
 * Fluent conductor for checking multiple context values at once.
 *
 * Usage:
 *   Suspend::check()
 *       ->email($order->email)
 *       ->phone($order->phone)
 *       ->ip($request->ip())
 *       ->matches();
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CheckConductor
{
    /**
     * Values to check by type.
     *
     * @var array<string, list<string>>
     */
    private array $checks = [];

    /**
     * Cached suspensions grouped by type for query optimization.
     *
     * Stores suspensions keyed by match_type to avoid repeated database
     * queries when checking multiple values of the same type.
     *
     * @var null|Collection<string, Collection<int, Suspension>>
     */
    private ?Collection $suspensionCache = null; // @phpstan-ignore generics.notSubtype

    /**
     * Create a new check conductor.
     *
     * @param SuspendManager $manager Suspension manager for accessing matchers and configuration
     */
    public function __construct(
        private readonly SuspendManager $manager,
    ) {}

    /**
     * Add an email to check.
     *
     * @param  string $email Email address to check against suspensions
     * @return $this
     */
    public function email(string $email): self
    {
        return $this->type('email', $email);
    }

    /**
     * Add a phone number to check.
     *
     * @param  string $phone Phone number to check against suspensions
     * @return $this
     */
    public function phone(string $phone): self
    {
        return $this->type('phone', $phone);
    }

    /**
     * Add an IP address to check.
     *
     * @param  string $ip IP address (supports CIDR notation for range matching)
     * @return $this
     */
    public function ip(string $ip): self
    {
        return $this->type('ip', $ip);
    }

    /**
     * Add a domain to check.
     *
     * @param  string $domain Domain name to check against suspensions
     * @return $this
     */
    public function domain(string $domain): self
    {
        return $this->type('domain', $domain);
    }

    /**
     * Add a country code to check.
     *
     * @param  string $country ISO country code to check against suspensions
     * @return $this
     */
    public function country(string $country): self
    {
        return $this->type('country', $country);
    }

    /**
     * Add a fingerprint to check.
     *
     * @param  string $fingerprint Browser or device fingerprint to check against suspensions
     * @return $this
     */
    public function fingerprint(string $fingerprint): self
    {
        return $this->type('fingerprint', $fingerprint);
    }

    /**
     * Add a generic type check.
     *
     * @param  string $type  Match type identifier (e.g., 'email', 'ip', 'phone')
     * @param  string $value Value to check against suspensions
     * @return $this
     */
    public function type(string $type, string $value): self
    {
        $this->checks[$type][] = $value;
        $this->suspensionCache = null; // Invalidate cache

        return $this;
    }

    /**
     * Check if any of the values match a suspension.
     *
     * @return bool True if any value matches an active suspension, false otherwise
     */
    public function matches(): bool
    {
        return $this->first() instanceof Suspension;
    }

    /**
     * Get the first matching suspension.
     *
     * @return null|Suspension First matching active suspension or null if none found
     */
    public function first(): ?Suspension
    {
        if ($this->checks === []) {
            return null;
        }

        $suspensionsByType = $this->loadSuspensions();

        foreach ($this->checks as $type => $values) {
            $matcher = $this->manager->getMatcher($type);

            /** @var Collection<int, Suspension> $suspensions */
            $suspensions = $suspensionsByType->get($type, new Collection());

            foreach ($values as $value) {
                $normalizedValue = $matcher?->normalize($value) ?? $value;

                foreach ($suspensions as $suspension) {
                    if ($matcher instanceof Matcher && $suspension->match_value !== null) {
                        // Use matcher for pattern matching (CIDR, wildcards, etc.)
                        if ($matcher->matches($suspension->match_value, $normalizedValue)) {
                            return $suspension;
                        }
                    } elseif ($suspension->match_value === $normalizedValue) {
                        // Exact match fallback
                        return $suspension;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get all matching suspensions.
     *
     * @return Collection<int, Suspension>
     */
    public function all(): Collection
    {
        if ($this->checks === []) {
            return new Collection();
        }

        $results = new Collection();
        $suspensionsByType = $this->loadSuspensions();

        foreach ($this->checks as $type => $values) {
            $matcher = $this->manager->getMatcher($type);

            /** @var Collection<int, Suspension> $suspensions */
            $suspensions = $suspensionsByType->get($type, new Collection());

            foreach ($values as $value) {
                $normalizedValue = $matcher?->normalize($value) ?? $value;

                foreach ($suspensions as $suspension) {
                    if ($matcher instanceof Matcher && $suspension->match_value !== null) {
                        if ($matcher->matches($suspension->match_value, $normalizedValue)) {
                            $results->push($suspension);
                        }
                    } elseif ($suspension->match_value === $normalizedValue) {
                        $results->push($suspension);
                    }
                }
            }
        }

        return $results->unique('id');
    }

    /**
     * Get the types and values being checked.
     *
     * @return array<string, list<string>>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * Load all active suspensions for the check types in a single query.
     *
     * Uses query optimization by fetching all suspensions for registered types
     * in a single database query, then grouping by match_type for efficient lookup.
     *
     * @return Collection<string, Collection<int, Suspension>> Suspensions grouped by match type
     */
    private function loadSuspensions(): Collection // @phpstan-ignore generics.notSubtype
    {
        if ($this->suspensionCache instanceof Collection) {
            return $this->suspensionCache;
        }

        $types = array_keys($this->checks);

        if ($types === []) {
            $this->suspensionCache = new Collection();

            return $this->suspensionCache;
        }

        // Single query for all match types
        /** @var Builder<Suspension> $query */
        $query = Suspension::query();

        /** @phpstan-ignore-next-line */
        $suspensions = $query
            ->whereIn('match_type', $types)
            ->active()
            ->get()
            ->groupBy('match_type');

        /** @phpstan-ignore assign.propertyType */
        $this->suspensionCache = $suspensions;

        /** @phpstan-ignore return.type */
        return $suspensions;
    }
}
