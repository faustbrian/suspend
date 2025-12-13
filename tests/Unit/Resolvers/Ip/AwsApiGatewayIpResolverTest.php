<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Ip\AwsApiGatewayIpResolver;

describe('AwsApiGatewayIpResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new AwsApiGatewayIpResolver();
    });

    describe('identifier', function (): void {
        it('returns aws', function (): void {
            expect($this->resolver->identifier())->toBe('aws');
        });
    });

    describe('resolve', function (): void {
        it('extracts first IP from X-Forwarded-For header', function (): void {
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50',
            ]);

            expect($this->resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('extracts client IP from comma-separated X-Forwarded-For chain', function (): void {
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50, 198.51.100.1, 192.0.2.1',
            ]);

            expect($this->resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('trims whitespace from extracted IP', function (): void {
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '  203.0.113.50  , 198.51.100.1',
            ]);

            expect($this->resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('falls back to request IP when header is missing', function (): void {
            $request = createRequestWithIp('192.168.1.1');

            expect($this->resolver->resolve($request))->toBe('192.168.1.1');
        });

        it('falls back when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '',
            ]);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            expect($this->resolver->resolve($request))->toBe('192.168.1.1');
        });

        it('falls back when header contains only whitespace', function (): void {
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '   ',
            ]);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            expect($this->resolver->resolve($request))->toBe('192.168.1.1');
        });
    });
});
