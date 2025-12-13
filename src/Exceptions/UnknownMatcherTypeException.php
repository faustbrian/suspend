<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

/**
 * Exception thrown when a matcher type has not been registered.
 *
 * This exception is raised when attempting to use a matcher type that
 * hasn't been registered in the MatcherRegistry. Common causes include
 * typos in match_type values or forgetting to register custom matchers.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnknownMatcherTypeException extends InvalidMatcherException
{
    /**
     * Create an exception for an unknown matcher type.
     *
     * @param  string $type The unregistered matcher type that was requested
     * @return self   Exception instance with a descriptive error message
     */
    public static function forType(string $type): self
    {
        return new self('No matcher registered for type: '.$type);
    }
}
