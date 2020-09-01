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

[Refer to the original repository.](https://github.com/hslavich/OneloginSamlBundle/blob/v1.5.0/README.md)