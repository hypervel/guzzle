<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Handler\ContextualCurlMultiHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise as P;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\TestCase;

class ContextualCurlMultiHandlerTest extends TestCase
{
    /** @var list<\WeakReference<CurlMultiHandler>> */
    private array $created = [];

    private ?\Throwable $failure = null;

    public function testRejectsNegativePoolSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler(-1);
    }

    /**
     * @dataProvider poolSizes
     */
    public function testConcurrentCoroutinesUseSeparateHandlersAndBoundIdleRetention(int $limit): void
    {
        $handler = $this->handler($limit);

        $this->runInCoroutine(function () use ($handler): void {
            $gate = new \Swoole\Coroutine\Channel(1);
            $first = $this->coroutine(static function () use ($handler, $gate): void {
                $one = $handler(new Request('GET', Server::$url), ['delay' => 60000]);
                $two = $handler(new Request('GET', Server::$url), ['delay' => 60000]);
                try {
                    $gate->pop(2);
                } finally {
                    $one->cancel();
                    $two->cancel();
                }
            });

            try {
                self::assertCount(1, $this->created);
                $this->coroutine(static function () use ($handler): void {
                    $pending = $handler(new Request('GET', Server::$url), ['delay' => 60000]);
                    $pending->cancel();
                });
                self::assertCount(2, $this->created);
            } finally {
                $gate->push(true);
                if (\Swoole\Coroutine::exists($first)) {
                    \Swoole\Coroutine::join([$first], 2);
                }
                $gate->close();
            }
        });

        \gc_collect_cycles();
        self::assertCount(\min(2, $limit), \array_filter($this->created, static function (\WeakReference $reference): bool {
            return $reference->get() !== null;
        }));
    }

    public function testIdleHandlersCanBeReusedBeforeTheirCoroutineEnds(): void
    {
        $handler = $this->handler();

        $this->runInCoroutine(function () use ($handler): void {
            $handler(new Request('GET', Server::$url), ['delay' => 60000])->cancel();
            $this->coroutine(static function () use ($handler): void {
                $handler(new Request('GET', Server::$url), ['delay' => 60000])->cancel();
            });
            $handler(new Request('GET', Server::$url), ['delay' => 60000])->cancel();
        });

        self::assertCount(1, $this->created);
    }

    /**
     * @dataProvider poolSizes
     */
    public function testRequestsOutsideCoroutinesReuseIdleHandlers(int $limit): void
    {
        $handler = $this->handler($limit);
        $request = new Request('GET', Server::$url);
        $handler($request, ['delay' => 60000])->cancel();
        $handler($request, ['delay' => 60000])->cancel();

        self::assertCount($limit === 0 ? 2 : 1, $this->created);
    }

    public static function poolSizes(): iterable
    {
        yield 'disabled' => [0];
        yield 'one' => [1];
        yield 'default' => [64];
    }

    /**
     * @dataProvider closeModes
     */
    public function testClosedHandlersAreNotReused(bool $whileLeased): void
    {
        $handler = $this->handler();
        $request = new Request('GET', Server::$url);
        $pending = $handler($request, ['delay' => 60000]);
        $native = $this->created[0]->get();

        if (!$whileLeased) {
            $pending->cancel();
        }
        $native->close();
        $handler($request, ['delay' => 60000])->cancel();

        self::assertCount(2, $this->created);
        self::assertNotSame($native, $this->created[1]->get());
    }

    public static function closeModes(): iterable
    {
        yield 'leased' => [true];
        yield 'pooled' => [false];
    }

    public function testAbandonedLeasesDoNotRetainTheirCoroutineOrHandler(): void
    {
        $handler = $this->handler();
        $context = null;

        $this->runInCoroutine(function () use ($handler, &$context): void {
            $this->coroutine(static function () use ($handler, &$context): void {
                $context = \WeakReference::create(\Swoole\Coroutine::getContext());
                $handler(new Request('GET', Server::$url), ['delay' => 60000]);
            });
        });

        \gc_collect_cycles();
        self::assertNull($context->get());
        self::assertNull($this->created[0]->get());
    }

    public function testFailedRequestPreparationReleasesTheLease(): void
    {
        $handler = $this->handler();
        $request = new Request('GET', Server::$url);

        try {
            $handler($request, ['on_headers' => 'not-a-function']);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('on_headers must be callable', $e->getMessage());
        }

        $handler($request, ['delay' => 60000])->cancel();
        self::assertCount(1, $this->created);
    }

    public function testSharedDefaultClientSupportsOverlappingAsyncRequests(): void
    {
        Server::flush();
        Server::enqueue([new Response(200), new Response(201)]);
        $client = new Client();
        $statuses = [];

        $this->runInCoroutine(function () use ($client, &$statuses): void {
            $gate = new \Swoole\Coroutine\Channel(1);
            $first = $this->coroutine(static function () use ($client, &$statuses, $gate): void {
                $pending = $client->getAsync(Server::$url, ['timeout' => 2]);
                try {
                    $gate->pop(2);
                    $statuses[] = $pending->wait()->getStatusCode();
                } finally {
                    $pending->cancel();
                }
            });

            try {
                $this->coroutine(static function () use ($client, &$statuses): void {
                    $statuses[] = $client->getAsync(Server::$url, ['timeout' => 2])->wait()->getStatusCode();
                });
            } finally {
                $gate->push(true);
                if (\Swoole\Coroutine::exists($first)) {
                    \Swoole\Coroutine::join([$first], 3);
                }
                $gate->close();
            }
        });

        \sort($statuses);
        self::assertSame([200, 201], $statuses);
    }

    /**
     * @dataProvider connectionCapOptionProvider
     */
    public function testCappedClientsKeepOneHandlerAcrossCoroutines(string $option): void
    {
        $client = new Client([$option => 1]);

        $this->runInCoroutine(function () use ($client): void {
            $pending = $client->getAsync(Server::$url, ['delay' => 60000]);
            try {
                $this->coroutine(static function () use ($client): void {
                    try {
                        $client->getAsync(Server::$url, ['timeout' => 1])->wait();
                        self::fail('Expected LogicException.');
                    } catch (\LogicException $e) {
                        self::assertStringContainsString('cannot call __invoke() on a cURL multi handler with work owned by coroutine', $e->getMessage());
                    }
                });
            } finally {
                $pending->cancel();
            }
        });
    }

    public static function connectionCapOptionProvider(): iterable
    {
        yield 'max host connections' => ['max_host_connections'];
        yield 'max total connections' => ['max_total_connections'];
    }

    private function handler(int $limit = 64): ContextualCurlMultiHandler
    {
        return new ContextualCurlMultiHandler(function (): CurlMultiHandler {
            $handler = new CurlMultiHandler();
            $this->created[] = \WeakReference::create($handler);

            return $handler;
        }, $limit);
    }

    private function coroutine(callable $callback): int
    {
        return \Swoole\Coroutine::create(function () use ($callback): void {
            try {
                $callback();
            } catch (\Throwable $e) {
                $this->failure = $e;
            }
        });
    }

    private function runInCoroutine(callable $callback): void
    {
        $queue = P\Utils::queue();
        $hooks = \Swoole\Runtime::getHookFlags();
        P\Utils::queue(new P\TaskQueue(false));

        try {
            \Swoole\Coroutine\run(function () use ($callback): void {
                $this->coroutine($callback);
            });
        } finally {
            \Swoole\Runtime::setHookFlags($hooks);
            P\Utils::queue($queue);
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
