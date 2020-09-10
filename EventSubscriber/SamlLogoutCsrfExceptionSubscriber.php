<?php

namespace Hslavich\OneloginSamlBundle\EventSubscriber;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error as SamlError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * Adds CSRF token to Identity Provider-initated single logout.
 *
 * If the firewall is configured to require a CSRF token for logging out, SLO initiated by the Identity Provider will always fail, as the IdP (obviously) cannot generate a CSRF token for the Service Provider.
 *
 * This subscriber will listen for CSRF failures on the logout URL and if it detects a valid SLO request, it will redirect it to the logout URL with a valid CSRF token, where it will be processed as usual.
 */
final class SamlLogoutCsrfExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var HttpUtils
     */
    protected $http;
    /**
     * @var LogoutUrlGenerator
     */
    protected $generator;
    /**
     * @var Auth
     */
    protected $saml;

    public function setLogoutUrlGenerator(LogoutUrlGenerator $generator)
    {
        $this->generator = $generator;
    }

    public function setSaml(Auth $saml)
    {
        $this->saml = $saml;
    }

    public function __construct(HttpUtils $router)
    {
        $this->http = $router;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException'],
        ];
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if (!($event->getThrowable() instanceof AccessDeniedHttpException) || 'Invalid CSRF token.' !== $event->getThrowable()->getMessage())
        {
            return;
        }

        $request = $event->getRequest();
        if (!($request instanceof Request) || !$this->http->checkRequestPath($request, 'saml_logout'))
        {
            return;
        }

        $query = $request->getQueryString();
        if (empty($query))
        {
            // the request cannot contain a SLO request without a query string
            return;
        }

        // verify the SLO request is valid; if not, let the CSRF protection fail
        try {
            $this->saml->processSLO(true, null, false, null, true);
        } catch (SamlError $e) {
            return;
        }

        try {
            $logoutPath = $this->generator->getLogoutPath();
        } catch (\Throwable $e) {
            // LogoutUrlGenerator will throw an InvalidArgumentException if no user is logged in, let the protection fail in that case
            return;
        }

        $queryStart = strpos($logoutPath, '?');
        if (false === $queryStart)
        {
            // the generated logout URL does not contain a CSRF token, we cannot recover
            return;
        } else {
            $queryEnd = strpos($logoutPath, '=', $queryStart);
            if (false !== $queryEnd)
            {
                $csrfParamName = substr($logoutPath, $queryStart + 1, $queryEnd - $queryStart - 1);
                // if the request already contains a CSRF token, it has to be invalid and we should let the CSRF protection fail
                if ($request->query->has($csrfParamName))
                {
                    return;
                }
            } else {
                return;
            }
        }
        $logoutPath .= '&'.$query;

        $redirect = $this->http->createRedirectResponse($request, $logoutPath);
        $event->setResponse($redirect);
    }
}