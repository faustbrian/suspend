<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\IpMatcher;

describe('IpMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new IpMatcher();
    });

    describe('type', function (): void {
        it('returns ip', function (): void {
            expect($this->matcher->type())->toBe('ip');
        });
    });

    describe('matches - IPv4', function (): void {
        it('matches exact IPv4 addresses', function (): void {
            expect($this->matcher->matches('192.168.1.1', '192.168.1.1'))->toBeTrue();
        });

        it('does not match different IPv4 addresses', function (): void {
            expect($this->matcher->matches('192.168.1.1', '192.168.1.2'))->toBeFalse();
        });

        it('matches CIDR /24 range', function (): void {
            expect($this->matcher->matches('192.168.1.0/24', '192.168.1.1'))->toBeTrue();
            expect($this->matcher->matches('192.168.1.0/24', '192.168.1.255'))->toBeTrue();
            expect($this->matcher->matches('192.168.1.0/24', '192.168.2.1'))->toBeFalse();
        });

        it('matches CIDR /16 range', function (): void {
            expect($this->matcher->matches('10.0.0.0/16', '10.0.0.1'))->toBeTrue();
            expect($this->matcher->matches('10.0.0.0/16', '10.0.255.255'))->toBeTrue();
            expect($this->matcher->matches('10.0.0.0/16', '10.1.0.1'))->toBeFalse();
        });

        it('matches CIDR /8 range', function (): void {
            expect($this->matcher->matches('10.0.0.0/8', '10.0.0.1'))->toBeTrue();
            expect($this->matcher->matches('10.0.0.0/8', '10.255.255.255'))->toBeTrue();
            expect($this->matcher->matches('10.0.0.0/8', '11.0.0.1'))->toBeFalse();
        });
    });

    describe('matches - IPv6', function (): void {
        it('matches exact IPv6 addresses', function (): void {
            expect($this->matcher->matches('2001:db8::1', '2001:db8::1'))->toBeTrue();
        });

        it('matches IPv6 CIDR ranges', function (): void {
            expect($this->matcher->matches('2001:db8::/32', '2001:db8::1'))->toBeTrue();
            expect($this->matcher->matches('2001:db8::/32', '2001:db8:ffff::1'))->toBeTrue();
            expect($this->matcher->matches('2001:db8::/32', '2001:db9::1'))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates IPv4 addresses', function (): void {
            expect($this->matcher->validate('192.168.1.1'))->toBeTrue();
            expect($this->matcher->validate('0.0.0.0'))->toBeTrue();
            expect($this->matcher->validate('255.255.255.255'))->toBeTrue();
        });

        it('validates IPv6 addresses', function (): void {
            expect($this->matcher->validate('2001:db8::1'))->toBeTrue();
            expect($this->matcher->validate('::1'))->toBeTrue();
        });

        it('validates CIDR notation', function (): void {
            expect($this->matcher->validate('192.168.1.0/24'))->toBeTrue();
            expect($this->matcher->validate('10.0.0.0/8'))->toBeTrue();
            expect($this->matcher->validate('2001:db8::/32'))->toBeTrue();
        });

        it('rejects invalid addresses', function (): void {
            expect($this->matcher->validate('not-an-ip'))->toBeFalse();
            expect($this->matcher->validate('256.256.256.256'))->toBeFalse();
            expect($this->matcher->validate('192.168.1.0/33'))->toBeFalse();
        });

        it('rejects CIDR with non-numeric bits', function (): void {
            expect($this->matcher->validate('192.168.1.0/abc'))->toBeFalse();
        });

        it('rejects malformed CIDR with multiple slashes', function (): void {
            expect($this->matcher->validate('192.168.1.0/24/16'))->toBeFalse();
        });

        it('rejects IPv6 CIDR with out of range bits', function (): void {
            expect($this->matcher->validate('2001:db8::/129'))->toBeFalse();
        });

        it('rejects CIDR with invalid subnet IP', function (): void {
            expect($this->matcher->validate('999.999.999.999/24'))->toBeFalse();
        });

        it('validates IPv4 CIDR boundary values', function (): void {
            expect($this->matcher->validate('192.168.1.0/0'))->toBeTrue();
            expect($this->matcher->validate('192.168.1.0/32'))->toBeTrue();
        });

        it('validates IPv6 CIDR boundary values', function (): void {
            expect($this->matcher->validate('2001:db8::/0'))->toBeTrue();
            expect($this->matcher->validate('2001:db8::/128'))->toBeTrue();
        });
    });

    describe('extract', function (): void {
        it('returns null for IP addresses', function (): void {
            expect($this->matcher->extract('192.168.1.1'))->toBeNull();
        });
    });

    describe('normalize', function (): void {
        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  192.168.1.1  '))->toBe('192.168.1.1');
        });
    });

    describe('edge cases', function (): void {
        it('handles invalid IPv4 in CIDR match', function (): void {
            expect($this->matcher->matches('192.168.1.0/24', 'invalid-ip'))->toBeFalse();
        });

        it('handles invalid IPv6 in CIDR match', function (): void {
            expect($this->matcher->matches('2001:db8::/32', 'invalid-ip'))->toBeFalse();
        });

        it('handles invalid subnet in IPv4 CIDR', function (): void {
            expect($this->matcher->matches('invalid/24', '192.168.1.1'))->toBeFalse();
        });

        it('handles invalid subnet in IPv6 CIDR', function (): void {
            expect($this->matcher->matches('invalid::/32', '2001:db8::1'))->toBeFalse();
        });

        it('matches IPv6 with partial byte comparison', function (): void {
            // /36 requires comparing partial bytes (4 full bytes + 4 bits)
            expect($this->matcher->matches('2001:db8::/36', '2001:db8:0000::1'))->toBeTrue();
            expect($this->matcher->matches('2001:db8::/36', '2001:db8:f000::1'))->toBeFalse();
        });
    });
});
