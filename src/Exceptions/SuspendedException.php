<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Exceptions;

use Cline\Suspend\Database\Models\Suspension;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function config;

/**
 * Exception thrown when access is denied due to an active suspension.
 *
 * This HTTP exception is thrown by the CheckSuspension middleware when
 * a matching suspension record is found. It extends Symfony's HttpException
 * to provide proper HTTP error responses with configurable status codes
 * and messages. The exception carries the suspension record for logging
 * and audit purposes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SuspendedException extends HttpException implements SuspendException
{
    /**
     * The suspension record that triggered this exception.
     */
    private ?Suspension $suspension = null;

    /**
     * Create an exception from a suspension record.
     *
     * Builds an HTTP exception with status code and message from configuration,
     * appending the suspension reason if available. The suspension record is
     * attached to enable access in exception handlers for logging or custom
     * error responses.
     *
     * @param  Suspension $suspension The active suspension record that caused the access denial
     * @return self       The exception instance configured with appropriate HTTP response details
     */
    public static function fromSuspension(Suspension $suspension): self
    {
        /** @var string $message */
        $message = config('suspend.middleware.response_message', 'Access denied. Your access has been suspended.');

        /** @var int $statusCode */
        $statusCode = config('suspend.middleware.response_code', 403);

        if ($suspension->reason !== null) {
            $message .= ' Reason: '.$suspension->reason;
        }

        $exception = new self($statusCode, $message);
        $exception->suspension = $suspension;

        return $exception;
    }

    /**
     * Get the suspension record that triggered this exception.
     *
     * Useful in exception handlers to access suspension details for
     * logging, audit trails, or custom error response formatting.
     *
     * @return null|Suspension The suspension record, or null if not set
     */
    public function getSuspension(): ?Suspension
    {
        return $this->suspension;
    }
}
