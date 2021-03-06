<?php

namespace Scheb\TwoFactorBundle\Tests\Security\TwoFactor\EventListener;

use Scheb\TwoFactorBundle\Security\TwoFactor\EventListener\RequestListener;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContext;
use Scheb\TwoFactorBundle\Tests\TestCase;

class RequestListenerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $authenticationContextFactory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $authHandler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $tokenStorage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $request;

    /**
     * @var RequestListener
     */
    private $listener;

    public function setUp()
    {
        $this->authenticationContextFactory = $this->createMock('Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextFactoryInterface');
        $this->authHandler = $this->createMock('Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationHandlerInterface');
        $this->tokenStorage = $this->createMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');

        $supportedTokens = array('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken');
        $this->listener = new RequestListener($this->authenticationContextFactory, $this->authHandler, $this->tokenStorage, $supportedTokens, '^/exclude/');
    }

    /**
     * @return UsernamePasswordToken
     */
    private function createSupportedSecurityToken()
    {
        return new UsernamePasswordToken('user', array(), 'key');
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function createEvent($pathInfo = '/some-path/', $isMasterRequest = true)
    {
        $this->request = $this->createMock('Symfony\Component\HttpFoundation\Request');
        $this->request
            ->expects($this->any())
            ->method('getPathInfo')
            ->willReturn($pathInfo);

        $event = $this->createMock('Symfony\Component\HttpKernel\Event\GetResponseEvent');
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($this->request);
        $event
            ->expects($this->any())
            ->method('isMasterRequest')
            ->willReturn($isMasterRequest);

        return $event;
    }

    private function stubTokenStorage($token)
    {
        $this->tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token);
    }

    /**
     * @test
     */
    public function onCoreRequest_tokenClassSupported_requestAuthenticationCode()
    {
        $event = $this->createEvent();
        $token = $this->createSupportedSecurityToken();
        $this->stubTokenStorage($token);

        $expectedContext = new AuthenticationContext($this->request, $token);

        $this->authenticationContextFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($expectedContext);

        //Expect TwoFactorProvider to be called
        $this->authHandler
            ->expects($this->once())
            ->method('requestAuthenticationCode')
            ->with($expectedContext);

        $this->listener->onCoreRequest($event);
    }

    /**
     * @test
     */
    public function onCoreRequest_responseCreated_setResponseOnEvent()
    {
        $event = $this->createEvent();
        $token = $this->createSupportedSecurityToken();
        $this->stubTokenStorage($token);
        $response = $this->createMock('Symfony\Component\HttpFoundation\Response');

        $expectedContext = new AuthenticationContext($this->request, $token);

        $this->authenticationContextFactory
            ->method('create')
            ->willReturn($expectedContext)
        ;

        //Stub the TwoFactorProvider
        $this->authHandler
            ->expects($this->any())
            ->method('requestAuthenticationCode')
            ->willReturn($response);

        //Expect response to be set
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $this->listener->onCoreRequest($event);
    }

    /**
     * @test
     */
    public function onCoreRequest_tokenClassNotSupported_doNothing()
    {
        $event = $this->createEvent();
        $token = $this->createMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $this->stubTokenStorage($token);

        //Stub the TwoFactorProvider
        $this->authHandler
            ->expects($this->never())
            ->method('requestAuthenticationCode');

        $this->listener->onCoreRequest($event);
    }

    /**
     * @test
     */
    public function onCoreRequest_pathExcluded_notRequestAuthenticationCode()
    {
        $event = $this->createEvent('/exclude/someFile');
        $token = $this->createSupportedSecurityToken();
        $this->stubTokenStorage($token);

        //Stub the TwoFactorProvider
        $this->authHandler
            ->expects($this->never())
            ->method('requestAuthenticationCode');

        $this->listener->onCoreRequest($event);
    }

    /**
     * @test
     */
    public function onCoreRequest_noMasterRequest_doNothing()
    {
        $event = $this->createEvent(null, false);
        $token = $this->createSupportedSecurityToken();
        $this->stubTokenStorage($token);

        $expectedContext = new AuthenticationContext($this->request, $token);

        $this->authenticationContextFactory
            ->expects($this->never())
            ->method('create')
            ->willReturn($expectedContext);

        //Stub the TwoFactorProvider
        $this->authHandler
            ->expects($this->never())
            ->method('requestAuthenticationCode');

        $this->listener->onCoreRequest($event);
    }
}
