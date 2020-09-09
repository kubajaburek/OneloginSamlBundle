<?php

namespace Hslavich\OneloginSamlBundle\Tests\EventSubscriber;

use Hslavich\OneloginSamlBundle\EventSubscriber\SamlLogoutCsrfExceptionSubscriber;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error as SamlException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

class SamlLogoutCsrfExceptionSubscriberTest extends TestCase
{
    public function testUnrelatedExceptionType() : void
    {
        $subscriber = $this->createSubscriber($this->getHttpUtils(), $this->getSamlAuth(false), $this->getLogoutUrlGenerator());
        $exception = new \RuntimeException('Invalid CSRF token.');
        $request = $this->getRequest('/saml_logout', ['SAMLRequest' => 'test']);
        $event = $this->getEvent($request, $exception);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber does not check the exception type.');
    }

    public function testUnrelatedExceptionMessage() : void
    {
        $subscriber = $this->createSubscriber($this->getHttpUtils(), $this->getSamlAuth(false), $this->getLogoutUrlGenerator());
        $exception = new AccessDeniedHttpException('Unrelated access denied exception.');
        $request = $this->getRequest('/saml_logout', ['SAMLRequest' => 'test']);
        $event = $this->getEvent($request, $exception);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber does not check if the exception cause is an invalid CSRF token.');
    }

    public function testUnrelatedUrl() : void
    {
        $request = $this->getRequest('unrelated', ['SAMLRequest' => 'test']);
        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(false), $this->getLogoutUrlGenerator());
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber responds to non-logout URLs.');
    }

    public function testInvalidSamlRequest() : void
    {
        $request = $this->getRequest();
        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(false), $this->getLogoutUrlGenerator());
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber does not check if there is a SAML request.');

        $request = $this->getRequest('/saml_logout', ['SAMLRequest' => 'invalid']);
        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(true), $this->getLogoutUrlGenerator());
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber does not validate the SAML request.');
    }

    public function testNoCsrfConfigured() : void
    {
        $request = $this->getRequest('/saml_logout', ['SAMLRequest' => 'test']);
        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(false), $this->getLogoutUrlGenerator(null, null));
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber malfunctions when no CSRF protection for logout is configured.');

        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(false), $this->getLogoutUrlGenerator(null, 'malformed'));
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse());
    }

    public function testCsrfLoop() : void
    {
        $request = $this->getRequest('/saml_logout', ['csrf' => 'token', 'SAMLRequest' => 'test']);
        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(false), $this->getLogoutUrlGenerator());
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $this->assertEmpty($event->getResponse(), 'Subscriber does not check whether a CSRF token is already present in the request.');
    }

    public function testRedirect() : void
    {
        $request = $this->getRequest('/saml_logout', ['SAMLRequest' => 'please']);
        $subscriber = $this->createSubscriber($this->getHttpUtils($request), $this->getSamlAuth(false), $this->getLogoutUrlGenerator());
        $event = $this->getEvent($request);
        $subscriber->onKernelException($event);
        $response = $event->getResponse();
        $this->assertTrue($response instanceof Response, 'Subscriber did not return a response.');
        $this->assertTrue($response->isRedirection(), 'Subscriber did not return a redirect.');
        $this->assertEquals('http://mock.test:8080/saml_logout?csrf=token&SAMLRequest=please', $response->headers->get('Location'), 'Subscriber redirects to a wrong URL.');
    }

    protected function getHttpUtils(?Request $request = null) : HttpUtils
    {
        if (null === $request)
        {
            return new HttpUtils($this->getUrlGenerator());
        } else {
            return new HttpUtils($this->getUrlGenerator(), $this->getUrlMatcher($request));
        }
    }

    protected function getUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static function ($name) {
                return '/'.(string)$name;
            });
        return $urlGenerator;
    }

    protected function getUrlMatcher(Request $request): UrlMatcher
    {
        $context = new RequestContext();
        $context->fromRequest($request);
        return new UrlMatcher($this->getRoutes(), $context);
    }

    protected function getRoutes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('saml_logout', new Route('/saml_logout'));
        return $routes;
    }

    protected function getSamlAuth(bool $shouldFail) : Auth
    {
        $auth = $this->createMock(Auth::class);
        if ($shouldFail)
        {
            $auth
                ->method('processSLO')
                ->will($this->throwException(new SamlException('mock')));
        } else {
            $auth
                ->method('processSLO')
                ->willReturn(true);
        }
        return $auth;
    }

    protected function getLogoutUrlGenerator(?string $csrfParamName = 'csrf', ?string $csrfValue = 'token') : LogoutUrlGenerator
    {
        $generator = $this->createMock(LogoutUrlGenerator::class);
        if (null === $csrfParamName && null !== $csrfValue)
        {
            $generator
                ->method('getLogoutPath')
                ->willReturn('/saml_logout?'.$csrfValue);
        } else if (null !== $csrfParamName && null !== $csrfValue)
        {
            $generator
                ->method('getLogoutPath')
                ->willReturn('/saml_logout?'.urlencode($csrfParamName).'='.urlencode($csrfValue));
        } else {
            $generator
                ->method('getLogoutPath')
                ->willReturn('/saml_logout');
        }
        return $generator;
    }

    protected function getHttpKernel() : HttpKernelInterface
    {
        return $this->createMock(HttpKernelInterface::class);
    }

    protected function getRequest(string $uri = '/saml_logout', array $parameters = []) : Request
    {
        return Request::create('http://mock.test:8080'.$uri, 'GET', $parameters);
    }

    protected function getEvent(Request $request, ?\Throwable $exception = null) : ExceptionEvent
    {
        if (null === $exception)
        {
            $exception = new AccessDeniedHttpException('Invalid CSRF token.');
        }
        return new ExceptionEvent($this->getHttpKernel(), $request, HttpKernelInterface::MASTER_REQUEST, $exception);
    }

    protected function createSubscriber(HttpUtils $httpUtils, Auth $samlAuth, LogoutUrlGenerator $generator) : SamlLogoutCsrfExceptionSubscriber
    {
        $subscriber = new SamlLogoutCsrfExceptionSubscriber($httpUtils);
        $subscriber->setSaml($samlAuth);
        $subscriber->setLogoutUrlGenerator($generator);
        return $subscriber;
    }
}