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
 * Base exception for all matcher-related errors.
 *
 * Abstract base class that allows catching any matcher exception
 * with a single catch block while providing concrete implementations
 * for specific error conditions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidMatcherException extends InvalidArgumentException implements SuspendException
{
    // Abstract base - no factory methods
}
