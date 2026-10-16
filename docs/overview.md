# Overview

## Requirements

1.  PHP 8.4 or newer with the Swoole extension
2.  To use the PHP stream handler, `allow_url_fopen` must be enabled in your
    system's php.ini.
3.  To use the cURL handler, you must have cURL >= 7.34.0 compiled with OpenSSL
    and zlib.

Support for HTTP/2 and HTTP/3 depends on the PHP cURL extension and the linked
runtime libcurl. HTTP/2 requires libcurl 7.65.2 or higher built with HTTP/2
support (nghttp2). HTTP/3 requires PHP to expose cURL's HTTP/3 constants,
libcurl 7.88.0 or higher, and runtime libcurl reporting the `CURL_VERSION_HTTP3`
feature. Many libcurl builds do not enable HTTP/3 unless an HTTP/3 backend is
selected at build time.

## Installation

The recommended way to install Guzzle is with
[Composer](https://getcomposer.org). Composer is a dependency management tool
for PHP that allows you to declare the dependencies your project needs and
installs them into your project.

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

You can add Guzzle as a dependency using Composer:

```bash
composer require hypervel/guzzle:^8.2
```

Alternatively, you can specify Guzzle as a dependency in your project's existing
composer.json file:

```js
{
  "require": {
     "hypervel/guzzle": "^8.2"
  }
}
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

You can find out more on how to install Composer, configure autoloading, and
other best-practices for defining dependencies at
[getcomposer.org](https://getcomposer.org).

## Upgrading

The git repository contains an [upgrade guide](../UPGRADING.md) that details
what changed between the major versions.

## Contributing

### Guidelines

1.  Guzzle utilizes PSR-1, PSR-2, PSR-4, and PSR-7.
2.  Guzzle is meant to be lean and fast with very few dependencies. This means
    that not every feature request will be accepted.
3.  Guzzle's code uses PHP 7.4-compatible syntax so its static analysis tools
    can run on PHP 7.4. At runtime, this fork requires PHP 8.4 or newer and the
    Swoole extension.
4.  All pull requests must include unit tests to ensure the change works as
    expected and to prevent regressions.

### Running the tests

In order to contribute, you'll need to checkout the source from GitHub and
install Guzzle's dependencies using Composer:

```bash
git clone https://github.com/hypervel/guzzle.git
cd guzzle && composer install
```

Guzzle is unit tested with PHPUnit:

```bash
vendor/bin/phpunit
```

> [!NOTE]
> You'll need Node.js `^20.19 || ^22.13 || >=24` available as `node` in order to
> perform integration tests on Guzzle's HTTP handlers.

## Reporting a security vulnerability

If you discover a security vulnerability in this fork, please report it
privately by emailing Albert Chen at
[albert@hypervel.org](mailto:albert@hypervel.org). Please do not report security
vulnerabilities through public GitHub issues or discussions. If it also affects
`guzzlehttp/guzzle`, please report it upstream as well through their
[Security Policy](https://github.com/guzzle/guzzle/security/policy).
