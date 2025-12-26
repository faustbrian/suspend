<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

use function gettype;
use function is_string;
use function sprintf;

/**
 * Exception thrown when a matcher receives an invalid value.
 *
 * This exception is raised when a matcher receives a value that fails
 * its validation rules. For example, a malformed email address for
 * the email matcher or invalid CIDR notation for the IP matcher.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidMatcherValueException extends InvalidMatcherException
{
    /**
     * Create an exception for an invalid matcher value.
     *
     * @param  string $type  The matcher type that rejected the value
     * @param  mixed  $value The value that failed validation
     * @return self   Exception instance with details about the validation failure
     */
    public static function forValue(string $type, mixed $value): self
    {
        $valueStr = is_string($value) ? $value : gettype($value);

        return new self(sprintf("Invalid value for matcher type '%s': %s", $type, $valueStr));
    }
}
