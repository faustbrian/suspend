<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Contracts;

use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for models that can be suspended.
 *
 * Implement this interface on any Eloquent model that should support
 * entity-based suspensions (e.g., User, Team, Organization). The
 * HasSuspensions trait provides the default implementation.
 *
 * All suspension operations should be performed via the Suspend facade:
 * - Suspend::for($model)->suspend()
 * - Suspend::for($model)->isSuspended()
 * - Suspend::for($model)->lift()
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Suspendable
{
    /**
     * Get all suspensions for this entity.
     *
     * @return MorphMany<Suspension, Model>
     */
    public function suspensions(): MorphMany;
}
