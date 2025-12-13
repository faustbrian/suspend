<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when an optional dependency is required but not installed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingDependencyException extends RuntimeException implements SuspendException
{
    /**
     * Create an exception for a missing composer package.
     *
     * Thrown when attempting to use functionality that depends on an
     * optional package that has not been installed. This allows the
     * package to keep core dependencies minimal while supporting
     * advanced features through optional dependencies.
     *
     * @param  string $package The missing package name in vendor/package format (e.g., 'geoip2/geoip2')
     * @param  string $feature The feature name that requires this package (e.g., 'GeoIP country resolution')
     * @return self   The exception instance with installation instructions
     */
    public static function package(string $package, string $feature): self
    {
        return new self(
            sprintf('The %s requires the %s package. ', $feature, $package).
            ('Install it with: composer require '.$package),
        );
    }
}
