<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when all suspensions for a context are lifted.
 *
 * This event fires when one or more suspensions are lifted (revoked) for
 * a given context, either entity-based (via Model) or match-based (via
 * match type and value). Listeners can use this to send notifications,
 * restore access, or log the restoration for audit purposes.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SuspensionLifted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new suspension lifted event instance.
     *
     * @param null|Model  $context    The entity that was unsuspended (User, Team, etc.),
     *                                or null for match-based suspensions that don't target
     *                                a specific model instance.
     * @param null|string $matchType  the match type (email, phone, IP) if this was a
     *                                match-based suspension lift, or null for entity-based
     * @param null|string $matchValue the match value that was unsuspended if match-based,
     *                                or null for entity-based lifts
     * @param int         $count      Total number of suspension records that were lifted.
     *                                May be greater than 1 if multiple suspensions existed
     *                                for the same context or match criteria.
     */
    public function __construct(
        public ?Model $context,
        public ?string $matchType,
        public ?string $matchValue,
        public int $count,
    ) {}
}
