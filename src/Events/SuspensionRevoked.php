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
 * Event dispatched when a suspension is revoked.
 *
 * This event fires when a specific suspension is explicitly revoked before
 * its natural expiration. Unlike SuspensionLifted which handles bulk operations,
 * this event provides fine-grained tracking for individual suspension revocations
 * along with optional revocation reasoning for audit trails.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SuspensionRevoked
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new suspension revoked event instance.
     *
     * @param Suspension  $suspension the suspension record that was revoked, including
     *                                its revoked_at timestamp and revoked_by relationship
     *                                for audit purposes
     * @param null|string $reason     Optional human-readable reason for the revocation,
     *                                providing context for why this suspension was lifted
     *                                before its scheduled expiration (e.g., "Appeal approved",
     *                                "False positive", "Policy change").
     */
    public function __construct(
        public Suspension $suspension,
        public ?string $reason = null,
    ) {}
}
