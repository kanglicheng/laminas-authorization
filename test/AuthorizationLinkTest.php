<?php
/**
 * Copyright (c) 2019, 2020 Ali Cherry
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Date: 2020-06-02
 * Time: 12:37
 */
declare(strict_types=1);

namespace AliChry\Laminas\Authorization\Test;

use AliChry\Laminas\AccessControl\ListAdapterInterface;
use AliChry\Laminas\AccessControl\Status;
use AliChry\Laminas\Authorization\AuthorizationException;
use AliChry\Laminas\Authorization\AuthorizationLink;
use Laminas\Authentication\AuthenticationServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthorizationLinkTest extends TestCase
{
    public function testName()
    {
        $constructorName = 'auth-1';
        $setterName = 'auth-2';

        $link = new AuthorizationLink(
            $constructorName,
            $this->mockAuthService(),
            $this->mockListAdapter()
        );

        $this->assertEquals(
            $constructorName,
            $link->getName()
        );

        $link->setName($setterName);
        $this->assertEquals(
            $setterName,
            $link->getName()
        );
    }

    public function testAuthService()
    {
        $constructorService = $this->mockAuthService();
        $setterService = $this->mockAuthService();
        $link = new AuthorizationLink(
            '',
            $constructorService,
            $this->mockListAdapter()
        );

        $this->assertSame(
            $constructorService,
            $link->getAuthenticationService()
        );

        $link->setAuthenticationService($setterService);

        $this->assertSame(
            $setterService,
            $link->getAuthenticationService()
        );
    }

    public function testListAdapter()
    {
        $constructorAdapter = $this->mockListAdapter();
        $setterAdapter = $this->mockListAdapter();
        $link = new AuthorizationLink(
            '',
            $this->mockAuthService(),
            $constructorAdapter
        );

        $this->assertSame(
            $constructorAdapter,
            $link->getListAdapter()
        );

        $link->setListAdapter($setterAdapter);
        $this->assertSame(
            $setterAdapter,
            $link->getListAdapter()
        );
    }

    public function testRedirectRoute()
    {
        $constructorRoute = 'route-1';
        $setterRoute = 'route-2';
        $link = new AuthorizationLink(
            '',
            $this->mockAuthService(),
            $this->mockListAdapter(),
            $constructorRoute
        );

        $this->assertSame(
            $constructorRoute,
            $link->getRedirectRoute()
        );

        $link->setRedirectRoute($setterRoute);
        $this->assertSame(
            $setterRoute,
            $link->getRedirectRoute()
        );
    }

    public function testIsAuthenticated()
    {
        $serviceMock = $this->mockAuthService();
        $serviceMock->expects($this->once())
            ->method('hasIdentity')
            ->willReturn(true);

        $link = new AuthorizationLink(
            '',
            $serviceMock,
            $this->mockListAdapter()
        );

        $this->assertSame(
            true,
            $link->isAuthenticated()
        );

        $serviceMock2 = $this->mockAuthService();
        $serviceMock2->expects($this->once())
            ->method('hasIdentity')
            ->willReturn(false);

        $link->setAuthenticationService($serviceMock2);

        $this->assertSame(
            false,
            $link->isAuthenticated()
        );
    }

    /**
     * @dataProvider isAuthorizedProvider
     * @param $identity
     * @param $controller
     * @param $action
     * @param $code
     * @param $expected
     * @throws AuthorizationException
     */
    public function testIsAuthorized(
        $identity,
        $controller,
        $action,
        $code,
        $expected
    )
    {
        $expectingException = is_array($expected);
        $mockService = $this->mockAuthService();
        $mockList = $this->mockListAdapter();
        $mockAccessStatus = $this->createMock(Status::class);

        $link = new AuthorizationLink(
            '',
            $mockService,
            $mockList
        );

        if ($expectingException) {
            $this->expectException($expected['exception']);
        }
        $mockService->expects($this->once())
            ->method('getIdentity')
            ->willReturn($identity);

        if ($code === Status::OK) {
            $mockService->expects($this->any())
                ->method('hasIdentity')
                ->willReturn(isset($identity));
        } else {
            $mockService->expects($this->exactly(0))
                ->method('hasIdentity');
        }

        $mockList->expects($this->once())
            ->method('getAccessStatus')
            ->with($identity, $controller, $action)
            ->willReturn($mockAccessStatus);

        if ($expectingException) {
            // an exception is expected if the status code is not known a priori
            // and if it is thrown, then currently an auth result is not created,
            // and if it is not created then accessStatus->getCode is only called
            // once
            $getCodeGonnaBeCalled = $this->once();
        } else {
            // otherwise, it is called twice. Once in isAuthorized and once
            // in AuthorizationResult
            $getCodeGonnaBeCalled = $this->exactly(2);
        }

        $mockAccessStatus->expects($getCodeGonnaBeCalled)
            ->method('getCode')
            ->willReturn($code);

        $result = $link->isAuthorized($controller, $action);

        if ($expectingException) {
            return;
        }
        $this->assertSame(
            $mockAccessStatus,
            $result->getAccessStatus()
        );
        $this->assertSame(
            $link,
            $result->getAuthLink()
        );
        $this->assertSame(
            $expected,
            $result->isValid()
        );
    }

    /**
     * @return array
     */
    public function isAuthorizedProvider()
    {
        $identities = [
            null,
            'id1',
            'id2',
            ''
            // Although this is an empty string, should be valid if Auth Adapter
            // authenticates an empty string as the identity
        ];
        $controllers = [
            null,
            'TestController'
        ];
        $actions = [
            null,
            'getAction'
        ];
        $validStatusCodes = [
            Status::UNAUTHORIZED,
            Status::REJECTED,
            Status::PUBLIC,
            Status::OK
        ];
        $statusCodes = [
            Status::CODE_MAX + 1,
            Status::CODE_MIN - 1,
            Status::UNAUTHORIZED,
            Status::REJECTED,
            Status::PUBLIC,
            Status::OK
        ];
        $data = [];
        foreach ($identities as $identity) {
            foreach ($controllers as $controller) {
                foreach ($actions as $action) {
                    foreach ($statusCodes as $code) {
                        $datum = [
                            $identity,
                            $controller,
                            $action,
                            $code
                        ];
                        if (! in_array($code, $validStatusCodes)) {
                            // expecting exception since code returned by
                            // the mock list adapter is bad
                            $datum[] = [
                                'exception' => AuthorizationException::class
                            ];
                        } else {
                            $authenticated = isset($identity);
                            $datum[] = $code === Status::PUBLIC
                                || (
                                    $code === Status::OK
                                    && $authenticated
                                );
                        }
                        $data[] = $datum;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @return MockObject
     */
    private function mockAuthService()
    {
        return $this->createMock(AuthenticationServiceInterface::class);
    }

    /**
     * @return MockObject
     */
    private function mockListAdapter()
    {
        return $this->createMock(ListAdapterInterface::class);
    }
}