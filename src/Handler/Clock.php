<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

use Swoole\Coroutine\CanceledException;

/**
 * @internal
 */
final class Clock
{
    private function __construct()
    {
    }

    /**
     * Returns the current monotonic clock reading in seconds.
     */
    public static function now(): float
    {
        return \hrtime(true) / 1e9;
    }

    /**
     * @throws CanceledException when canceling the current coroutine
     *                           interrupts the sleep
     */
    public static function sleep(int $microseconds): void
    {
        while ($microseconds > 0) {
            $slice = \min($microseconds, 0xFFFFFFFF);
            \swoole_clear_error();
            \usleep($slice);

            if (CoroutineCancellation::interrupted(\SWOOLE_ERROR_CO_CANCELED)) {
                throw new CanceledException('The coroutine was canceled while sleeping.');
            }

            $microseconds -= $slice;
        }
    }
}
