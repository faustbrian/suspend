<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Strategies\TimeWindowStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

describe('TimeWindowStrategy', function (): void {
    beforeEach(function (): void {
        $this->strategy = new TimeWindowStrategy();
    });

    describe('identifier', function (): void {
        test('returns time_window as strategy identifier', function (): void {
            // Act
            $identifier = $this->strategy->identifier();

            // Assert
            expect($identifier)->toBe('time_window');
        });
    });

    describe('Happy Paths', function (): void {
        test('matches request within time window with start and end times', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0)); // Noon
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request at exact start time of window', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 9, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request on specific day of week', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0)); // Monday
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [1], // Monday
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request on multiple allowed days of week', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 3, 12, 0, 0)); // Wednesday
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [1, 2, 3], // Monday, Tuesday, Wednesday
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with custom timezone', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0, 'America/New_York'));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
                'timezone' => 'America/New_York',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches when only start time is specified and current time is after', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 14, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches when only end time is specified and current time is before', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 14, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when current time is before start time', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 8, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when current time is after end time', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 18, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when day of week is not in allowed days', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 6, 12, 0, 0)); // Saturday
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [1, 2, 3, 4, 5], // Monday-Friday
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when day matches but time is outside window', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 18, 0, 0)); // Monday 6 PM
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [1], // Monday
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('matches when no time constraints are specified', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false at exact end time boundary', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 17, 0, 1)); // One second after 5 PM
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles midnight crossing time window', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 23, 30, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '22:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles Sunday as day 0 in days array', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 7, 12, 0, 0)); // Sunday
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [0], // Sunday
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles Saturday as day 6 in days array', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 6, 12, 0, 0)); // Saturday
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [6], // Saturday
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('uses app timezone when timezone not specified in metadata', function (): void {
            // Arrange
            config(['app.timezone' => 'UTC']);
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0, 'UTC'));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when days array is empty', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'days' => [],
                'start' => '09:00',
                'end' => '17:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles full datetime string for start time', function (): void {
            // Arrange
            Date::setTestNow(Date::create(2_024, 1, 1, 12, 0, 0));
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'start' => '2024-01-01 09:00:00',
                'end' => '2024-01-01 17:00:00',
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
