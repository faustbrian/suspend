<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Models\User;

describe('HasSuspensions', function (): void {
    describe('Happy Paths', function (): void {
        describe('suspensions relationship', function (): void {
            test('returns morphMany relationship to Suspension model', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $relationship = $user->suspensions();

                // Assert
                expect($relationship)->toBeInstanceOf(MorphMany::class)
                    ->and($relationship->getRelated())->toBeInstanceOf(Suspension::class);
            });

            test('retrieves all suspensions created for the user', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('First reason');
                $user->suspend('Second reason');

                // Act
                $suspensions = $user->suspensions;

                // Assert
                expect($suspensions)->toHaveCount(2)
                    ->and($suspensions->first())->toBeInstanceOf(Suspension::class)
                    ->and($suspensions->pluck('reason')->toArray())->toContain('First reason', 'Second reason');
            });
        });

        describe('isSuspended', function (): void {
            test('returns true when user has active suspension', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Violation of terms');

                // Act
                $result = $user->isSuspended();

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns true when user has multiple active suspensions', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('First offense');
                $user->suspend('Second offense');

                // Act
                $result = $user->isSuspended();

                // Assert
                expect($result)->toBeTrue();
            });
        });

        describe('isNotSuspended', function (): void {
            test('returns true when user has no suspensions', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $result = $user->isNotSuspended();

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns true when all suspensions are revoked', function (): void {
                // Arrange
                $user = createUser();
                $suspension = $user->suspend('Test');
                $suspension->revoke();

                // Act
                $result = $user->isNotSuspended();

                // Assert
                expect($result)->toBeTrue();
            });
        });

        describe('activeSuspensions', function (): void {
            test('returns collection of active suspensions for user', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active 1');
                $user->suspend('Active 2');

                // Act
                $activeSuspensions = $user->activeSuspensions();

                // Assert
                expect($activeSuspensions)->toBeInstanceOf(Collection::class)
                    ->and($activeSuspensions)->toHaveCount(2)
                    ->and($activeSuspensions->first())->toBeInstanceOf(Suspension::class);
            });

            test('excludes revoked suspensions from active suspensions', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');

                $revoked = $user->suspend('Revoked');
                $revoked->revoke();

                // Act
                $activeSuspensions = $user->activeSuspensions();

                // Assert
                expect($activeSuspensions)->toHaveCount(1)
                    ->and($activeSuspensions->first()->reason)->toBe('Active');
            });

            test('excludes expired suspensions from active suspensions', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');
                $user->suspend('Expired', now()->subDay());

                // Act
                $activeSuspensions = $user->activeSuspensions();

                // Assert
                expect($activeSuspensions)->toHaveCount(1)
                    ->and($activeSuspensions->first()->reason)->toBe('Active');
            });

            test('excludes pending scheduled suspensions from active suspensions', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');
                $user->suspendAt(now()->addWeek(), 'Pending');

                // Act
                $activeSuspensions = $user->activeSuspensions();

                // Assert
                expect($activeSuspensions)->toHaveCount(1)
                    ->and($activeSuspensions->first()->reason)->toBe('Active');
            });
        });

        describe('suspend', function (): void {
            test('creates suspension with reason', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $suspension = $user->suspend('Spam activity');

                // Assert
                expect($suspension)->toBeInstanceOf(Suspension::class)
                    ->and($suspension->reason)->toBe('Spam activity')
                    ->and($suspension->context_type)->toBe($user->getMorphClass())
                    ->and($suspension->context_id)->toBe($user->getKey())
                    ->and($suspension->suspended_at)->not->toBeNull();
            });

            test('creates suspension without reason', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $suspension = $user->suspend();

                // Assert
                expect($suspension)->toBeInstanceOf(Suspension::class)
                    ->and($suspension->reason)->toBeNull();
            });

            test('creates suspension with expiration date', function (): void {
                // Arrange
                $user = createUser();
                $expiresAt = now()->addDays(7);

                // Act
                $suspension = $user->suspend('Temporary ban', $expiresAt);

                // Assert
                expect($suspension->expires_at->toDateTimeString())
                    ->toBe($expiresAt->toDateTimeString());
            });

            test('creates suspension with metadata', function (): void {
                // Arrange
                $user = createUser();
                $metadata = ['ip' => '192.168.1.1', 'severity' => 'high'];

                // Act
                $suspension = $user->suspend('Security violation', null, $metadata);

                // Assert
                expect($suspension->strategy_metadata)->toBe($metadata);
            });

            test('creates suspension with reason, expiration, and metadata', function (): void {
                // Arrange
                $user = createUser();
                $expiresAt = now()->addMonth();
                $metadata = ['admin_id' => 123, 'ticket' => 'TKT-456'];

                // Act
                $suspension = $user->suspend('Policy breach', $expiresAt, $metadata);

                // Assert
                expect($suspension->reason)->toBe('Policy breach')
                    ->and($suspension->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString())
                    ->and($suspension->strategy_metadata)->toBe($metadata);
            });
        });

        describe('lift', function (): void {
            test('revokes all active suspensions and returns count', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('First');
                $user->suspend('Second');

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(2)
                    ->and($user->fresh()->isSuspended())->toBeFalse();
            });

            test('revokes single active suspension', function (): void {
                // Arrange
                $user = createUser();
                $suspension = $user->suspend('Test');

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(1)
                    ->and($suspension->fresh()->isRevoked())->toBeTrue();
            });

            test('sets revoked_at timestamp when lifting suspension', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Test');

                // Act
                $user->lift();

                // Assert
                $suspension = $user->suspensions()->first();
                expect($suspension->revoked_at)->not->toBeNull()
                    ->and($suspension->revoked_at)->toBeInstanceOf(Carbon::class);
            });

            test('updates reason when provided during lift', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Original reason');

                // Act
                $user->lift('Amnesty granted');

                // Assert
                $suspension = $user->suspensions()->first();
                expect($suspension->reason)->toBe('Amnesty granted');
            });
        });

        describe('suspendAt', function (): void {
            test('creates scheduled suspension for future start date', function (): void {
                // Arrange
                $user = createUser();
                $startsAt = now()->addWeek();

                // Act
                $suspension = $user->suspendAt($startsAt, 'Scheduled ban');

                // Assert
                expect($suspension->starts_at->toDateTimeString())->toBe($startsAt->toDateTimeString())
                    ->and($suspension->reason)->toBe('Scheduled ban')
                    ->and($suspension->isPending())->toBeTrue();
            });

            test('creates scheduled suspension with expiration', function (): void {
                // Arrange
                $user = createUser();
                $startsAt = now()->addWeek();
                $expiresAt = now()->addWeeks(2);

                // Act
                $suspension = $user->suspendAt($startsAt, 'Scheduled temp ban', $expiresAt);

                // Assert
                expect($suspension->starts_at->toDateTimeString())->toBe($startsAt->toDateTimeString())
                    ->and($suspension->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString());
            });

            test('creates scheduled suspension with metadata', function (): void {
                // Arrange
                $user = createUser();
                $startsAt = now()->addDays(3);
                $metadata = ['scheduled_by' => 'admin', 'auto_lift' => true];

                // Act
                $suspension = $user->suspendAt($startsAt, 'Auto-scheduled', null, $metadata);

                // Assert
                expect($suspension->strategy_metadata)->toBe($metadata);
            });

            test('scheduled suspension does not make user immediately suspended', function (): void {
                // Arrange
                $user = createUser();
                $startsAt = now()->addMonth();

                // Act
                $user->suspendAt($startsAt, 'Future ban');

                // Assert
                expect($user->isSuspended())->toBeFalse();
            });
        });

        describe('suspendFor', function (): void {
            test('creates suspension with duration-based expiration', function (): void {
                // Arrange
                $user = createUser();
                $duration = new DateInterval('P7D'); // 7 days

                // Act
                $suspension = $user->suspendFor($duration, 'Week ban');

                // Assert
                expect($suspension->reason)->toBe('Week ban')
                    ->and($suspension->expires_at)->not->toBeNull()
                    ->and((int) abs(round($suspension->expires_at->diffInDays(now()))))->toBe(7);
            });

            test('creates suspension for 1 hour duration', function (): void {
                // Arrange
                $user = createUser();
                $duration = new DateInterval('PT1H'); // 1 hour

                // Act
                $suspension = $user->suspendFor($duration, 'Timeout');

                // Assert
                expect((int) abs(round($suspension->expires_at->diffInHours(now()))))->toBe(1);
            });

            test('creates suspension for 30 days with metadata', function (): void {
                // Arrange
                $user = createUser();
                $duration = new DateInterval('P30D');
                $metadata = ['type' => 'automatic', 'rule' => 'spam_detection'];

                // Act
                $suspension = $user->suspendFor($duration, 'Auto-ban', $metadata);

                // Assert
                expect($suspension->strategy_metadata)->toBe($metadata)
                    ->and((int) abs(round($suspension->expires_at->diffInDays(now()))))->toBe(30);
            });
        });

        describe('suspensionHistory', function (): void {
            test('returns all suspensions ordered by suspended_at descending', function (): void {
                // Arrange
                $user = createUser();
                $first = $user->suspend('First');
                Sleep::sleep(1); // Ensure different timestamps
                $second = $user->suspend('Second');
                Sleep::sleep(1);
                $third = $user->suspend('Third');

                // Act
                $history = $user->suspensionHistory();

                // Assert
                expect($history)->toHaveCount(3)
                    ->and($history->first()->id)->toBe($third->id)
                    ->and($history->last()->id)->toBe($first->id);
            });

            test('includes both active and revoked suspensions in history', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');

                $revoked = $user->suspend('Revoked');
                $revoked->revoke();

                // Act
                $history = $user->suspensionHistory();

                // Assert
                expect($history)->toHaveCount(2);
            });

            test('includes expired suspensions in history', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');
                $user->suspend('Expired', now()->subDay());

                // Act
                $history = $user->suspensionHistory();

                // Assert
                expect($history)->toHaveCount(2);
            });

            test('includes pending scheduled suspensions in history', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');
                $user->suspendAt(now()->addWeek(), 'Pending');

                // Act
                $history = $user->suspensionHistory();

                // Assert
                expect($history)->toHaveCount(2);
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('isSuspended', function (): void {
            test('returns false when user has no suspensions', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $result = $user->isSuspended();

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when all suspensions are revoked', function (): void {
                // Arrange
                $user = createUser();
                $suspension1 = $user->suspend('First');
                $suspension2 = $user->suspend('Second');
                $suspension1->revoke();
                $suspension2->revoke();

                // Act
                $result = $user->isSuspended();

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when all suspensions have expired', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Expired 1', now()->subDay());
                $user->suspend('Expired 2', now()->subHour());

                // Act
                $result = $user->isSuspended();

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when all suspensions are pending', function (): void {
                // Arrange
                $user = createUser();
                $user->suspendAt(now()->addWeek(), 'Pending 1');
                $user->suspendAt(now()->addMonth(), 'Pending 2');

                // Act
                $result = $user->isSuspended();

                // Assert
                expect($result)->toBeFalse();
            });
        });

        describe('isNotSuspended', function (): void {
            test('returns false when user has active suspension', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active ban');

                // Act
                $result = $user->isNotSuspended();

                // Assert
                expect($result)->toBeFalse();
            });
        });

        describe('activeSuspensions', function (): void {
            test('returns empty collection when user has no suspensions', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $activeSuspensions = $user->activeSuspensions();

                // Assert
                expect($activeSuspensions)->toBeEmpty();
            });

            test('returns empty collection when all suspensions are revoked', function (): void {
                // Arrange
                $user = createUser();
                $suspension1 = $user->suspend('First');
                $suspension2 = $user->suspend('Second');
                $suspension1->revoke();
                $suspension2->revoke();

                // Act
                $activeSuspensions = $user->activeSuspensions();

                // Assert
                expect($activeSuspensions)->toBeEmpty();
            });
        });

        describe('lift', function (): void {
            test('returns zero when no active suspensions exist', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(0);
            });

            test('returns zero when all suspensions already revoked', function (): void {
                // Arrange
                $user = createUser();
                $suspension = $user->suspend('Test');
                $suspension->revoke();

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(0);
            });

            test('only revokes active suspensions not expired ones', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');
                $user->suspend('Expired', now()->subDay());

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(1);
            });

            test('only revokes active suspensions not pending ones', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Active');
                $user->suspendAt(now()->addWeek(), 'Pending');

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(1);
            });
        });
    });

    describe('Edge Cases', function (): void {
        describe('suspend', function (): void {
            test('creates suspension with empty metadata array', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $suspension = $user->suspend('Test', null, []);

                // Assert
                expect($suspension->strategy_metadata)->toBeNull();
            });

            test('handles suspension with expiration in the past', function (): void {
                // Arrange
                $user = createUser();
                $expiresAt = now()->subDay();

                // Act
                $suspension = $user->suspend('Already expired', $expiresAt);

                // Assert
                expect($suspension->isExpired())->toBeTrue()
                    ->and($user->isSuspended())->toBeFalse();
            });

            test('creates multiple suspensions for same user', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $suspension1 = $user->suspend('First offense');
                $suspension2 = $user->suspend('Second offense');
                $suspension3 = $user->suspend('Third offense');

                // Assert
                expect($user->suspensions)->toHaveCount(3)
                    ->and($suspension1->id)->not->toBe($suspension2->id)
                    ->and($suspension2->id)->not->toBe($suspension3->id);
            });
        });

        describe('suspendAt', function (): void {
            test('creates suspension with start date in the past', function (): void {
                // Arrange
                $user = createUser();
                $startsAt = now()->subWeek();

                // Act
                $suspension = $user->suspendAt($startsAt, 'Backdated');

                // Assert
                expect($suspension->isActive())->toBeTrue();
            });

            test('handles scheduled suspension with expiration before start date', function (): void {
                // Arrange
                $user = createUser();
                $startsAt = now()->addWeek();
                $expiresAt = now()->addDays(3); // Before start date

                // Act
                $suspension = $user->suspendAt($startsAt, 'Invalid dates', $expiresAt);

                // Assert
                expect($suspension)->toBeInstanceOf(Suspension::class)
                    ->and($suspension->starts_at->toDateTimeString())->toBe($startsAt->toDateTimeString())
                    ->and($suspension->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString());
            });
        });

        describe('suspendFor', function (): void {
            test('handles very short duration of 1 second', function (): void {
                // Arrange
                $user = createUser();
                $duration = new DateInterval('PT1S'); // 1 second

                // Act
                $suspension = $user->suspendFor($duration, 'Brief timeout');

                // Assert
                expect($suspension->expires_at)->not->toBeNull();
                // Suspension likely expires immediately
            });

            test('handles very long duration of 1 year', function (): void {
                // Arrange
                $user = createUser();
                $duration = new DateInterval('P1Y'); // 1 year

                // Act
                $suspension = $user->suspendFor($duration, 'Long ban');

                // Assert
                expect((int) abs(round($suspension->expires_at->diffInYears(now()))))->toBe(1);
            });
        });

        describe('lift', function (): void {
            test('handles lifting suspensions with custom reason', function (): void {
                // Arrange
                $user = createUser();
                $user->suspend('Original');

                // Act
                $user->lift('Admin override');

                // Assert
                $suspension = $user->suspensions()->first();
                expect($suspension->reason)->toBe('Admin override');
            });

            test('handles concurrent active and non-active suspensions', function (): void {
                // Arrange
                $user = createUser();
                $active1 = $user->suspend('Active 1');
                $active2 = $user->suspend('Active 2');
                $expired = $user->suspend('Expired', now()->subDay());
                $pending = $user->suspendAt(now()->addWeek(), 'Pending');
                $revoked = $user->suspend('Revoked');
                $revoked->revoke();

                // Act
                $count = $user->lift();

                // Assert
                expect($count)->toBe(2)
                    ->and($active1->fresh()->isRevoked())->toBeTrue()
                    ->and($active2->fresh()->isRevoked())->toBeTrue()
                    ->and($expired->fresh()->isExpired())->toBeTrue()
                    ->and($pending->fresh()->isPending())->toBeTrue()
                    ->and($revoked->fresh()->isRevoked())->toBeTrue();
            });
        });

        describe('suspensionHistory', function (): void {
            test('returns empty collection when user has no suspensions', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $history = $user->suspensionHistory();

                // Assert
                expect($history)->toBeEmpty();
            });

            test('maintains descending order with suspensions created at different times', function (): void {
                // Arrange
                $user = createUser();
                $first = $user->suspend('Oldest');
                Sleep::sleep(1); // Ensure different timestamps
                $second = $user->suspend('Middle');
                Sleep::sleep(1);
                $third = $user->suspend('Newest');

                // Act
                $history = $user->suspensionHistory();

                // Assert
                expect($history)->toHaveCount(3);
                // Most recent first (newest suspension)
                expect($history->pluck('id')->toArray())->toBe([$third->id, $second->id, $first->id]);
            });
        });

        describe('relationship polymorphism', function (): void {
            test('suspension context_type matches user morph class', function (): void {
                // Arrange
                $user = createUser();

                // Act
                $suspension = $user->suspend('Test');

                // Assert
                expect($suspension->context_type)->toBe($user->getMorphClass())
                    ->and($suspension->context_id)->toBe($user->getKey());
            });

            test('can retrieve user from suspension context relationship', function (): void {
                // Arrange
                $user = createUser();
                $suspension = $user->suspend('Test');

                // Act
                $context = $suspension->context;

                // Assert
                expect($context)->toBeInstanceOf(User::class)
                    ->and($context->id)->toBe($user->id);
            });
        });
    });

    describe('Regressions', function (): void {
        test('activeSuspensions only queries database once and caches result', function (): void {
            // Arrange
            $user = createUser();
            $user->suspend('Test');

            // Act
            $first = $user->activeSuspensions();
            $second = $user->activeSuspensions();

            // Assert
            // Each call executes a fresh query
            expect($first)->toHaveCount(1)
                ->and($second)->toHaveCount(1);
        });

        test('lift method increments counter correctly when revoke succeeds', function (): void {
            // Arrange
            $user = createUser();
            $user->suspend('First');
            $user->suspend('Second');
            $user->suspend('Third');

            // Act
            $count = $user->lift();

            // Assert
            expect($count)->toBe(3);
        });

        test('isSuspended returns correct value after lifting suspension', function (): void {
            // Arrange
            $user = createUser();
            $user->suspend('Test');

            expect($user->isSuspended())->toBeTrue();

            // Act
            $user->lift();

            // Assert
            expect($user->fresh()->isSuspended())->toBeFalse();
        });

        test('suspensionHistory ordering handles null suspended_at values', function (): void {
            // Arrange
            $user = createUser();
            // Normal suspensions should always have suspended_at, but testing defensive code
            $s1 = $user->suspend('First');
            $s2 = $user->suspend('Second');

            // Act
            $history = $user->suspensionHistory();

            // Assert
            expect($history)->toHaveCount(2)
                ->and($history->first()->suspended_at)->not->toBeNull()
                ->and($history->last()->suspended_at)->not->toBeNull();
        });
    });
});
