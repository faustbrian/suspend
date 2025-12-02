<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Database\Models;

use Cline\Suspend\Database\Concerns\HasSuspendPrimaryKey;
use Cline\Suspend\Database\Concerns\IsSuspension;
use Cline\Suspend\Database\Models as ModelsRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Represents a suspension record in the system.
 *
 * Suspensions can be entity-based (tied to a model like User) or context-based
 * (matching transient data like email, phone, IP). Both types support strategies
 * for sophisticated matching logic.
 *
 * Entity-based suspensions use polymorphic relationships (context_type/context_id)
 * to link directly to models, while context-based suspensions use flexible matchers
 * (match_type/match_value) to evaluate runtime data against suspension criteria.
 *
 * @property null|int|string           $context_id        Polymorphic identifier for entity-based suspensions
 * @property null|string               $context_type      Polymorphic type for entity-based suspensions
 * @property Carbon                    $created_at        Timestamp when the record was created
 * @property null|Carbon               $expires_at        Timestamp when the suspension automatically expires
 * @property int                       $id                Primary key identifier
 * @property null|array<string, mixed> $match_metadata    Additional metadata for context matching logic
 * @property null|string               $match_type        Type of context matcher (email, phone, IP, etc.)
 * @property null|string               $match_value       Value to match against (supports wildcards/patterns)
 * @property null|string               $reason            Human-readable reason for the suspension
 * @property null|Carbon               $revoked_at        Timestamp when the suspension was manually revoked
 * @property null|int|string           $revoked_by_id     Polymorphic identifier of who revoked the suspension
 * @property null|string               $revoked_by_type   Polymorphic type of who revoked the suspension
 * @property null|Carbon               $starts_at         Timestamp when the suspension becomes active (scheduled)
 * @property null|string               $strategy          Strategy class name for advanced matching logic
 * @property null|array<string, mixed> $strategy_metadata Configuration data for the strategy implementation
 * @property Carbon                    $suspended_at      Timestamp when the suspension was created
 * @property null|int|string           $suspended_by_id   Polymorphic identifier of who created the suspension
 * @property null|string               $suspended_by_type Polymorphic type of who created the suspension
 * @property Carbon                    $updated_at        Timestamp when the record was last updated
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Suspension extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSuspendPrimaryKey;
    use IsSuspension;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'context_type',
        'context_id',
        'match_type',
        'match_value',
        'match_metadata',
        'strategy',
        'strategy_metadata',
        'reason',
        'suspended_at',
        'expires_at',
        'suspended_by_type',
        'suspended_by_id',
        'revoked_at',
        'revoked_by_type',
        'revoked_by_id',
        'starts_at',
    ];

    /**
     * Get the table associated with the model.
     *
     * Uses the ModelRegistry to resolve the actual table name, allowing
     * customization through configuration without requiring model changes.
     *
     * @return string The configured table name for suspensions
     */
    #[Override()]
    public function getTable(): string
    {
        return ModelsRegistry::table('suspensions');
    }

    /**
     * Get the attributes that should be cast to native types.
     *
     * Defines type casting for JSON metadata columns and timestamp fields
     * to ensure proper handling during model hydration and serialization.
     *
     * @return array<string, string> Mapping of attribute names to cast types
     */
    #[Override()]
    protected function casts(): array
    {
        return [
            'match_metadata' => 'array',
            'strategy_metadata' => 'array',
            'suspended_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'starts_at' => 'datetime',
        ];
    }
}
