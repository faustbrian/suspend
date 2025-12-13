<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope to filter only active (non-revoked) suspensions.
 *
 * Automatically filters suspension queries to exclude revoked suspensions
 * by adding a WHERE revoked_at IS NULL clause. This ensures that only
 * currently active suspensions are retrieved unless explicitly overridden.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ActiveScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Adds a constraint to filter out suspensions that have been revoked
     * by checking for null revoked_at timestamps. This ensures queries
     * automatically exclude inactive suspension records.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param Builder<TModel> $builder Query builder to modify with the active constraint
     * @param TModel          $model   Model instance being queried (required by Scope interface)
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull('revoked_at');
    }
}
