<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Events\SuspensionCreated;
use Cline\Suspend\Events\SuspensionLifted;
use Cline\Suspend\Events\SuspensionRevoked;
use Cline\Suspend\Facades\Suspend;
use Illuminate\Support\Facades\Event;

describe('Entity Suspension', function (): void {
    describe('via facade', function (): void {
        it('suspends a user', function (): void {
            $user = createUser();

            $suspension = Suspend::for($user)->suspend('Spam activity');

            expect($suspension)->toBeInstanceOf(Suspension::class);
            expect($suspension->reason)->toBe('Spam activity');
            expect($suspension->context_type)->toBe($user->getMorphClass());
            expect($suspension->context_id)->toBe($user->getKey());
            expect(Suspend::for($user)->isSuspended())->toBeTrue();
        });

        it('checks if user is not suspended', function (): void {
            $user = createUser();

            expect(Suspend::for($user)->isSuspended())->toBeFalse();
            expect(Suspend::for($user)->isNotSuspended())->toBeTrue();
        });

        it('lifts all suspensions', function (): void {
            $user = createUser();
            Suspend::for($user)->suspend('First offense');
            Suspend::for($user)->suspend('Second offense');

            expect(Suspend::for($user)->isSuspended())->toBeTrue();

            $count = Suspend::for($user)->lift();

            expect($count)->toBe(2);
            expect(Suspend::for($user)->isSuspended())->toBeFalse();
        });

        it('suspends with expiration', function (): void {
            $user = createUser();
            $expiresAt = now()->addDay();

            $suspension = Suspend::for($user)->suspend('Temporary ban', $expiresAt);

            expect($suspension->expires_at->toDateTimeString())
                ->toBe($expiresAt->toDateTimeString());
        });

        it('suspends for duration', function (): void {
            $user = createUser();

            $suspension = Suspend::for($user)->suspendFor(
                new DateInterval('P7D'),
                'Week ban',
            );

            expect((int) abs(round($suspension->expires_at->diffInDays(now()))))->toBe(7);
        });

        it('schedules future suspension', function (): void {
            $user = createUser();
            $startsAt = now()->addWeek();

            $suspension = Suspend::for($user)->suspendAt($startsAt, 'Scheduled ban');

            expect($suspension->starts_at->toDateTimeString())
                ->toBe($startsAt->toDateTimeString());
            expect($suspension->isPending())->toBeTrue();
            expect(Suspend::for($user)->isSuspended())->toBeFalse(); // Not active yet
        });

        it('gets suspension history', function (): void {
            $user = createUser();
            Suspend::for($user)->suspend('First');
            Suspend::for($user)->suspend('Second');

            $history = Suspend::for($user)->history();

            expect($history)->toHaveCount(2);
        });

        it('uses strategy', function (): void {
            $user = createUser();

            $suspension = Suspend::for($user)
                ->using('time_window', ['start' => '09:00', 'end' => '17:00'])
                ->suspend('Business hours only');

            expect($suspension->strategy)->toBe('time_window');
            expect($suspension->strategy_metadata)->toBe([
                'start' => '09:00',
                'end' => '17:00',
            ]);
        });
    });

    describe('bulk operations', function (): void {
        it('suspends multiple users at once', function (): void {
            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);
            $user3 = createUser(['email' => 'user3@example.com']);

            $suspensions = Suspend::suspendMany([$user1, $user2, $user3], 'Mass ban');

            expect($suspensions)->toHaveCount(3);
            expect(Suspend::for($user1->fresh())->isSuspended())->toBeTrue();
            expect(Suspend::for($user2->fresh())->isSuspended())->toBeTrue();
            expect(Suspend::for($user3->fresh())->isSuspended())->toBeTrue();
        });

        it('suspends many with expiration', function (): void {
            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);
            $expiresAt = now()->addDay();

            $suspensions = Suspend::suspendMany([$user1, $user2], 'Temp ban', $expiresAt);

            expect($suspensions)->toHaveCount(2);
            expect($suspensions[0]->expires_at->toDateTimeString())
                ->toBe($expiresAt->toDateTimeString());
        });

        it('suspends many with metadata', function (): void {
            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);

            $suspensions = Suspend::suspendMany(
                [$user1, $user2],
                'Batch',
                null,
                ['batch_id' => 'ABC123'],
            );

            expect($suspensions[0]->strategy_metadata)->toBe(['batch_id' => 'ABC123']);
            expect($suspensions[1]->strategy_metadata)->toBe(['batch_id' => 'ABC123']);
        });

        it('revokes multiple users at once', function (): void {
            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);
            Suspend::for($user1)->suspend('Test');
            Suspend::for($user2)->suspend('Test');

            expect(Suspend::for($user1)->isSuspended())->toBeTrue();
            expect(Suspend::for($user2)->isSuspended())->toBeTrue();

            $count = Suspend::revokeMany([$user1, $user2], 'Amnesty');

            expect($count)->toBe(2);
            expect(Suspend::for($user1->fresh())->isSuspended())->toBeFalse();
            expect(Suspend::for($user2->fresh())->isSuspended())->toBeFalse();
        });

        it('returns zero when revoking unsuspended users', function (): void {
            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);

            $count = Suspend::revokeMany([$user1, $user2]);

            expect($count)->toBe(0);
        });

        it('revokes multiple suspensions per user', function (): void {
            $user = createUser();
            Suspend::for($user)->suspend('First');
            Suspend::for($user)->suspend('Second');

            $count = Suspend::revokeMany([$user]);

            expect($count)->toBe(2);
        });
    });

    describe('events', function (): void {
        it('dispatches SuspensionCreated when suspending', function (): void {
            Event::fake([SuspensionCreated::class]);

            $user = createUser();
            Suspend::for($user)->suspend('Test');

            Event::assertDispatched(SuspensionCreated::class, fn ($event): bool => $event->suspension instanceof Suspension);
        });

        it('dispatches SuspensionRevoked when revoking', function (): void {
            $user = createUser();
            $suspension = Suspend::for($user)->suspend('Test');

            Event::fake([SuspensionRevoked::class]);

            $suspension->revoke();

            Event::assertDispatched(SuspensionRevoked::class, fn ($event): bool => $event->suspension->id === $suspension->id);
        });

        it('dispatches SuspensionLifted when lifting all suspensions', function (): void {
            $user = createUser();
            Suspend::for($user)->suspend('Test');

            Event::fake([SuspensionLifted::class]);

            Suspend::for($user)->lift();

            Event::assertDispatched(SuspensionLifted::class, fn ($event): bool => $event->count === 1);
        });

        it('dispatches SuspensionCreated for each bulk suspension', function (): void {
            Event::fake([SuspensionCreated::class]);

            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);

            Suspend::suspendMany([$user1, $user2], 'Bulk');

            Event::assertDispatchedTimes(SuspensionCreated::class, 2);
        });

        it('dispatches SuspensionLifted for each bulk revoke', function (): void {
            $user1 = createUser(['email' => 'user1@example.com']);
            $user2 = createUser(['email' => 'user2@example.com']);
            Suspend::for($user1)->suspend('Test');
            Suspend::for($user2)->suspend('Test');

            Event::fake([SuspensionLifted::class]);

            Suspend::revokeMany([$user1, $user2]);

            Event::assertDispatchedTimes(SuspensionLifted::class, 2);
        });
    });

    describe('relationship', function (): void {
        it('provides suspensions relationship for eager loading', function (): void {
            $user = createUser();
            Suspend::for($user)->suspend('Test 1');
            Suspend::for($user)->suspend('Test 2');

            $suspensions = $user->suspensions;

            expect($suspensions)->toHaveCount(2);
            expect($suspensions->first())->toBeInstanceOf(Suspension::class);
        });

        it('can query through suspensions relationship', function (): void {
            $user = createUser();
            Suspend::for($user)->suspend('Active');
            $revoked = Suspend::for($user)->suspend('Revoked');
            $revoked->revoke();

            $activeSuspensions = $user->suspensions()->active()->get();

            expect($activeSuspensions)->toHaveCount(1);
            expect($activeSuspensions->first()->reason)->toBe('Active');
        });
    });
});
