<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when coordinates array is missing a longitude key.
 *
 * This exception is raised when creating Coordinates from an array
 * that does not contain either a "longitude", "lon", or "lng" key.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingLongitudeKeyException extends InvalidArgumentException implements SuspendException
{
    /**
     * Create an exception for missing longitude key.
     *
     * @return self Exception instance with a descriptive message
     */
    public static function create(): self
    {
        return new self('Array must contain either "longitude", "lon", or "lng" key');
    }
}
