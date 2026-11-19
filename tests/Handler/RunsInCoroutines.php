<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Handler;

use GuzzleHttp\Promise as P;

trait RunsInCoroutines
{
    /**
     * Runs the callback in a coroutine scheduler with its own task queue and
     * all of Swoole's runtime hooks. A coroutine that drains the shared queue
     * would also run tasks left by other tests. The scheduler only enables the
     * hooks itself until they are set explicitly, so they are set for each run
     * and restored for the tests that follow. Call the test server's control
     * methods, such as Server::enqueue(), outside the callback: its client
     * reuses cURL handles created without the hooks, which the hooked cURL
     * functions reject.
     */
    private static function runInCoroutine(callable $callback): void
    {
        $queue = P\Utils::queue();
        $hookFlags = \Swoole\Runtime::getHookFlags();
        P\Utils::queue(new P\TaskQueue(false));
        $failure = null;

        try {
            \Swoole\Runtime::setHookFlags(\SWOOLE_HOOK_ALL);
            \Swoole\Coroutine\run(static function () use ($callback, &$failure): void {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    $failure = $e;
                }
            });
        } finally {
            \Swoole\Runtime::setHookFlags($hookFlags);
            P\Utils::queue($queue);
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Cancels the current coroutine once it has waited for the given time.
     */
    private static function cancelCurrentCoroutineAfter(float $seconds, bool $throwException = false): void
    {
        $coroutine = \Swoole\Coroutine::getCid();
        \Swoole\Coroutine::create(static function () use ($coroutine, $seconds, $throwException): void {
            \Swoole\Coroutine::sleep($seconds);
            \Swoole\Coroutine::cancel($coroutine, $throwException);
        });
    }
}
