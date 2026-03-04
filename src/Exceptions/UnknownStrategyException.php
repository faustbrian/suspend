<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

/**
 * Exception thrown when a strategy has not been registered.
 *
 * This exception is raised when attempting to retrieve a strategy
 * that has not been registered with the SuspendManager. Common causes
 * include referencing a strategy by an incorrect identifier or
 * forgetting to register a custom strategy during bootstrap.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnknownStrategyException extends InvalidStrategyException
{
    /**
     * Create an exception for an unknown strategy.
     *
     * @param  string $identifier The strategy identifier that was not found
     * @return self   Exception instance with a descriptive message
     */
    public static function forIdentifier(string $identifier): self
    {
        return new self('No strategy registered with identifier: '.$identifier);
    }
}
