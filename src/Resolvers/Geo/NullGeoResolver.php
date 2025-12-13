<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Geo;

use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Support\Coordinates;

/**
 * Null geo resolver that returns null for all lookups.
 *
 * Useful as a fallback or for testing when geo lookup is not needed.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class NullGeoResolver implements GeoResolver
{
    /**
     * Returns null for country lookups.
     *
     * This resolver intentionally provides no geolocation data, making it
     * useful as a no-op implementation when geo features are disabled.
     *
     * @param string $ip The IP address to resolve (ignored)
     */
    public function country(string $ip): ?string
    {
        return null;
    }

    /**
     * Returns null for region lookups.
     *
     * This resolver intentionally provides no geolocation data, making it
     * useful as a no-op implementation when geo features are disabled.
     *
     * @param string $ip The IP address to resolve (ignored)
     */
    public function region(string $ip): ?string
    {
        return null;
    }

    /**
     * Returns null for city lookups.
     *
     * This resolver intentionally provides no geolocation data, making it
     * useful as a no-op implementation when geo features are disabled.
     *
     * @param string $ip The IP address to resolve (ignored)
     */
    public function city(string $ip): ?string
    {
        return null;
    }

    /**
     * Returns null for coordinate lookups.
     *
     * This resolver intentionally provides no geolocation data, making it
     * useful as a no-op implementation when geo features are disabled.
     *
     * @param string $ip The IP address to resolve (ignored)
     */
    public function coordinates(string $ip): ?Coordinates
    {
        return null;
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'null'
     */
    public function identifier(): string
    {
        return 'null';
    }
}
