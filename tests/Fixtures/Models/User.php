<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Models;

use Cline\Suspend\Concerns\HasSuspensions;
use Cline\Suspend\Contracts\Suspendable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Test user model.
 *
 * @property string $email
 * @property int    $id
 * @property string $name
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class User extends Model implements Suspendable
{
    use HasFactory;
    use HasSuspensions;

    protected $guarded = [];
}
