<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\NonSerializableTrait;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * ContextualCurlMultiHandler sends asynchronous requests through a cURL multi
 * handler leased to the current Swoole coroutine while it has work.
 *
 * Idle handlers return to a bounded pool so later requests can reuse their
 * connections. Concurrent coroutines use separate handlers; requests in the
 * same coroutine share a handler while its transfers are active.
 *
 * @see https://github.com/hypervel/guzzle/blob/8.3/docs/handlers.md#coroutine-handlers
 */
final class ContextualCurlMultiHandler
{
    use NonSerializableTrait;

    /** @var callable(): CurlMultiHandler */
    private $factory;

    private int $maxIdleHandlers;

    private ?CurlMultiHandler $outside = null;

    /** @var \WeakMap<object, CurlMultiHandler>|null */
    private ?\WeakMap $leases = null;

    /** @var list<CurlMultiHandler> */
    private array $idle = [];

    /**
     * @param callable(): CurlMultiHandler $factory         Creates a handler for a coroutine.
     * @param int                          $maxIdleHandlers Idle handlers kept for reuse.
     */
    public function __construct(callable $factory, int $maxIdleHandlers = 64)
    {
        if ($maxIdleHandlers < 0) {
            throw new InvalidArgumentException('maxIdleHandlers must be zero or greater.');
        }

        $this->factory = $factory;
        $this->maxIdleHandlers = $maxIdleHandlers;
    }

    /**
     * @return PromiseInterface<ResponseInterface, mixed>
     */
    public function __invoke(
        #[\SensitiveParameter]
        RequestInterface $request,
        #[\SensitiveParameter]
        array $options
    ): PromiseInterface {
        return $this->handlerForCurrentContext()($request, $options);
    }

    private function handlerForCurrentContext(): CurlMultiHandler
    {
        $context = \Swoole\Coroutine::getContext();

        $this->leases ??= new \WeakMap();

        $handler = $context === null ? $this->outside : ($this->leases[$context] ?? null);

        if ($handler !== null) {
            return $handler;
        }

        do {
            $handler = \array_pop($this->idle);
        } while ($handler !== null && $handler->isClosed());

        if ($handler === null) {
            $handler = ($this->factory)();
            $reference = \WeakReference::create($this);
            $handler->onIdle(static function (CurlMultiHandler $handler) use ($reference): void {
                $pool = $reference->get();
                if ($pool !== null) {
                    $pool->release($handler);
                }
            });
        }

        if ($context === null) {
            $this->outside = $handler;
        } else {
            $this->leases[$context] = $handler;
        }

        return $handler;
    }

    /**
     * Returns the owner's idle handler to the pool.
     */
    private function release(CurlMultiHandler $handler): void
    {
        $context = \Swoole\Coroutine::getContext();
        if ($context === null) {
            if ($this->outside !== $handler) {
                return;
            }
            $this->outside = null;
        } else {
            if (($this->leases[$context] ?? null) !== $handler) {
                return;
            }
            unset($this->leases[$context]);
        }

        if (!$handler->isClosed() && \count($this->idle) < $this->maxIdleHandlers) {
            $this->idle[] = $handler;
        }
    }
}
