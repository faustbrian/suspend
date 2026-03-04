<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Events;

use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a suspension is created.
 *
 * This event fires immediately after a new suspension record is created,
 * allowing listeners to perform actions like sending notifications, logging
 * the suspension for audit purposes, or triggering additional security measures.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SuspensionCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new suspension created event instance.
     *
     * @param Suspension $suspension the newly created suspension record containing
     *                               the suspension configuration, target entity or
     *                               match criteria, temporal settings, and metadata
     */
    public function __construct(
        public Suspension $suspension,
    ) {}
}
