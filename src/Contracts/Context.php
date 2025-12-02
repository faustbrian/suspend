<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Contracts;

/**
 * Contract for suspension context resolution.
 *
 * Contexts provide a way to identify the subject of a suspension check.
 * They can represent database entities (User model) or transient data
 * (email, phone, IP address).
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Context
{
    /**
     * Get the context type identifier.
     *
     * For entity contexts, this is typically the morph class name.
     * For transient contexts, this is the match type (email, phone, etc.).
     *
     * @return string The context type identifier
     */
    public function getContextType(): string;

    /**
     * Get the context identifier value.
     *
     * For entity contexts, this is typically the model's primary key.
     * For transient contexts, this is the actual value being matched.
     *
     * @return int|string The context identifier
     */
    public function getContextIdentifier(): string|int;

    /**
     * Serialize the context for storage or caching.
     *
     * @return string Serialized string representation of the context
     */
    public function serialize(): string;
}
