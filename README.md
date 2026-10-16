![Guzzle](.github/logo.png?raw=true)

# Guzzle, PHP HTTP Client

This is Hypervel's fork of Guzzle for Swoole coroutines. It replaces
`guzzlehttp/guzzle` and adds coroutine ownership checks and global middleware.
See [Hypervel Fork](#hypervel-fork).

Guzzle is a PHP HTTP client that makes it easy to send HTTP requests and
trivial to integrate with web services.

- Simple interface for building query strings, POST requests, streaming large
  uploads, streaming large downloads, using HTTP cookies, uploading JSON data,
  etc...
- Can send both synchronous and asynchronous requests using the same interface.
- Uses PSR-7 interfaces for requests, responses, and streams. This allows you
  to utilize other PSR-7 compatible libraries with Guzzle.
- Supports PSR-18, allowing interoperability with other PSR-18 HTTP clients.
- Abstracts away the underlying HTTP transport, allowing you to write
  environment and transport agnostic code; i.e., no hard dependency on cURL,
  PHP streams, sockets, or non-blocking event loops.
- Middleware system allows you to augment and compose client behavior.

## Hypervel Fork

[Hypervel](https://hypervel.org) runs many Swoole coroutines in one long-lived
PHP process. A cURL multi handler that one coroutine is driving must not be
driven by another, and framework integrations need to see the requests of every
Guzzle client, including clients that SDKs create. This fork adds:

- **Multi handler ownership.** A `CurlMultiHandler` with pending work belongs to
  the coroutine driving it. Until the handler is idle, other coroutines can't
  send requests through it, tick, run or close it, or wait on or cancel its
  transfers: those calls throw a `LogicException` without changing the handler.
  See [Coroutine Ownership](docs/handlers.md#coroutine-ownership).
- **Transfer tracking.** When the promise task queue implements
  `ScopedTaskQueueInterface` from
  [`hypervel/guzzle-promises`](https://github.com/hypervel/guzzle-promises), the
  multi handler passes each pending transfer to it, so the queue can cancel
  transfers that a coroutine leaves unfinished when it ends.
- **Global middleware.** `Client::pushGlobalMiddleware()` and
  `Client::removeGlobalMiddleware()` manage named middleware that wraps the
  transfers of every client in the process. See
  [Global Middleware](docs/middleware.md#global-middleware).
- **More idle cURL handles.** `CurlHandler` keeps up to 256 idle easy handles by
  default instead of 3. Coroutines overlap synchronous requests on one handler,
  and each easy handle has its own connection cache, so with 3 most concurrent
  requests would open new connections. `CurlMultiHandler` keeps its default of
  50. See [Idle cURL Handles](docs/handlers.md#idle-curl-handles).
- **Upstream pull requests.** The fork includes these changes from upstream pull
  requests:
  - [#3934](https://github.com/guzzle/guzzle/pull/3934): keeps `StreamHandler`
    deadlines local to each request.
  - [#3935](https://github.com/guzzle/guzzle/pull/3935): adds the
    `max_idle_handles` option for the cURL handlers.
  - [#3936](https://github.com/guzzle/guzzle/pull/3936): returns available bytes
    from filtered streamed responses instead of waiting for a full buffer.
  - [#3937](https://github.com/guzzle/guzzle/pull/3937): reads `StreamHandler`
    response headers from the opened stream, which also fixes the stream handler
    under Swoole on PHP 8.4 and newer.

> [!IMPORTANT]
> This fork keeps upstream's public API. Once installed, it replaces
> `guzzlehttp/guzzle` for packages with compatible version requirements,
> including third-party packages and SDKs, so their existing code keeps working
> unchanged. Changes to the fork must stay backward compatible.

## Installation

The recommended way to install Guzzle is through
[Composer](https://getcomposer.org/).

```bash
composer require hypervel/guzzle
```

## Version Guidance

This fork requires PHP 8.4 or newer and the Swoole extension.
[composer.json](composer.json) lists the exact requirements. Each fork version
is the upstream release it is based on plus a fork release number. For example,
`8.2.0.1` is the first fork release based on upstream `8.2.0`.

## Quick Start

```php
$client = new \GuzzleHttp\Client();
$response = $client->request('GET', 'https://api.example.com/users/123');

echo $response->getStatusCode(); // 200
echo $response->getHeaderLine('content-type'); // 'application/json'
echo $response->getBody(); // '{"id": 123, "name": "Ada"}'
```

For more examples, see the [Quick Start](docs/quick-start.md).

## Documentation

- [Quick Start](docs/quick-start.md)
- [Overview](docs/overview.md)
- [Request Options](docs/request-options.md)
- [Uploading Data](docs/uploading-data.md)
- [Cookies](docs/cookies.md)
- [Exceptions](docs/exceptions.md)
- [Guzzle and PSR-7](docs/guzzle-and-psr-7.md)
- [Handlers](docs/handlers.md)
- [Middleware](docs/middleware.md)
- [Testing Guzzle Clients](docs/testing-guzzle-clients.md)
- [FAQ](docs/faq.md)
- [Package Ecosystem](docs/package-ecosystem.md)
- [Upgrade Guide](UPGRADING.md)
- [Changelog](CHANGELOG.md)

For support questions, ideas, and feature requests, please use
[GitHub Discussions](https://github.com/hypervel/components/discussions).

## Security

If you discover a security vulnerability in this fork, please report it
privately by emailing Albert Chen at
[albert@hypervel.org](mailto:albert@hypervel.org). Please do not report security
vulnerabilities through public GitHub issues or discussions. If it also affects
`guzzlehttp/guzzle`, please report it upstream as well through their
[Security Policy](https://github.com/guzzle/guzzle/security/policy).

## License

Guzzle is made available under the MIT License (MIT). Please see
[License File](LICENSE) for more information.
