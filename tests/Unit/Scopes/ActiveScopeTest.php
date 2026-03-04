<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Facades\Suspend;
use Cline\Suspend\Scopes\ActiveScope;
use Tests\Fixtures\Models\User;

describe('ActiveScope', function (): void {
    beforeEach(function (): void {
        $this->scope = new ActiveScope();
        $this->user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    });

    describe('Happy Paths', function (): void {
        test('applies whereNull constraint on revoked_at to filter active suspensions', function (): void {
            // Arrange
            $query = Suspension::query();
            $model = new Suspension();

            // Act
            $this->scope->apply($query, $model);

            // Assert
            expect($query->toSql())->toContain('"revoked_at" is null');
        });

        test('filters out revoked suspensions from query results', function (): void {
            // Arrange
            $activeSuspension = Suspend::for($this->user)->suspend('Active suspension');
            $revokedSuspension = Suspend::for($this->user)->suspend('Revoked suspension');
            $revokedSuspension->update(['revoked_at' => now()]);

            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert
            expect($suspensions)->toHaveCount(1)
                ->and($suspensions->first()->id)->toBe($activeSuspension->id)
                ->and($suspensions->first()->reason)->toBe('Active suspension');
        });

        test('includes all suspensions when none have revoked_at set', function (): void {
            // Arrange
            Suspend::for($this->user)->suspend('First suspension');
            Suspend::for($this->user)->suspend('Second suspension');

            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert
            expect($suspensions)->toHaveCount(2);
        });

        test('returns empty result when all suspensions are revoked', function (): void {
            // Arrange
            $suspension1 = Suspend::for($this->user)->suspend('First');
            $suspension2 = Suspend::for($this->user)->suspend('Second');
            $suspension1->update(['revoked_at' => now()]);
            $suspension2->update(['revoked_at' => now()]);

            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert
            expect($suspensions)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns empty result when no suspensions exist in database', function (): void {
            // Arrange
            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert
            expect($suspensions)->toBeEmpty();
        });

        test('includes suspension with null revoked_at value', function (): void {
            // Arrange
            $suspension = Suspend::for($this->user)->suspend('Test');
            expect($suspension->revoked_at)->toBeNull();

            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert
            expect($suspensions)->toHaveCount(1)
                ->and($suspensions->first()->revoked_at)->toBeNull();
        });

        test('excludes suspension with future revoked_at timestamp', function (): void {
            // Arrange
            $activeSuspension = Suspend::for($this->user)->suspend('Active');
            $futureRevokedSuspension = Suspend::for($this->user)->suspend('Future revoked');
            $futureRevokedSuspension->update(['revoked_at' => now()->addDay()]);

            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert - scope checks for null, so any non-null value excludes it
            expect($suspensions)->toHaveCount(1)
                ->and($suspensions->first()->id)->toBe($activeSuspension->id);
        });

        test('combines with other query constraints properly', function (): void {
            // Arrange
            $user2 = User::query()->create(['name' => 'User 2', 'email' => 'user2@example.com']);
            $activeSuspension1 = Suspend::for($this->user)->suspend('User 1 active');
            $revokedSuspension1 = Suspend::for($this->user)->suspend('User 1 revoked');
            $activeSuspension2 = Suspend::for($user2)->suspend('User 2 active');
            $revokedSuspension1->update(['revoked_at' => now()]);

            $query = Suspension::query()->where('context_id', $this->user->id);
            $this->scope->apply($query, new Suspension());

            // Act
            $suspensions = $query->get();

            // Assert
            expect($suspensions)->toHaveCount(1)
                ->and($suspensions->first()->id)->toBe($activeSuspension1->id);
        });

        test('works with count queries without loading models', function (): void {
            // Arrange
            Suspend::for($this->user)->suspend('Active');
            $revoked = Suspend::for($this->user)->suspend('Revoked');
            $revoked->update(['revoked_at' => now()]);

            $query = Suspension::query();
            $this->scope->apply($query, new Suspension());

            // Act
            $count = $query->count();

            // Assert
            expect($count)->toBe(1);
        });
    });
});
