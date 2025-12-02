<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\EmailMatcher;

describe('EmailMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new EmailMatcher();
    });

    describe('type', function (): void {
        it('returns email', function (): void {
            expect($this->matcher->type())->toBe('email');
        });
    });

    describe('normalize', function (): void {
        it('lowercases email addresses', function (): void {
            expect($this->matcher->normalize('USER@EXAMPLE.COM'))->toBe('user@example.com');
        });

        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  user@example.com  '))->toBe('user@example.com');
        });
    });

    describe('matches', function (): void {
        it('matches exact emails', function (): void {
            expect($this->matcher->matches('user@example.com', 'user@example.com'))->toBeTrue();
        });

        it('matches case-insensitively', function (): void {
            expect($this->matcher->matches('user@example.com', 'USER@EXAMPLE.COM'))->toBeTrue();
        });

        it('does not match different emails', function (): void {
            expect($this->matcher->matches('user@example.com', 'other@example.com'))->toBeFalse();
        });

        it('matches wildcard domain patterns', function (): void {
            expect($this->matcher->matches('*@example.com', 'anyone@example.com'))->toBeTrue();
            expect($this->matcher->matches('*@example.com', 'user@other.com'))->toBeFalse();
        });

        it('matches wildcard user patterns', function (): void {
            expect($this->matcher->matches('admin@*', 'admin@example.com'))->toBeTrue();
            expect($this->matcher->matches('admin@*', 'admin@other.org'))->toBeTrue();
            expect($this->matcher->matches('admin@*', 'user@example.com'))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates correct emails', function (): void {
            expect($this->matcher->validate('user@example.com'))->toBeTrue();
            expect($this->matcher->validate('test.user+tag@sub.example.org'))->toBeTrue();
        });

        it('rejects invalid emails', function (): void {
            expect($this->matcher->validate('not-an-email'))->toBeFalse();
            expect($this->matcher->validate('@example.com'))->toBeFalse();
        });

        it('validates wildcard patterns', function (): void {
            expect($this->matcher->validate('*@example.com'))->toBeTrue();
            expect($this->matcher->validate('admin@*'))->toBeTrue();
        });

        it('rejects patterns with multiple @ symbols', function (): void {
            expect($this->matcher->validate('user@domain@extra.com'))->toBeFalse();
        });
    });

    describe('extract', function (): void {
        it('extracts domain from email', function (): void {
            expect($this->matcher->extract('user@example.com'))->toBe('example.com');
        });

        it('returns null for invalid email', function (): void {
            expect($this->matcher->extract('not-an-email'))->toBeNull();
        });
    });
});
