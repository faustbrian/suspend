<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Geo\Cdn;

use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Support\Coordinates;
use Illuminate\Http\Request;

use function is_string;
use function urldecode;

/**
 * Geo resolver using Vercel Edge headers.
 *
 * Vercel provides geo information via headers for Edge deployments.
 *
 * Available headers:
 * - x-vercel-ip-country: Two-letter country code
 * - x-vercel-ip-country-region: Region/state code
 * - x-vercel-ip-city: City name (URL-encoded)
 * - x-vercel-ip-latitude/longitude: Coordinates
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://vercel.com/docs/edge-network/headers
 *
 * @psalm-immutable
 */
final readonly class VercelGeoResolver implements GeoResolver
{
    /**
     * Create a new Vercel geo resolver.
     *
     * @param Request $request The current HTTP request containing Vercel Edge Network
     *                         geolocation headers. These headers are automatically
     *                         added by Vercel's edge infrastructure for all requests
     *                         routed through their CDN.
     */
    public function __construct(
        private Request $request,
    ) {}

    /**
     * Resolves the country code from Vercel geo headers.
     *
     * Reads the x-vercel-ip-country header which contains the two-letter
     * ISO 3166-1 alpha-2 country code determined by Vercel's edge network.
     *
     * @param  string      $ip The IP address to resolve (unused, as Vercel provides geo data via headers)
     * @return null|string Two-letter ISO country code (e.g., 'US', 'DE') or null if unavailable
     */
    public function country(string $ip): ?string
    {
        $country = $this->request->header('x-vercel-ip-country');

        if (!is_string($country) || $country === '') {
            return null;
        }

        return $country;
    }

    /**
     * Resolves the region/state code from Vercel geo headers.
     *
     * Reads the x-vercel-ip-country-region header which contains the
     * region or state code for the request origin.
     *
     * @param  string      $ip The IP address to resolve (unused, as Vercel provides geo data via headers)
     * @return null|string Region or state code (e.g., 'CA', 'TX') or null if unavailable
     */
    public function region(string $ip): ?string
    {
        $region = $this->request->header('x-vercel-ip-country-region');

        if (!is_string($region) || $region === '') {
            return null;
        }

        return $region;
    }

    /**
     * Resolves the city from Vercel geo headers.
     *
     * Reads the x-vercel-ip-city header and decodes it, as Vercel URL-encodes
     * city names to handle special characters and spaces in city names.
     *
     * @param  string      $ip The IP address to resolve (unused, as Vercel provides geo data via headers)
     * @return null|string Decoded city name (e.g., 'San Francisco', 'São Paulo') or null if unavailable
     */
    public function city(string $ip): ?string
    {
        $city = $this->request->header('x-vercel-ip-city');

        if (!is_string($city) || $city === '') {
            return null;
        }

        // Vercel URL-encodes city names
        return urldecode($city);
    }

    /**
     * Resolves geographic coordinates from Vercel geo headers.
     *
     * Extracts latitude and longitude from x-vercel-ip-latitude and
     * x-vercel-ip-longitude headers. Returns null if either coordinate
     * is missing or invalid.
     *
     * @param  string           $ip The IP address to resolve (unused, as Vercel provides geo data via headers)
     * @return null|Coordinates Geographic coordinates object containing latitude and longitude, or null if unavailable
     */
    public function coordinates(string $ip): ?Coordinates
    {
        $lat = $this->request->header('x-vercel-ip-latitude');
        $lon = $this->request->header('x-vercel-ip-longitude');

        if (!is_string($lat) || !is_string($lon) || $lat === '' || $lon === '') {
            return null;
        }

        return new Coordinates((float) $lat, (float) $lon);
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'vercel'
     */
    public function identifier(): string
    {
        return 'vercel';
    }
}
