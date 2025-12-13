<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers;

use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Support\Coordinates;

/**
 * Chain resolver that tries multiple resolvers in order.
 *
 * Returns the first non-null result from the chain. Useful for
 * setting up fallback chains (e.g., CDN headers → API → local database).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ChainResolver implements GeoResolver
{
    /**
     * Create a new chain resolver.
     *
     * @param list<GeoResolver> $resolvers Ordered list of GeoResolvers to try sequentially.
     *                                     The first resolver to return a non-null result wins.
     *                                     Typically ordered from fastest/cheapest to slowest/most expensive
     *                                     (e.g., CDN headers → local database → external API).
     */
    public function __construct(
        private array $resolvers,
    ) {}

    /**
     * Resolve the country code for an IP address.
     *
     * Tries each resolver in sequence until one returns a non-null result.
     * Returns the first successful result or null if all resolvers fail.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The two-letter country code, or null if unavailable from all resolvers
     */
    public function country(string $ip): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->country($ip);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Resolve the region/state for an IP address.
     *
     * Tries each resolver in sequence until one returns a non-null result.
     * Returns the first successful result or null if all resolvers fail.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The region name, or null if unavailable from all resolvers
     */
    public function region(string $ip): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->region($ip);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Resolve the city for an IP address.
     *
     * Tries each resolver in sequence until one returns a non-null result.
     * Returns the first successful result or null if all resolvers fail.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The city name, or null if unavailable from all resolvers
     */
    public function city(string $ip): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->city($ip);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Resolve geographic coordinates for an IP address.
     *
     * Tries each resolver in sequence until one returns a non-null result.
     * Returns the first successful result or null if all resolvers fail.
     *
     * @param  string           $ip The IP address to look up
     * @return null|Coordinates The coordinates, or null if unavailable from all resolvers
     */
    public function coordinates(string $ip): ?Coordinates
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->coordinates($ip);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Get the resolver identifier.
     *
     * @return string The identifier 'chain'
     */
    public function identifier(): string
    {
        return 'chain';
    }
}
