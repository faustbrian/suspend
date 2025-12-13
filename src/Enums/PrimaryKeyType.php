<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Enums;

/**
 * Supported primary key types for Suspend tables.
 *
 * Defines the available primary key strategies for the suspensions table,
 * allowing configuration to match the application's existing key management
 * approach and performance requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum PrimaryKeyType: string
{
    /**
     * Standard auto-incrementing integer primary key.
     *
     * Uses BIGINT UNSIGNED AUTO_INCREMENT for the id column. This is the
     * traditional Laravel default offering excellent query performance,
     * minimal storage footprint, and straightforward migration compatibility.
     */
    case ID = 'id';

    /**
     * UUID primary key.
     *
     * Uses UUID (CHAR(36) or native UUID type depending on database) for
     * the id column. Provides globally unique identifiers ideal for distributed
     * systems, multi-tenant architectures, and scenarios requiring non-sequential
     * identifiers for security or privacy reasons.
     */
    case UUID = 'uuid';

    /**
     * ULID primary key.
     *
     * Uses ULID (CHAR(26)) for the id column. Combines UUID benefits with
     * lexicographic sortability, making it URL-safe, compact, and naturally
     * time-ordered without requiring separate timestamp columns for ordering.
     */
    case ULID = 'ulid';
}
