<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

use Carbon\Exceptions\InvalidFormatException;

use function sprintf;

/**
 * Exception thrown when a time string cannot be parsed.
 *
 * This exception is raised when the TimeWindowStrategy receives
 * a time value that cannot be parsed in the expected format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTimeFormatException extends InvalidFormatException implements SuspendException
{
    /**
     * Create an exception for an invalid time format.
     *
     * @param  string $time The time string that could not be parsed
     * @return self   Exception instance with a descriptive message
     */
    public static function forTime(string $time): self
    {
        return new self(sprintf('Invalid time format: %s', $time));
    }
}
