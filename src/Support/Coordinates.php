<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Support;

use Cline\Suspend\Exceptions\MissingLatitudeKeyException;
use Cline\Suspend\Exceptions\MissingLongitudeKeyException;

use function array_key_exists;
use function atan2;
use function cos;
use function deg2rad;
use function sin;
use function sqrt;

/**
 * Value object representing geographic coordinates.
 *
 * Immutable container for latitude/longitude pairs returned by
 * geo resolvers. Provides convenience methods for distance calculation
 * and array conversion, supporting flexible input formats for common
 * geolocation APIs.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class Coordinates
{
    /**
     * Create a new coordinates instance.
     *
     * @param float $latitude  Latitude in decimal degrees, ranging from -90 (South Pole) to 90 (North Pole)
     * @param float $longitude Longitude in decimal degrees, ranging from -180 (westernmost) to 180 (easternmost)
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * Create coordinates from an array.
     *
     * Accepts multiple common array formats from various geolocation services
     * including Google Maps, OpenStreetMap, and other geographic APIs.
     *
     * @param array{lat: float, lng: float}|array{lat: float, lon: float}|array{latitude: float, longitude: float} $data Array containing latitude and longitude in various key formats
     *
     * @throws MissingLatitudeKeyException  When the array does not contain a latitude key
     * @throws MissingLongitudeKeyException When the array does not contain a longitude key
     *
     * @return self New coordinates instance
     */
    public static function fromArray(array $data): self
    {
        if (array_key_exists('latitude', $data)) {
            $lat = $data['latitude'];
        } elseif (array_key_exists('lat', $data)) {
            $lat = $data['lat'];
        } else {
            throw MissingLatitudeKeyException::create();
        }

        if (array_key_exists('longitude', $data)) {
            $lon = $data['longitude'];
        } elseif (array_key_exists('lon', $data)) {
            $lon = $data['lon'];
        } elseif (array_key_exists('lng', $data)) {
            $lon = $data['lng'];
        } else {
            throw MissingLongitudeKeyException::create();
        }

        return new self((float) $lat, (float) $lon);
    }

    /**
     * Convert to an array.
     *
     * Exports coordinates in a standardized array format with full key names
     * suitable for storage or API responses.
     *
     * @return array{latitude: float, longitude: float} Array with 'latitude' and 'longitude' keys
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * Calculate distance to another point in kilometers.
     *
     * Uses the Haversine formula for great-circle distance calculation on a
     * spherical Earth approximation. Accurate for most applications with
     * typical error of less than 0.5%. Earth radius of 6,371 km is used.
     *
     * @param  self  $other The destination coordinates to calculate distance to
     * @return float Distance in kilometers between the two points
     */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6_371; // km

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($other->latitude);
        $lonTo = deg2rad($other->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
            cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
