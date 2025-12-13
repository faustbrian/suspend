<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Enums;

/**
 * Supported polymorphic relationship identifier types.
 *
 * Controls the column type used for polymorphic relationship foreign keys
 * (context_type, context_id, suspended_by_type, suspended_by_id, etc.) in
 * the suspensions table. This ensures schema compatibility with the primary
 * key types used in related models.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum MorphType: string
{
    /**
     * Standard string-based morph type.
     *
     * Uses VARCHAR columns for both type and id fields. This provides
     * maximum flexibility but may have performance implications for
     * large datasets compared to numeric types.
     */
    case String = 'string';

    /**
     * Numeric morph type.
     *
     * Uses unsigned BIGINT for id columns while maintaining VARCHAR for
     * type columns. Ideal for traditional auto-incrementing integer primary
     * keys with optimal query performance and minimal storage overhead.
     */
    case Numeric = 'numeric';

    /**
     * UUID-based morph type.
     *
     * Uses UUID columns (CHAR(36) or native UUID type) for id fields.
     * Provides globally unique identifiers suitable for distributed systems
     * and scenarios requiring non-sequential, unpredictable identifiers.
     */
    case UUID = 'uuid';

    /**
     * ULID-based morph type.
     *
     * Uses ULID columns (CHAR(26)) for id fields. Combines the benefits
     * of UUIDs (global uniqueness) with lexicographic sortability and
     * compact string representation for time-ordered records.
     */
    case ULID = 'ulid';
}
