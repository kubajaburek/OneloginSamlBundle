# OneloginSamlBundle backports

[![Build Status](https://travis-ci.org/kubajaburek/OneloginSamlBundle.svg?branch=v1)](https://travis-ci.org/kubajaburek/OneloginSamlBundle)

As the [OneloginSaml](https://github.com/hslavich/OneloginSamlBundle) Symfony bundle [dropped support](https://github.com/hslavich/OneloginSamlBundle/pull/125) for framework versions less than 5.1, this repository contains the latest available version compatible with Symfony 4.4. Selected features and fixes from the upstream are backported here.

*Note: Support for Symfony versions older than 4.4 and EOL versions has been removed from this fork as well.*

Installation
------------

This fork overrides the original [`hslavich/OneloginSamlBundle`](https://github.com/hslavich/OneloginSamlBundle) package, so you must explicitly configure its repository in your `composer.json`:

``` json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/kubajaburek/OneloginSamlBundle",
        "no-api": true
    }
]
```

Then you can install the package as usual:

``` bash
composer install hslavich/oneloginsaml-bundle
```

Usage
-----

[Refer to the original repository.](https://github.com/kubajaburek/OneloginSamlBundle/blob/ae9efea27043e04140690cf0645cffb51afe5b4d/README.md)

Differences from upstream
-------------------------

This fork remains compatible with Symfony 4.4, so it does not refactor code that has been marked as deprecated in later versions. Below is a list of feature differences (may not be exhaustive).

-   **CSRF protection support for Identity Provider-initated logout** (not in upstream)\
    For additional security, you can configure logout to require a valid CSRF token:

    ``` yaml
    security:
        firewalls:
            main:
                logout:
                    csrf_token_generator: security.csrf.token_manager
    ```

    Navigating to `/saml/logout` without appending a valid CSRF token will then fail. However, when performing IdP-initiated SLO, the IdP redirects the user to that URL without a token (as it is not able to generate one) and the request fails.

    This fork subscribes to CSRF verification failures and when it detects a logout attempt with a valid SAML request from the Identity Provider, it automatically adds a valid CSRF token to the request, allowing it to proceed.

Pull requests with new features, fixes, backports and other improvements are welcome.