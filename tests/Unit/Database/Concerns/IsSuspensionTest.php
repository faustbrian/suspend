<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Enums\SuspensionStatus;
use Cline\Suspend\Events\SuspensionRevoked;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Models\User;

describe('IsSuspension', function (): void {
    describe('Relationships', function (): void {
        describe('Happy Paths', function (): void {
            test('returns MorphTo relationship for context', function (): void {
                // Arrange
                $user = createUser();
                $suspension = new Suspension([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $relationship = $suspension->context();

                // Assert
                expect($relationship)->toBeInstanceOf(MorphTo::class);
            });

            test('returns MorphTo relationship for suspendedBy', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $suspension = new Suspension([
                    'suspended_by_type' => $admin->getMorphClass(),
                    'suspended_by_id' => $admin->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $relationship = $suspension->suspendedBy();

                // Assert
                expect($relationship)->toBeInstanceOf(MorphTo::class);
            });

            test('returns MorphTo relationship for revokedBy', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $suspension = new Suspension([
                    'revoked_by_type' => $admin->getMorphClass(),
                    'revoked_by_id' => $admin->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $relationship = $suspension->revokedBy();

                // Assert
                expect($relationship)->toBeInstanceOf(MorphTo::class);
            });

            test('loads context relationship with actual model', function (): void {
                // Arrange
                $user = createUser();
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $context = $suspension->context;

                // Assert
                expect($context)->toBeInstanceOf(User::class)
                    ->and($context->id)->toBe($user->id);
            });

            test('loads suspendedBy relationship with actual model', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $user = createUser(['email' => 'user@example.com']);
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_by_type' => $admin->getMorphClass(),
                    'suspended_by_id' => $admin->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $suspendedBy = $suspension->suspendedBy;

                // Assert
                expect($suspendedBy)->toBeInstanceOf(User::class)
                    ->and($suspendedBy->id)->toBe($admin->id);
            });

            test('loads revokedBy relationship with actual model', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $user = createUser(['email' => 'user@example.com']);
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'revoked_by_type' => $admin->getMorphClass(),
                    'revoked_by_id' => $admin->getKey(),
                    'revoked_at' => now(),
                    'suspended_at' => now(),
                ]);

                // Act
                $revokedBy = $suspension->revokedBy;

                // Assert
                expect($revokedBy)->toBeInstanceOf(User::class)
                    ->and($revokedBy->id)->toBe($admin->id);
            });
        });

        describe('Edge Cases', function (): void {
            test('context relationship returns null when not set', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'match_type' => 'email',
                    'match_value' => 'test@example.com',
                    'suspended_at' => now(),
                ]);

                // Act
                $context = $suspension->context;

                // Assert
                expect($context)->toBeNull();
            });

            test('suspendedBy relationship returns null when not set', function (): void {
                // Arrange
                $user = createUser();
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $suspendedBy = $suspension->suspendedBy;

                // Assert
                expect($suspendedBy)->toBeNull();
            });

            test('revokedBy relationship returns null when not set', function (): void {
                // Arrange
                $user = createUser();
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act
                $revokedBy = $suspension->revokedBy;

                // Assert
                expect($revokedBy)->toBeNull();
            });
        });
    });

    describe('Status Determination', function (): void {
        describe('Happy Paths', function (): void {
            test('returns Active when suspension is currently in effect', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subDay(),
                    'starts_at' => null,
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Active);
            });

            test('returns Active when started and not expired', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subDay(),
                    'starts_at' => now()->subHour(),
                    'expires_at' => now()->addDay(),
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Active);
            });

            test('returns Pending when starts_at is in future', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                    'starts_at' => now()->addDay(),
                    'expires_at' => now()->addWeek(),
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Pending);
            });

            test('returns Expired when expires_at is in past', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subWeek(),
                    'starts_at' => null,
                    'expires_at' => now()->subDay(),
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Expired);
            });

            test('returns Revoked when revoked_at is set', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subWeek(),
                    'starts_at' => null,
                    'expires_at' => null,
                    'revoked_at' => now()->subDay(),
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Revoked);
            });
        });

        describe('Edge Cases', function (): void {
            test('returns Revoked even when expired (revoked takes priority)', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subWeek(),
                    'expires_at' => now()->subDay(),
                    'revoked_at' => now()->subHour(),
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Revoked);
            });

            test('returns Pending even when would be expired (pending takes priority over expired)', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subWeek(),
                    'starts_at' => now()->addDay(),
                    'expires_at' => now()->subDay(),
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Pending);
            });

            test('returns Active when starts_at equals current time', function (): void {
                // Arrange
                $now = now();
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => $now->copy()->subDay(),
                    'starts_at' => $now,
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Active);
            });

            test('returns Expired when expires_at equals current time', function (): void {
                // Arrange
                $now = now();
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => $now->copy()->subDay(),
                    'starts_at' => null,
                    'expires_at' => $now,
                    'revoked_at' => null,
                ]);

                // Act
                $status = $suspension->status();

                // Assert
                expect($status)->toBe(SuspensionStatus::Expired);
            });
        });
    });

    describe('Status Check Methods', function (): void {
        describe('Happy Paths', function (): void {
            test('isActive returns true when suspension is active', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                    'revoked_at' => null,
                ]);

                // Act & Assert
                expect($suspension->isActive())->toBeTrue()
                    ->and($suspension->isExpired())->toBeFalse()
                    ->and($suspension->isRevoked())->toBeFalse()
                    ->and($suspension->isPending())->toBeFalse();
            });

            test('isExpired returns true when suspension is expired', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subWeek(),
                    'expires_at' => now()->subDay(),
                    'revoked_at' => null,
                ]);

                // Act & Assert
                expect($suspension->isExpired())->toBeTrue()
                    ->and($suspension->isActive())->toBeFalse()
                    ->and($suspension->isRevoked())->toBeFalse()
                    ->and($suspension->isPending())->toBeFalse();
            });

            test('isRevoked returns true when suspension is revoked', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now()->subWeek(),
                    'revoked_at' => now(),
                ]);

                // Act & Assert
                expect($suspension->isRevoked())->toBeTrue()
                    ->and($suspension->isActive())->toBeFalse()
                    ->and($suspension->isExpired())->toBeFalse()
                    ->and($suspension->isPending())->toBeFalse();
            });

            test('isPending returns true when suspension is pending', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                    'starts_at' => now()->addDay(),
                    'revoked_at' => null,
                ]);

                // Act & Assert
                expect($suspension->isPending())->toBeTrue()
                    ->and($suspension->isActive())->toBeFalse()
                    ->and($suspension->isExpired())->toBeFalse()
                    ->and($suspension->isRevoked())->toBeFalse();
            });
        });
    });

    describe('Type Check Methods', function (): void {
        describe('Happy Paths', function (): void {
            test('isContextBased returns true when match_type is set', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'match_type' => 'email',
                    'match_value' => 'test@example.com',
                    'suspended_at' => now(),
                ]);

                // Act & Assert
                expect($suspension->isContextBased())->toBeTrue();
            });

            test('isEntityBased returns true when context_type is set', function (): void {
                // Arrange
                $user = createUser();
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act & Assert
                expect($suspension->isEntityBased())->toBeTrue();
            });

            test('isContextBased returns false when match_type is null', function (): void {
                // Arrange
                $user = createUser();
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'suspended_at' => now(),
                ]);

                // Act & Assert
                expect($suspension->isContextBased())->toBeFalse();
            });

            test('isEntityBased returns false when context_type is null', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'match_type' => 'email',
                    'match_value' => 'test@example.com',
                    'suspended_at' => now(),
                ]);

                // Act & Assert
                expect($suspension->isEntityBased())->toBeFalse();
            });
        });

        describe('Edge Cases', function (): void {
            test('suspension can be both context-based and entity-based', function (): void {
                // Arrange
                $user = createUser();
                $suspension = Suspension::query()->create([
                    'context_type' => $user->getMorphClass(),
                    'context_id' => $user->getKey(),
                    'match_type' => 'email',
                    'match_value' => 'test@example.com',
                    'suspended_at' => now(),
                ]);

                // Act & Assert
                expect($suspension->isContextBased())->toBeTrue()
                    ->and($suspension->isEntityBased())->toBeTrue();
            });

            test('suspension can be neither context-based nor entity-based', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'suspended_at' => now(),
                    'reason' => 'Global suspension',
                ]);

                // Act & Assert
                expect($suspension->isContextBased())->toBeFalse()
                    ->and($suspension->isEntityBased())->toBeFalse();
            });
        });
    });

    describe('Revoke Method', function (): void {
        describe('Happy Paths', function (): void {
            test('revokes suspension by setting revoked_at timestamp', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                ]);
                expect($suspension->revoked_at)->toBeNull();

                // Act
                $result = $suspension->revoke();

                // Assert
                expect($result)->toBeTrue()
                    ->and($suspension->fresh()->revoked_at)->not->toBeNull();
            });

            test('revokes suspension with revokedBy model', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                ]);

                // Act
                $result = $suspension->revoke($admin);

                // Assert
                $suspension = $suspension->fresh();
                expect($result)->toBeTrue()
                    ->and($suspension->revoked_by_type)->toBe($admin->getMorphClass())
                    ->and($suspension->revoked_by_id)->toBe($admin->getKey());
            });

            test('revokes suspension with reason', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                    'reason' => 'Original reason',
                ]);

                // Act
                $result = $suspension->revoke(null, 'Appeal granted');

                // Assert
                expect($result)->toBeTrue()
                    ->and($suspension->fresh()->reason)->toBe('Appeal granted');
            });

            test('revokes suspension with both revokedBy and reason', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                ]);

                // Act
                $result = $suspension->revoke($admin, 'Appeal granted');

                // Assert
                $suspension = $suspension->fresh();
                expect($result)->toBeTrue()
                    ->and($suspension->revoked_by_type)->toBe($admin->getMorphClass())
                    ->and($suspension->revoked_by_id)->toBe($admin->getKey())
                    ->and($suspension->reason)->toBe('Appeal granted');
            });

            test('dispatches SuspensionRevoked event when revoked successfully', function (): void {
                // Arrange
                Event::fake([SuspensionRevoked::class]);
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                ]);

                // Act
                $suspension->revoke(null, 'Test reason');

                // Assert
                Event::assertDispatched(SuspensionRevoked::class, fn ($event): bool => $event->suspension->id === $suspension->id
                    && $event->reason === 'Test reason');
            });
        });

        describe('Edge Cases', function (): void {
            test('revokes suspension without overwriting existing reason when reason is null', function (): void {
                // Arrange
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                    'reason' => 'Original reason',
                ]);

                // Act
                $result = $suspension->revoke();

                // Assert
                expect($result)->toBeTrue()
                    ->and($suspension->fresh()->reason)->toBe('Original reason');
            });

            test('revokes suspension with integer model key', function (): void {
                // Arrange
                $admin = createUser(['email' => 'admin@example.com']);
                $suspension = Suspension::query()->create([
                    'context_type' => 'User',
                    'context_id' => 1,
                    'suspended_at' => now(),
                ]);

                // Act
                $result = $suspension->revoke($admin);

                // Assert
                expect($result)->toBeTrue()
                    ->and($suspension->fresh()->revoked_by_id)->toBe($admin->getKey());
            });
        });
    });

    describe('Query Scopes', function (): void {
        describe('scopeActive', function (): void {
            describe('Happy Paths', function (): void {
                test('includes suspensions with no revoked_at, no starts_at, and no expires_at', function (): void {
                    // Arrange
                    $active = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                    ]);
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 2,
                        'suspended_at' => now(),
                        'revoked_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($active->id);
                });

                test('includes suspensions that have started', function (): void {
                    // Arrange
                    $active = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'starts_at' => now()->subHour(),
                    ]);
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 2,
                        'suspended_at' => now(),
                        'starts_at' => now()->addHour(),
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($active->id);
                });

                test('includes suspensions not yet expired', function (): void {
                    // Arrange
                    $active = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'expires_at' => now()->addDay(),
                    ]);
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 2,
                        'suspended_at' => now(),
                        'expires_at' => now()->subDay(),
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($active->id);
                });

                test('excludes revoked suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'revoked_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes pending suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'starts_at' => now()->addDay(),
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes expired suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'expires_at' => now()->subDay(),
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });

            describe('Edge Cases', function (): void {
                test('includes suspensions with starts_at exactly at current time', function (): void {
                    // Arrange
                    $now = now();
                    $active = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => $now->copy()->subDay(),
                        'starts_at' => $now,
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($active->id);
                });

                test('excludes suspensions with expires_at exactly at current time', function (): void {
                    // Arrange
                    $now = now();
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => $now->copy()->subDay(),
                        'expires_at' => $now,
                    ]);

                    // Act
                    $results = Suspension::query()->active()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });
        });

        describe('scopeExpired', function (): void {
            describe('Happy Paths', function (): void {
                test('includes suspensions with expires_at in past', function (): void {
                    // Arrange
                    $expired = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now()->subWeek(),
                        'expires_at' => now()->subDay(),
                    ]);

                    // Act
                    $results = Suspension::query()->expired()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($expired->id);
                });

                test('excludes active suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->expired()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes revoked suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now()->subWeek(),
                        'expires_at' => now()->subDay(),
                        'revoked_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->expired()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes suspensions with null expires_at', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'expires_at' => null,
                    ]);

                    // Act
                    $results = Suspension::query()->expired()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });

            describe('Edge Cases', function (): void {
                test('includes suspensions with expires_at exactly at current time', function (): void {
                    // Arrange
                    $now = now();
                    $expired = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => $now->copy()->subDay(),
                        'expires_at' => $now,
                    ]);

                    // Act
                    $results = Suspension::query()->expired()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($expired->id);
                });
            });
        });

        describe('scopeRevoked', function (): void {
            describe('Happy Paths', function (): void {
                test('includes suspensions with revoked_at set', function (): void {
                    // Arrange
                    $revoked = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'revoked_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->revoked()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($revoked->id);
                });

                test('excludes active suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->revoked()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('includes multiple revoked suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'revoked_at' => now()->subDay(),
                    ]);
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 2,
                        'suspended_at' => now(),
                        'revoked_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->revoked()->get();

                    // Assert
                    expect($results)->toHaveCount(2);
                });
            });
        });

        describe('scopePending', function (): void {
            describe('Happy Paths', function (): void {
                test('includes suspensions with starts_at in future', function (): void {
                    // Arrange
                    $pending = Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'starts_at' => now()->addDay(),
                    ]);

                    // Act
                    $results = Suspension::query()->pending()->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($pending->id);
                });

                test('excludes active suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->pending()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes revoked suspensions', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'starts_at' => now()->addDay(),
                        'revoked_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->pending()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes suspensions with null starts_at', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'starts_at' => null,
                    ]);

                    // Act
                    $results = Suspension::query()->pending()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('excludes suspensions that have already started', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => now(),
                        'starts_at' => now()->subHour(),
                    ]);

                    // Act
                    $results = Suspension::query()->pending()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });

            describe('Edge Cases', function (): void {
                test('excludes suspensions with starts_at exactly at current time', function (): void {
                    // Arrange
                    $now = now();
                    Suspension::query()->create([
                        'context_type' => 'User',
                        'context_id' => 1,
                        'suspended_at' => $now->copy()->subDay(),
                        'starts_at' => $now,
                    ]);

                    // Act
                    $results = Suspension::query()->pending()->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });
        });

        describe('scopeForContext', function (): void {
            describe('Happy Paths', function (): void {
                test('filters suspensions by context model', function (): void {
                    // Arrange
                    $user1 = createUser(['email' => 'user1@example.com']);
                    $user2 = createUser(['email' => 'user2@example.com']);
                    $suspension1 = Suspension::query()->create([
                        'context_type' => $user1->getMorphClass(),
                        'context_id' => $user1->getKey(),
                        'suspended_at' => now(),
                    ]);
                    Suspension::query()->create([
                        'context_type' => $user2->getMorphClass(),
                        'context_id' => $user2->getKey(),
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forContext($user1)->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($suspension1->id);
                });

                test('returns empty collection when no suspensions match context', function (): void {
                    // Arrange
                    $user1 = createUser(['email' => 'user1@example.com']);
                    $user2 = createUser(['email' => 'user2@example.com']);
                    Suspension::query()->create([
                        'context_type' => $user1->getMorphClass(),
                        'context_id' => $user1->getKey(),
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forContext($user2)->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });

                test('filters multiple suspensions for same context', function (): void {
                    // Arrange
                    $user = createUser();
                    Suspension::query()->create([
                        'context_type' => $user->getMorphClass(),
                        'context_id' => $user->getKey(),
                        'suspended_at' => now()->subWeek(),
                    ]);
                    Suspension::query()->create([
                        'context_type' => $user->getMorphClass(),
                        'context_id' => $user->getKey(),
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forContext($user)->get();

                    // Assert
                    expect($results)->toHaveCount(2);
                });
            });
        });

        describe('scopeForMatchType', function (): void {
            describe('Happy Paths', function (): void {
                test('filters suspensions by match_type', function (): void {
                    // Arrange
                    $email = Suspension::query()->create([
                        'match_type' => 'email',
                        'match_value' => 'test@example.com',
                        'suspended_at' => now(),
                    ]);
                    Suspension::query()->create([
                        'match_type' => 'ip',
                        'match_value' => '192.168.1.1',
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forMatchType('email')->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($email->id);
                });

                test('returns empty collection when no suspensions match type', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'match_type' => 'email',
                        'match_value' => 'test@example.com',
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forMatchType('ip')->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });
        });

        describe('scopeForMatchValue', function (): void {
            describe('Happy Paths', function (): void {
                test('filters suspensions by match_type and match_value', function (): void {
                    // Arrange
                    $target = Suspension::query()->create([
                        'match_type' => 'email',
                        'match_value' => 'test@example.com',
                        'suspended_at' => now(),
                    ]);
                    Suspension::query()->create([
                        'match_type' => 'email',
                        'match_value' => 'other@example.com',
                        'suspended_at' => now(),
                    ]);
                    Suspension::query()->create([
                        'match_type' => 'ip',
                        'match_value' => 'test@example.com',
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forMatchValue('email', 'test@example.com')->get();

                    // Assert
                    expect($results)->toHaveCount(1)
                        ->and($results->first()->id)->toBe($target->id);
                });

                test('returns empty collection when no suspensions match type and value', function (): void {
                    // Arrange
                    Suspension::query()->create([
                        'match_type' => 'email',
                        'match_value' => 'test@example.com',
                        'suspended_at' => now(),
                    ]);

                    // Act
                    $results = Suspension::query()->forMatchValue('email', 'other@example.com')->get();

                    // Assert
                    expect($results)->toHaveCount(0);
                });
            });
        });
    });
});
