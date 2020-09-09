<?php

namespace Hslavich\OneloginSamlBundle\Tests\DependencyInjection;

use Hslavich\OneloginSamlBundle\DependencyInjection\HslavichOneloginSamlExtension;
use Hslavich\OneloginSamlBundle\HslavichOneloginSamlBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Nyholm\BundleTest\BaseBundleTestCase;

class HslavichOneloginSamlExtensionTest extends BaseBundleTestCase
{
    private static $containerCache = [];

    public function testLoadIdpSettings(): void
    {
        $settings = $this->createContainerFromFile('full')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertEquals('http://id.example.com/saml2/idp/metadata.php', $settings['idp']['entityId']);
        $this->assertEquals('http://id.example.com/saml2/idp/SSOService.php', $settings['idp']['singleSignOnService']['url']);
        $this->assertEquals('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', $settings['idp']['singleSignOnService']['binding']);
        $this->assertEquals('http://id.example.com/saml2/idp/SingleLogoutService.php', $settings['idp']['singleLogoutService']['url']);
        $this->assertEquals('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', $settings['idp']['singleLogoutService']['binding']);
        $this->assertEquals('idp_x509certdata', $settings['idp']['x509cert']);
        $this->assertEquals('43:51:43:a1:b5:fc:8b:b7:0a:3a:a9:b1:0f:66:73:a8', $settings['idp']['certFingerprint']);
        $this->assertEquals('sha1', $settings['idp']['certFingerprintAlgorithm']);
        $this->assertEquals(['<cert1-string>'], $settings['idp']['x509certMulti']['signing']);
        $this->assertEquals(['<cert2-string>'], $settings['idp']['x509certMulti']['encryption']);
    }

    public function testLoadSpSettings(): void
    {
        $settings = $this->createContainerFromFile('full')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertEquals('http://myapp.com/app_dev.php/saml/metadata', $settings['sp']['entityId']);
        $this->assertEquals('sp_privateKeyData', $settings['sp']['privateKey']);
        $this->assertEquals('sp_x509certdata', $settings['sp']['x509cert']);
        $this->assertEquals('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $settings['sp']['NameIDFormat']);
        $this->assertEquals('http://myapp.com/app_dev.php/saml/acs', $settings['sp']['assertionConsumerService']['url']);
        $this->assertEquals('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', $settings['sp']['assertionConsumerService']['binding']);
        $this->assertEquals('http://myapp.com/app_dev.php/saml/logout', $settings['sp']['singleLogoutService']['url']);
        $this->assertEquals('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', $settings['sp']['singleLogoutService']['binding']);
    }

    public function testLoadSecuritySettings(): void
    {
        $settings = $this->createContainerFromFile('full')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertFalse($settings['security']['nameIdEncrypted']);
        $this->assertFalse($settings['security']['authnRequestsSigned']);
        $this->assertFalse($settings['security']['logoutRequestSigned']);
        $this->assertFalse($settings['security']['logoutResponseSigned']);
        $this->assertFalse($settings['security']['wantMessagesSigned']);
        $this->assertFalse($settings['security']['wantAssertionsSigned']);
        $this->assertFalse($settings['security']['wantNameIdEncrypted']);
        $this->assertTrue($settings['security']['requestedAuthnContext']);
        $this->assertFalse($settings['security']['signMetadata']);
        $this->assertFalse($settings['security']['wantXMLValidation']);
        $this->assertEquals('http://www.w3.org/2000/09/xmldsig#rsa-sha1', $settings['security']['signatureAlgorithm']);       
        $this->assertFalse($settings['security']['relaxDestinationValidation']);
        $this->assertTrue($settings['security']['destinationStrictlyMatches']);
        $this->assertFalse($settings['security']['rejectUnsolicitedResponsesWithInResponseTo']);
    }

    public function testLoadBasicSettings(): void
    {
        $settings = $this->createContainerFromFile('full')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertTrue($settings['strict']);
        $this->assertFalse($settings['debug']);
        $this->assertEquals('http://myapp.com/app_dev.php/saml/', $settings['baseurl']);
    }

    public function testLoadOrganizationSettings(): void
    {
        $settings = $this->createContainerFromFile('full')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertEquals('Example', $settings['organization']['en']['name']);
        $this->assertEquals('Example', $settings['organization']['en']['displayname']);
        $this->assertEquals('http://example.com', $settings['organization']['en']['url']);
    }

    public function testLoadContactSettings(): void
    {
        $settings = $this->createContainerFromFile('full')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertEquals('Tech User', $settings['contactPerson']['technical']['givenName']);
        $this->assertEquals('techuser@example.com', $settings['contactPerson']['technical']['emailAddress']);
        $this->assertEquals('Support User', $settings['contactPerson']['support']['givenName']);
        $this->assertEquals('supportuser@example.com', $settings['contactPerson']['support']['emailAddress']);
    }

    public function testLoadArrayRequestedAuthnContext(): void
    {
        $settings = $this->createContainerFromFile('requestedAuthnContext_as_array')->getParameter('hslavich_onelogin_saml.settings');

        $this->assertSame(['foo', 'bar'], $settings['security']['requestedAuthnContext']);
    }

    protected function createContainerFromFile(string $file)
    {
        if (isset(self::$containerCache[$file]))
        {
            return self::$containerCache[$file];
        }

        $kernel = $this->createKernel();
        $kernel->addConfigFile(__DIR__.'/Fixtures/security.yaml');
        $kernel->addConfigFile(__DIR__.'/Fixtures/'.$file.'.yaml');
        $kernel->addBundle(SecurityBundle::class);
        $this->bootKernel();
        self::$containerCache[$file] = $this->getContainer();
        return self::$containerCache[$file];
    }

    protected function getBundleClass()
    {
        return HslavichOneloginSamlBundle::class;
    }
}
