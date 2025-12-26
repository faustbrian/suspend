<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Ip\AkamaiIpResolver;

describe('AkamaiIpResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new AkamaiIpResolver();
    });

    describe('identifier', function (): void {
        it('returns akamai', function (): void {
            expect($this->resolver->identifier())->toBe('akamai');
        });
    });

    describe('resolve', function (): void {
        it('extracts IP from True-Client-IP header', function (): void {
            $request = createRequestWithHeaders([
                'True-Client-IP' => '203.0.113.50',
            ]);

            expect($this->resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('falls back to request IP when header is missing', function (): void {
            $request = createRequestWithIp('192.168.1.1');

            expect($this->resolver->resolve($request))->toBe('192.168.1.1');
        });

        it('falls back when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'True-Client-IP' => '',
            ]);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            expect($this->resolver->resolve($request))->toBe('192.168.1.1');
        });
    });
});
