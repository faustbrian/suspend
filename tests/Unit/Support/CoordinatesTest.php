<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Support\Coordinates;

describe('Coordinates', function (): void {
    it('stores latitude and longitude', function (): void {
        $coords = new Coordinates(34.052_2, -118.243_7);

        expect($coords->latitude)->toBe(34.052_2);
        expect($coords->longitude)->toBe(-118.243_7);
    });

    it('creates from array with latitude/longitude keys', function (): void {
        $coords = Coordinates::fromArray([
            'latitude' => 40.712_8,
            'longitude' => -74.006_0,
        ]);

        expect($coords->latitude)->toBe(40.712_8);
        expect($coords->longitude)->toBe(-74.006_0);
    });

    it('creates from array with lat/lng keys', function (): void {
        $coords = Coordinates::fromArray([
            'lat' => 51.507_4,
            'lng' => -0.127_8,
        ]);

        expect($coords->latitude)->toBe(51.507_4);
        expect($coords->longitude)->toBe(-0.127_8);
    });

    it('creates from array with lat/lon keys', function (): void {
        $coords = Coordinates::fromArray([
            'lat' => 48.856_6,
            'lon' => 2.352_2,
        ]);

        expect($coords->latitude)->toBe(48.856_6);
        expect($coords->longitude)->toBe(2.352_2);
    });

    it('converts to array', function (): void {
        $coords = new Coordinates(35.676_2, 139.650_3);

        expect($coords->toArray())->toBe([
            'latitude' => 35.676_2,
            'longitude' => 139.650_3,
        ]);
    });

    it('calculates distance between two points', function (): void {
        // Los Angeles to New York is approximately 3944 km
        $la = new Coordinates(34.052_2, -118.243_7);
        $nyc = new Coordinates(40.712_8, -74.006_0);

        $distance = $la->distanceTo($nyc);

        // Allow 1% margin for Haversine approximation
        expect($distance)->toBeGreaterThan(3_900);
        expect($distance)->toBeLessThan(4_000);
    });

    it('returns zero distance for same point', function (): void {
        $coords = new Coordinates(34.052_2, -118.243_7);

        expect($coords->distanceTo($coords))->toBe(0.0);
    });

    it('calculates distance correctly for antipodal points', function (): void {
        // Opposite sides of Earth - approximately 20,000 km
        $point1 = new Coordinates(0.0, 0.0);
        $point2 = new Coordinates(0.0, 180.0);

        $distance = $point1->distanceTo($point2);

        expect($distance)->toBeGreaterThan(19_000);
        expect($distance)->toBeLessThan(21_000);
    });
});
