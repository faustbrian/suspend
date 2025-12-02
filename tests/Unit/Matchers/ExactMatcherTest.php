<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\ExactMatcher;

describe('ExactMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new ExactMatcher();
    });

    describe('type', function (): void {
        it('returns exact', function (): void {
            expect($this->matcher->type())->toBe('exact');
        });
    });

    describe('normalize', function (): void {
        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  test  '))->toBe('test');
        });

        it('preserves case', function (): void {
            expect($this->matcher->normalize('TeSt'))->toBe('TeSt');
        });

        it('preserves special characters', function (): void {
            expect($this->matcher->normalize('test@123#'))->toBe('test@123#');
        });

        it('converts to string', function (): void {
            expect($this->matcher->normalize(12_345))->toBe('12345');
        });
    });

    describe('matches', function (): void {
        it('matches exact strings', function (): void {
            expect($this->matcher->matches('test', 'test'))->toBeTrue();
            expect($this->matcher->matches('hello world', 'hello world'))->toBeTrue();
        });

        it('matches case-sensitively', function (): void {
            expect($this->matcher->matches('test', 'Test'))->toBeFalse();
            expect($this->matcher->matches('TEST', 'test'))->toBeFalse();
        });

        it('does not match different strings', function (): void {
            expect($this->matcher->matches('test', 'example'))->toBeFalse();
        });

        it('trims whitespace before matching', function (): void {
            expect($this->matcher->matches('test', '  test  '))->toBeTrue();
            expect($this->matcher->matches('  test  ', 'test'))->toBeTrue();
        });

        it('matches numeric strings', function (): void {
            expect($this->matcher->matches('12345', '12345'))->toBeTrue();
            expect($this->matcher->matches('12345', 12_345))->toBeTrue();
        });

        it('matches strings with special characters', function (): void {
            expect($this->matcher->matches('test@123#', 'test@123#'))->toBeTrue();
            expect($this->matcher->matches('order-123-abc', 'order-123-abc'))->toBeTrue();
        });

        it('matches UUIDs', function (): void {
            $uuid = '550e8400-e29b-41d4-a716-446655440000';

            expect($this->matcher->matches($uuid, $uuid))->toBeTrue();
        });

        it('matches account numbers', function (): void {
            expect($this->matcher->matches('ACC-2024-001', 'ACC-2024-001'))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('validates non-empty strings', function (): void {
            expect($this->matcher->validate('test'))->toBeTrue();
            expect($this->matcher->validate('12345'))->toBeTrue();
            expect($this->matcher->validate('test@123'))->toBeTrue();
        });

        it('rejects empty strings', function (): void {
            expect($this->matcher->validate(''))->toBeFalse();
        });

        it('rejects whitespace-only strings', function (): void {
            expect($this->matcher->validate('   '))->toBeFalse();
        });

        it('validates single characters', function (): void {
            expect($this->matcher->validate('a'))->toBeTrue();
            expect($this->matcher->validate('1'))->toBeTrue();
        });

        it('validates long strings', function (): void {
            expect($this->matcher->validate(str_repeat('a', 1_000)))->toBeTrue();
        });

        it('validates special characters', function (): void {
            expect($this->matcher->validate('!@#$%^&*()'))->toBeTrue();
        });

        it('validates numeric values', function (): void {
            expect($this->matcher->validate(12_345))->toBeTrue();
            expect($this->matcher->validate(0))->toBeTrue();
        });
    });

    describe('extract', function (): void {
        it('returns null for exact values', function (): void {
            expect($this->matcher->extract('test'))->toBeNull();
            expect($this->matcher->extract('12345'))->toBeNull();
            expect($this->matcher->extract('order-123-abc'))->toBeNull();
        });
    });
});
