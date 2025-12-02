<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models;
use Cline\Suspend\Enums\MorphType;
use Cline\Suspend\Enums\PrimaryKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('suspend.primary_key_type', 'id')) ?? PrimaryKeyType::ID;
        $contextMorphType = MorphType::tryFrom(config('suspend.context_morph_type', 'string')) ?? MorphType::String;
        $actorMorphType = MorphType::tryFrom(config('suspend.actor_morph_type', 'string')) ?? MorphType::String;

        Schema::create(Models::table('suspensions'), function (Blueprint $table) use ($primaryKeyType, $contextMorphType, $actorMorphType): void {
            // Primary key
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };

            // Entity-based context (optional - null for context-only suspensions)
            match ($contextMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('context'),
                MorphType::UUID => $table->nullableUuidMorphs('context'),
                MorphType::Numeric => $table->nullableNumericMorphs('context'),
                MorphType::String => $table->nullableMorphs('context'),
            };

            // Context-based matching (for transient data like email, phone, IP)
            $table->string('match_type')->nullable()->index();
            $table->string('match_value')->nullable();
            $table->jsonb('match_metadata')->nullable();

            // Strategy configuration
            $table->string('strategy')->nullable();
            $table->jsonb('strategy_metadata')->nullable();

            // Suspension details
            $table->text('reason')->nullable();
            $table->timestamp('suspended_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('starts_at')->nullable()->index();

            // Who created the suspension
            match ($actorMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('suspended_by'),
                MorphType::UUID => $table->nullableUuidMorphs('suspended_by'),
                MorphType::Numeric => $table->nullableNumericMorphs('suspended_by'),
                MorphType::String => $table->nullableMorphs('suspended_by'),
            };

            // Revocation tracking
            $table->timestamp('revoked_at')->nullable()->index();

            // Who revoked the suspension
            match ($actorMorphType) {
                MorphType::ULID => $table->nullableUlidMorphs('revoked_by'),
                MorphType::UUID => $table->nullableUuidMorphs('revoked_by'),
                MorphType::Numeric => $table->nullableNumericMorphs('revoked_by'),
                MorphType::String => $table->nullableMorphs('revoked_by'),
            };

            $table->timestamps();

            // Composite indexes for efficient lookups
            $table->index(['match_type', 'match_value']);
            $table->index(['context_type', 'context_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop(Models::table('suspensions'));
    }
};
