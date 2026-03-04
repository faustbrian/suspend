<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

use function sprintf;

/**
 * Exception thrown when required strategy metadata is missing.
 *
 * This exception is raised when a strategy requires specific metadata
 * fields that were not provided during suspension creation. Different
 * strategies may require different metadata (e.g., IP ranges for
 * geographic restrictions, duration for temporary suspensions).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingStrategyMetadataException extends InvalidStrategyException
{
    /**
     * Create an exception for missing required metadata.
     *
     * @param  string $strategy The strategy identifier requiring the metadata
     * @param  string $field    The name of the missing metadata field
     * @return self   Exception instance with a descriptive message
     */
    public static function forField(string $strategy, string $field): self
    {
        return new self(sprintf("Strategy '%s' requires metadata field: %s", $strategy, $field));
    }
}
