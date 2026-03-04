<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Geo\Local;

use Cline\Suspend\Exceptions\MissingDependencyException;
use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Support\Coordinates;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;

use function class_exists;
use function is_float;
use function is_string;

/**
 * Geo resolver using MaxMind GeoIP2/GeoLite2 local database.
 *
 * Requires the geoip2/geoip2 package and a .mmdb database file:
 * - GeoLite2-City.mmdb (free, requires registration)
 * - GeoIP2-City.mmdb (paid, more accurate)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://dev.maxmind.com/geoip/geoip2/geolite2/
 */
final class MaxMindLocalGeoResolver implements GeoResolver
{
    /**
     * The MaxMind database reader instance.
     *
     * Lazily initialized on first use to avoid opening the database file
     * until geolocation data is actually needed.
     */
    private ?Reader $reader = null;

    /**
     * Create a new MaxMind local geo resolver.
     *
     * @param string $databasePath Absolute path to the MaxMind .mmdb database file
     *                             (e.g., '/path/to/GeoLite2-City.mmdb'). The file
     *                             must be readable and in a valid MaxMind DB format.
     *                             Download GeoLite2 databases from MaxMind's website
     *                             after registration.
     *
     * @throws MissingDependencyException If geoip2/geoip2 package is not installed
     */
    public function __construct(
        private readonly string $databasePath,
    ) {
        if (!class_exists(Reader::class)) {
            throw MissingDependencyException::package('geoip2/geoip2', 'MaxMind local geo resolver');
        }
    }

    /**
     * Closes the database reader connection when the object is destroyed.
     *
     * Ensures proper cleanup of file handles and resources when the
     * resolver is no longer needed.
     */
    public function __destruct()
    {
        $this->reader?->close();
    }

    /**
     * Resolves the country code by querying the MaxMind database.
     *
     * Performs a city-level lookup in the database and extracts the
     * ISO 3166-1 alpha-2 country code from the response.
     *
     * @param  string      $ip The IP address to resolve (IPv4 or IPv6)
     * @return null|string Two-letter ISO country code (e.g., 'US', 'CA') or null if lookup fails
     */
    public function country(string $ip): ?string
    {
        try {
            $record = $this->getReader()->city($ip);

            $isoCode = $record->country->isoCode;

            return is_string($isoCode) ? $isoCode : null;
        } catch (AddressNotFoundException|InvalidDatabaseException) {
            return null;
        }
    }

    /**
     * Resolves the region/state by querying the MaxMind database.
     *
     * Performs a city-level lookup and extracts the most specific
     * subdivision (typically state or province) name from the response.
     *
     * @param  string      $ip The IP address to resolve (IPv4 or IPv6)
     * @return null|string Region or state name (e.g., 'California', 'Ontario') or null if lookup fails
     */
    public function region(string $ip): ?string
    {
        try {
            $record = $this->getReader()->city($ip);

            $name = $record->mostSpecificSubdivision->name;

            return is_string($name) ? $name : null;
        } catch (AddressNotFoundException|InvalidDatabaseException) {
            return null;
        }
    }

    /**
     * Resolves the city by querying the MaxMind database.
     *
     * Performs a city-level lookup and extracts the city name from
     * the database response.
     *
     * @param  string      $ip The IP address to resolve (IPv4 or IPv6)
     * @return null|string City name (e.g., 'Los Angeles', 'Toronto') or null if lookup fails
     */
    public function city(string $ip): ?string
    {
        try {
            $record = $this->getReader()->city($ip);

            $name = $record->city->name;

            return is_string($name) ? $name : null;
        } catch (AddressNotFoundException|InvalidDatabaseException) {
            return null;
        }
    }

    /**
     * Resolves geographic coordinates by querying the MaxMind database.
     *
     * Performs a city-level lookup and extracts latitude and longitude
     * from the location data. Returns null if either coordinate is missing.
     *
     * @param  string           $ip The IP address to resolve (IPv4 or IPv6)
     * @return null|Coordinates Geographic coordinates object containing latitude and longitude, or null if lookup fails
     */
    public function coordinates(string $ip): ?Coordinates
    {
        try {
            $record = $this->getReader()->city($ip);

            $lat = $record->location->latitude;
            $lon = $record->location->longitude;

            if (!is_float($lat) || !is_float($lon)) {
                return null;
            }

            return new Coordinates($lat, $lon);
        } catch (AddressNotFoundException|InvalidDatabaseException) {
            return null;
        }
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'maxmind_local'
     */
    public function identifier(): string
    {
        return 'maxmind_local';
    }

    /**
     * Gets or lazily initializes the MaxMind database reader.
     *
     * Creates a new Reader instance on first call, then caches it for
     * subsequent lookups to avoid repeated file system access and parsing.
     *
     * @throws InvalidDatabaseException If the database file is corrupted or invalid
     *
     * @return Reader The initialized MaxMind database reader
     */
    private function getReader(): Reader
    {
        if (!$this->reader instanceof Reader) {
            $this->reader = new Reader($this->databasePath);
        }

        return $this->reader;
    }
}
