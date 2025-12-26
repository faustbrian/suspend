<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Strategies;

use Carbon\Carbon;
use Cline\Suspend\Contracts\Strategy;
use Cline\Suspend\Exceptions\InvalidTimeFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

use function array_key_exists;
use function config;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;

/**
 * Strategy that matches only during specific time windows.
 *
 * Useful for temporary restrictions during maintenance windows, business hours,
 * or scheduled downtime. Supports day-of-week filtering and time ranges with
 * timezone awareness for accurate temporal restrictions.
 *
 * Metadata format:
 * - start: string (optional) - Start time (H:i format like '09:00' or full Carbon parseable datetime)
 * - end: string (optional) - End time (H:i format like '17:00' or full Carbon parseable datetime)
 * - days: array<int> (optional) - Days of week (0=Sunday, 1=Monday, ..., 6=Saturday)
 * - timezone: string (optional) - Timezone for time comparison (defaults to app.timezone)
 *
 * ```php
 * // Suspend access during business hours (9 AM to 5 PM) on weekdays
 * $suspension = Suspension::create([
 *     'strategy' => 'time_window',
 *     'strategy_metadata' => [
 *         'start' => '09:00',
 *         'end' => '17:00',
 *         'days' => [1, 2, 3, 4, 5], // Monday-Friday
 *         'timezone' => 'America/New_York',
 *     ],
 * ]);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TimeWindowStrategy implements Strategy
{
    /**
     * Determine if the strategy matches the current request.
     *
     * Checks if the current time falls within the configured time window
     * and day-of-week restrictions. All time comparisons use the specified
     * timezone or fall back to the application's default timezone.
     *
     * @param  Request              $request  HTTP request (timestamp determined from current time, not request)
     * @param  array<string, mixed> $metadata Strategy metadata with start/end times, days, and timezone
     * @return bool                 True if current time is within the configured window and day restrictions, false otherwise
     */
    public function matches(Request $request, array $metadata = []): bool
    {
        $timezone = $metadata['timezone'] ?? config('app.timezone', 'UTC');

        if (!is_string($timezone)) {
            $timezone = 'UTC';
        }

        $now = Date::now($timezone);

        // Check day of week
        if (array_key_exists('days', $metadata) && is_array($metadata['days']) && !in_array($now->dayOfWeek, $metadata['days'], true)) {
            return false;
        }

        // Check time window
        $start = $metadata['start'] ?? null;
        $end = $metadata['end'] ?? null;

        if (is_string($start)) {
            $startTime = $this->parseTime($start, $timezone);

            if ($now->lt($startTime)) {
                return false;
            }
        }

        if (is_string($end)) {
            $endTime = $this->parseTime($end, $timezone);

            if ($now->gt($endTime)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the unique identifier for this strategy type.
     *
     * @return string Strategy identifier used for registration and lookup
     */
    public function identifier(): string
    {
        return 'time_window';
    }

    /**
     * Parse a time string to Carbon instance.
     *
     * Supports two formats: simple time strings in H:i format (e.g., '14:30')
     * which use today's date, or full datetime strings that Carbon can parse.
     *
     * @param string $time     Time string in H:i format or Carbon parseable datetime format
     * @param string $timezone Timezone to use for parsing and comparison
     *
     * @throws InvalidTimeFormatException When the time format is invalid
     *
     * @return Carbon Parsed Carbon instance with the appropriate timezone
     */
    private function parseTime(string $time, string $timezone): Carbon
    {
        // Handle H:i format (just time, use today's date)
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $parsed = Date::createFromFormat('H:i', $time, $timezone);

            if (!$parsed instanceof Carbon) {
                throw InvalidTimeFormatException::forTime($time);
            }

            return $parsed;
        }

        // Otherwise parse as full date/time
        return Date::parse($time, $timezone);
    }
}
