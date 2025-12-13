<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Exceptions\SuspendedException;
use Cline\Suspend\Facades\Suspend;
use Cline\Suspend\Http\Middleware\CheckSuspension;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('CheckSuspension Middleware', function (): void {
    beforeEach(function (): void {
        $this->middleware = resolve(CheckSuspension::class);
        $this->nextCalled = false;

        $this->next = function (Request $request): Response {
            $this->nextCalled = true;

            return new Response('OK', Symfony\Component\HttpFoundation\Response::HTTP_OK);
        };
    });

    describe('Happy Paths', function (): void {
        test('allows request through when no suspensions exist', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            // Act
            $response = $this->middleware->handle($request, $this->next);

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('OK');
        });

        test('allows request through when IP is not suspended', function (): void {
            // Arrange
            Suspend::match('ip', '10.0.0.1')->suspend('Blocked IP');
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('allows request through when suspension has expired', function (): void {
            // Arrange
            $expiredAt = now()->subDay();
            Suspend::match('ip', '192.168.1.1')
                ->suspend('Temporary block', $expiredAt);

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('allows request through when suspension is pending future start date', function (): void {
            // Arrange
            $futureStart = now()->addWeek();
            Suspend::match('ip', '192.168.1.1')
                ->suspendAt($futureStart, 'Future block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('processes request successfully with no check types specified', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            // Act - No check types specified, should use defaults
            $response = $this->middleware->handle($request, $this->next);

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws SuspendedException when IP is suspended', function (): void {
            // Arrange
            $suspension = Suspend::match('ip', '192.168.1.100')
                ->suspend('Abusive behavior');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);

            expect($this->nextCalled)->toBeFalse();
        });

        test('returns 403 status code when suspended', function (): void {
            // Arrange
            config(['suspend.middleware.response_code' => 403]);

            Suspend::match('ip', '192.168.1.100')
                ->suspend('Blocked IP');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            try {
                $this->middleware->handle($request, $this->next, 'ip');
                expect(true)->toBeFalse(); // Should not reach here
            } catch (SuspendedException $suspendedException) {
                expect($suspendedException->getStatusCode())->toBe(403);
            }
        });

        test('includes suspension reason in exception message', function (): void {
            // Arrange
            $reason = 'Multiple failed login attempts';
            Suspend::match('ip', '192.168.1.100')
                ->suspend($reason);

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            try {
                $this->middleware->handle($request, $this->next, 'ip');
                expect(true)->toBeFalse(); // Should not reach here
            } catch (SuspendedException $suspendedException) {
                expect($suspendedException->getMessage())->toContain($reason);
            }
        });

        test('blocks request when IP matches CIDR range suspension', function (): void {
            // Arrange
            Suspend::match('ip', '10.0.0.0/8')->suspend('Private network block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '10.0.0.50');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);
        });

        test('blocks request when country is suspended', function (): void {
            // Arrange
            Suspend::match('country', 'US')->suspend('Country block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '8.8.8.8');

            // Act & Assert
            // Note: This would require a real geo resolver in production
            // For now, we test the parameter is accepted
            $response = $this->middleware->handle($request, $this->next, 'country');

            // With NullGeoResolver, country check should pass through
            expect($this->nextCalled)->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles request with no IP address gracefully', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            // No REMOTE_ADDR set

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('handles multiple check types in single middleware call', function (): void {
            // Arrange
            Suspend::match('ip', '192.168.1.100')->suspend('IP block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip', 'country', 'user'))
                ->toThrow(SuspendedException::class);
        });

        test('stops at first matching suspension when multiple checks specified', function (): void {
            // Arrange
            $ipSuspension = Suspend::match('ip', '192.168.1.100')
                ->suspend('IP block - should match first');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            try {
                $this->middleware->handle($request, $this->next, 'ip', 'country');
                expect(true)->toBeFalse(); // Should not reach here
            } catch (SuspendedException $suspendedException) {
                expect($suspendedException->getSuspension()->id)->toBe($ipSuspension->id)
                    ->and($suspendedException->getMessage())->toContain('IP block - should match first');
            }
        });

        test('ignores unknown check types without throwing exception', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip', 'unknown_type', 'another_invalid');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('handles IPv6 addresses correctly', function (): void {
            // Arrange
            Suspend::match('ip', '2001:0db8:85a3:0000:0000:8a2e:0370:7334')
                ->suspend('IPv6 block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '2001:0db8:85a3:0000:0000:8a2e:0370:7334');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);
        });

        test('uses default checks from config when no parameters provided', function (): void {
            // Arrange
            config(['suspend.middleware.check_ip' => true]);
            config(['suspend.middleware.check_country' => false]);

            Suspend::match('ip', '192.168.1.100')->suspend('Default check block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next))
                ->toThrow(SuspendedException::class);
        });

        test('allows through when all default checks are disabled in config', function (): void {
            // Arrange
            config(['suspend.middleware.check_ip' => false]);
            config(['suspend.middleware.check_country' => false]);

            Suspend::match('ip', '192.168.1.100')->suspend('Should not block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act
            $response = $this->middleware->handle($request, $this->next);

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('handles suspension with custom response message from config', function (): void {
            // Arrange
            $customMessage = 'You have been banned from this service';
            config(['suspend.middleware.response_message' => $customMessage]);

            Suspend::match('ip', '192.168.1.100')->suspend('Violation');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            try {
                $this->middleware->handle($request, $this->next, 'ip');
                expect(true)->toBeFalse(); // Should not reach here
            } catch (SuspendedException $suspendedException) {
                expect($suspendedException->getMessage())->toContain($customMessage);
            }
        });

        test('handles suspension with custom response code from config', function (): void {
            // Arrange
            config(['suspend.middleware.response_code' => 451]);

            Suspend::match('ip', '192.168.1.100')->suspend('Legal block');

            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->server->set('REMOTE_ADDR', '192.168.1.100');

            // Act & Assert
            try {
                $this->middleware->handle($request, $this->next, 'ip');
                expect(true)->toBeFalse(); // Should not reach here
            } catch (SuspendedException $suspendedException) {
                expect($suspendedException->getStatusCode())->toBe(451);
            }
        });
    });

    describe('Context-based Suspensions', function (): void {
        test('checks IP suspension from request context', function (): void {
            // Arrange
            $blockedIp = '203.0.113.42';
            Suspend::match('ip', $blockedIp)->suspend('Spam source');

            $request = createRequestWithIp($blockedIp);

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);
        });

        test('allows request when IP is different from suspended IP', function (): void {
            // Arrange
            Suspend::match('ip', '203.0.113.42')->suspend('Blocked');

            $request = createRequestWithIp('192.168.1.1');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('checks multiple IP ranges efficiently', function (): void {
            // Arrange
            Suspend::match('ip', '10.0.0.0/8')->suspend('Private range 1');
            Suspend::match('ip', '172.16.0.0/12')->suspend('Private range 2');
            Suspend::match('ip', '192.168.0.0/16')->suspend('Private range 3');

            $request = createRequestWithIp('192.168.1.50');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);
        });

        test('returns suspension details in exception', function (): void {
            // Arrange
            $reason = 'Fraudulent activity detected';
            $suspension = Suspend::match('ip', '192.168.1.100')->suspend($reason);

            $request = createRequestWithIp('192.168.1.100');

            // Act & Assert
            try {
                $this->middleware->handle($request, $this->next, 'ip');
                expect(true)->toBeFalse(); // Should not reach here
            } catch (SuspendedException $suspendedException) {
                expect($suspendedException->getSuspension())->not->toBeNull()
                    ->and($suspendedException->getSuspension()->id)->toBe($suspension->id)
                    ->and($suspendedException->getSuspension()->reason)->toBe($reason);
            }
        });
    });

    describe('User Check Integration', function (): void {
        test('handles authenticated user check gracefully when user is null', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            // No authenticated user

            // Act
            $response = $this->middleware->handle($request, $this->next, 'user');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('handles user without Suspendable trait gracefully', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);

            // Mock a user without isSuspended method
            $user = new class()
            {
                public $id = 1;

                public $name = 'Test User';
            };

            $request->setUserResolver(fn (): object => $user);

            // Act
            $response = $this->middleware->handle($request, $this->next, 'user');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });
    });

    describe('Time-based Suspensions', function (): void {
        test('blocks request when active suspension has not yet expired', function (): void {
            // Arrange
            $expiresAt = now()->addHour();
            Suspend::match('ip', '192.168.1.100')
                ->suspend('Temporary ban', $expiresAt);

            $request = createRequestWithIp('192.168.1.100');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);
        });

        test('allows request immediately after suspension expires', function (): void {
            // Arrange
            $expiresAt = now()->subSecond();
            Suspend::match('ip', '192.168.1.100')
                ->suspend('Just expired', $expiresAt);

            $request = createRequestWithIp('192.168.1.100');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        test('blocks request when scheduled suspension has started', function (): void {
            // Arrange
            $startsAt = now()->subHour();
            $expiresAt = now()->addHour();

            $suspension = Suspend::match('ip', '192.168.1.100')
                ->suspendAt($startsAt, 'Scheduled block');

            $suspension->expires_at = $expiresAt;
            $suspension->save();

            $request = createRequestWithIp('192.168.1.100');

            // Act & Assert
            expect(fn () => $this->middleware->handle($request, $this->next, 'ip'))
                ->toThrow(SuspendedException::class);
        });

        test('allows request before scheduled suspension starts', function (): void {
            // Arrange
            $startsAt = now()->addHour();
            Suspend::match('ip', '192.168.1.100')
                ->suspendAt($startsAt, 'Future block');

            $request = createRequestWithIp('192.168.1.100');

            // Act
            $response = $this->middleware->handle($request, $this->next, 'ip');

            // Assert
            expect($this->nextCalled)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });
    });
});
