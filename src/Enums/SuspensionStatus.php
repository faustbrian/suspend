<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Enums;

/**
 * Represents the lifecycle status of a suspension.
 *
 * This enum is used to determine the current state of a suspension record
 * based on its temporal attributes (starts_at, suspended_at, expires_at,
 * revoked_at) and provides a clear semantic representation of whether
 * the suspension should currently be enforced.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum SuspensionStatus: string
{
    /**
     * Suspension is currently in effect.
     *
     * The suspension is active when the current time is between starts_at
     * (or suspended_at if starts_at is null) and expires_at, and the suspension
     * has not been revoked. Active suspensions will block access or trigger
     * configured enforcement actions.
     */
    case Active = 'active';

    /**
     * Suspension has passed its expiration date.
     *
     * The suspension expired naturally when the current time exceeds expires_at.
     * Expired suspensions are no longer enforced and exist primarily for
     * historical audit purposes and analytics on past suspension patterns.
     */
    case Expired = 'expired';

    /**
     * Suspension was manually lifted before expiration.
     *
     * The suspension was explicitly revoked by an administrator or automated
     * process before its natural expiration. The revoked_at timestamp and
     * optional revoked_by relationships track when and by whom the revocation
     * occurred for accountability and audit trails.
     */
    case Revoked = 'revoked';

    /**
     * Suspension is scheduled to start in the future.
     *
     * The suspension exists but is not yet active because the current time
     * is before the starts_at timestamp. Pending suspensions allow for
     * scheduled enforcement actions and provide advance notice of upcoming
     * access restrictions.
     */
    case Pending = 'pending';
}
