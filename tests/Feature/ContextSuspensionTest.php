<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Facades\Suspend;

describe('Context-based Suspension', function (): void {
    describe('email matching', function (): void {
        it('suspends an email address', function (): void {
            $suspension = Suspend::match('email', 'spammer@evil.com')
                ->suspend('Fraudulent activity');

            expect($suspension)->toBeInstanceOf(Suspension::class);
            expect($suspension->match_type)->toBe('email');
            expect($suspension->match_value)->toBe('spammer@evil.com');
        });

        it('normalizes email on suspension', function (): void {
            $suspension = Suspend::match('email', 'SPAMMER@EVIL.COM')
                ->suspend('Test');

            expect($suspension->match_value)->toBe('spammer@evil.com');
        });

        it('checks if email is suspended', function (): void {
            Suspend::match('email', 'blocked@test.com')->suspend('Test');

            expect(Suspend::check()->email('blocked@test.com')->matches())->toBeTrue();
            expect(Suspend::check()->email('allowed@test.com')->matches())->toBeFalse();
        });

        it('matches wildcard email patterns', function (): void {
            Suspend::match('email', '*@spam-domain.com')->suspend('Domain block');

            expect(Suspend::check()->email('anyone@spam-domain.com')->matches())->toBeTrue();
            expect(Suspend::check()->email('user@other-domain.com')->matches())->toBeFalse();
        });

        it('lifts email suspension', function (): void {
            Suspend::match('email', 'temp@test.com')->suspend('Temporary');

            expect(Suspend::check()->email('temp@test.com')->matches())->toBeTrue();

            Suspend::match('email', 'temp@test.com')->lift();

            expect(Suspend::check()->email('temp@test.com')->matches())->toBeFalse();
        });
    });

    describe('phone matching', function (): void {
        it('suspends a phone number', function (): void {
            $suspension = Suspend::match('phone', '+15551234567')
                ->suspend('Fraud calls');

            expect($suspension->match_type)->toBe('phone');
            expect($suspension->match_value)->toBe('+15551234567');
        });

        it('normalizes phone numbers', function (): void {
            $suspension = Suspend::match('phone', '(555) 123-4567')
                ->suspend('Test');

            expect($suspension->match_value)->toBe('5551234567');
        });

        it('checks if phone is suspended', function (): void {
            Suspend::match('phone', '+15559998888')->suspend('Test');

            expect(Suspend::check()->phone('+15559998888')->matches())->toBeTrue();
            expect(Suspend::check()->phone('1-555-999-8888')->matches())->toBeTrue(); // Normalized with country code
        });
    });

    describe('IP matching', function (): void {
        it('suspends an IP address', function (): void {
            $suspension = Suspend::match('ip', '192.168.1.100')
                ->suspend('Abuse');

            expect($suspension->match_type)->toBe('ip');
            expect($suspension->match_value)->toBe('192.168.1.100');
        });

        it('suspends a CIDR range', function (): void {
            Suspend::match('ip', '10.0.0.0/8')->suspend('Private network');

            expect(Suspend::check()->ip('10.0.0.1')->matches())->toBeTrue();
            expect(Suspend::check()->ip('10.255.255.255')->matches())->toBeTrue();
            expect(Suspend::check()->ip('11.0.0.1')->matches())->toBeFalse();
        });
    });

    describe('multiple checks', function (): void {
        it('checks multiple values at once', function (): void {
            Suspend::match('email', 'blocked@test.com')->suspend('Email block');
            Suspend::match('phone', '+15551111111')->suspend('Phone block');

            // Neither matches
            expect(Suspend::check()
                ->email('allowed@test.com')
                ->phone('+15552222222')
                ->matches())->toBeFalse();

            // Email matches
            expect(Suspend::check()
                ->email('blocked@test.com')
                ->phone('+15552222222')
                ->matches())->toBeTrue();

            // Phone matches
            expect(Suspend::check()
                ->email('allowed@test.com')
                ->phone('+15551111111')
                ->matches())->toBeTrue();
        });

        it('returns the first matching suspension', function (): void {
            Suspend::match('email', 'first@test.com')->suspend('First block');
            Suspend::match('email', 'second@test.com')->suspend('Second block');

            $match = Suspend::check()
                ->email('first@test.com')
                ->email('second@test.com')
                ->first();

            expect($match)->not->toBeNull();
            expect($match->reason)->toBe('First block');
        });

        it('returns all matching suspensions', function (): void {
            Suspend::match('email', 'multi1@test.com')->suspend('Block 1');
            Suspend::match('email', 'multi2@test.com')->suspend('Block 2');

            $matches = Suspend::check()
                ->email('multi1@test.com')
                ->email('multi2@test.com')
                ->all();

            expect($matches)->toHaveCount(2);
        });
    });
});
