<?php

namespace Hslavich\OneloginSamlBundle\Security\Logout;

use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;

class SamlLogoutHandler implements LogoutHandlerInterface
{
    protected $samlAuth;

    public function __construct(\OneLogin\Saml2\Auth $samlAuth)
    {
        $this->samlAuth = $samlAuth;
    }

    /**
     * This method is called by the LogoutListener when a user has requested
     * to be logged out. Usually, you would unset session variables, or remove
     * cookies, etc.
     *
     * @param Request $request
     * @param Response $response
     * @param TokenInterface $token
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        if (!$token instanceof SamlTokenInterface) {
            return;
        }

        try {
            $redirect = $this->samlAuth->processSLO(true, null, false, null, true);
            $this->redirect($response, $redirect);
        } catch (\OneLogin\Saml2\Error $e) {
            if (!empty($this->samlAuth->getSLOurl())) {
                $sessionIndex = $token->hasAttribute('sessionIndex') ? $token->getAttribute('sessionIndex') : null;
                $relayState = $request->getSchemeAndHttpHost().$request->getBaseUrl(); // website root
                $redirect = $this->samlAuth->logout($relayState, [], $token->getUsername(), $sessionIndex, true);
                $this->redirect($response, $redirect);
            }
        }
    }

    protected function redirect(Response $response, string $url) : void
    {
        $response->setStatusCode(Response::HTTP_FOUND);
        $response->headers->set('Location', $url);
    }
}
