<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Database;

use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Facade;
use Override;

/**
 * Facade for accessing the ModelRegistry singleton.
 *
 * Provides static access to model and table configuration while maintaining
 * Octane compatibility through container-bound instance management. This facade
 * centralizes model class resolution, table name mapping, and morph map updates
 * to ensure consistent configuration across the package.
 *
 * @method static string     classname(string $model)                                     Resolve the fully-qualified class name for a registered model type
 * @method static Builder    query(string $table)                                         Create a query builder instance for the specified table
 * @method static void       reset()                                                      Reset all model and table registrations to default values
 * @method static void       setSuspensionModel(string $model)                            Register a custom Suspension model class
 * @method static void       setTables(array<string, string> $map)                        Register custom table name mappings
 * @method static Suspension suspension(array<string, mixed> $attributes = [])            Create a new Suspension model instance with optional attributes
 * @method static string     table(string $table)                                         Resolve the configured table name for a given table key
 * @method static void       updateMorphMap(?array<int, class-string> $classNames = null) Update Laravel's morph map with Suspend model classes
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ModelRegistry
 */
final class Models extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key for the ModelRegistry
     */
    #[Override()]
    protected static function getFacadeAccessor(): string
    {
        return ModelRegistry::class;
    }
}
