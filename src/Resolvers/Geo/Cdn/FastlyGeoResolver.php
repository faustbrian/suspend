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

/**
 * Geo resolver using Fastly geo headers.
 *
 * Fastly provides geo information via custom headers that must be
 * configured in your VCL. Common header names used:
 *
 * - Fastly-Geo-Country-Code: Two-letter country code
 * - Fastly-Geo-Region: Region/state name
 * - Fastly-Geo-City: City name
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://developer.fastly.com/reference/vcl/variables/geolocation/
 *
 * @psalm-immutable
 */
final readonly class FastlyGeoResolver implements GeoResolver
{
    /**
     * Create a new Fastly geo resolver.
     *
     * @param Request $request The current HTTP request containing Fastly geo headers
     *                         set by your Fastly VCL configuration. Fastly provides
     *                         geolocation information through custom headers that must
     *                         be enabled in your edge configuration.
     */
    public function __construct(
        private Request $request,
    ) {}

    /**
     * Resolves the country code from Fastly geo headers.
     *
     * Checks Fastly-Geo-Country-Code first, falling back to X-Geo-Country
     * for compatibility with older Fastly configurations.
     *
     * @param  string      $ip The IP address to resolve (unused, as Fastly provides geo data via headers)
     * @return null|string Two-letter ISO country code (e.g., 'US', 'GB') or null if unavailable
     */
    public function country(string $ip): ?string
    {
        $country = $this->request->header('Fastly-Geo-Country-Code')
            ?? $this->request->header('X-Geo-Country');

        if (!is_string($country) || $country === '') {
            return null;
        }

        return $country;
    }

    /**
     * Resolves the region/state from Fastly geo headers.
     *
     * Checks Fastly-Geo-Region first, falling back to X-Geo-Region
     * for compatibility with older Fastly configurations.
     *
     * @param  string      $ip The IP address to resolve (unused, as Fastly provides geo data via headers)
     * @return null|string Region or state name (e.g., 'California', 'England') or null if unavailable
     */
    public function region(string $ip): ?string
    {
        $region = $this->request->header('Fastly-Geo-Region')
            ?? $this->request->header('X-Geo-Region');

        if (!is_string($region) || $region === '') {
            return null;
        }

        return $region;
    }

    /**
     * Resolves the city from Fastly geo headers.
     *
     * Checks Fastly-Geo-City first, falling back to X-Geo-City
     * for compatibility with older Fastly configurations.
     *
     * @param  string      $ip The IP address to resolve (unused, as Fastly provides geo data via headers)
     * @return null|string City name (e.g., 'San Francisco', 'London') or null if unavailable
     */
    public function city(string $ip): ?string
    {
        $city = $this->request->header('Fastly-Geo-City')
            ?? $this->request->header('X-Geo-City');

        if (!is_string($city) || $city === '') {
            return null;
        }

        return $city;
    }

    /**
     * Resolves geographic coordinates from Fastly geo headers.
     *
     * Extracts latitude and longitude from Fastly headers, falling back to
     * X-Geo-Latitude and X-Geo-Longitude for older configurations. Returns
     * null if either coordinate is missing or invalid.
     *
     * @param  string           $ip The IP address to resolve (unused, as Fastly provides geo data via headers)
     * @return null|Coordinates Geographic coordinates object containing latitude and longitude, or null if unavailable
     */
    public function coordinates(string $ip): ?Coordinates
    {
        $lat = $this->request->header('Fastly-Geo-Latitude')
            ?? $this->request->header('X-Geo-Latitude');
        $lon = $this->request->header('Fastly-Geo-Longitude')
            ?? $this->request->header('X-Geo-Longitude');

        if (!is_string($lat) || !is_string($lon) || $lat === '' || $lon === '') {
            return null;
        }

        return new Coordinates((float) $lat, (float) $lon);
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'fastly'
     */
    public function identifier(): string
    {
        return 'fastly';
    }
}
