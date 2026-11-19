<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

use GuzzleHttp\Psr7;
use Psr\Http\Message\StreamInterface;
use Swoole\Coroutine\CanceledException;

/**
 * Recognizes and reports Swoole coroutine cancellations of transfers.
 *
 * @internal
 */
final class CoroutineCancellation
{
    private function __construct()
    {
    }

    /**
     * Returns whether canceling the current coroutine interrupted the native
     * wait made since swoole_clear_error() was last called. The answer is only
     * exact when no PHP code ran between the wait and this call.
     *
     * @param int $error Native error the interrupted wait records, such as
     *                   SWOOLE_ERROR_CO_CANCELED
     */
    public static function interrupted(int $error): bool
    {
        return \Swoole\Coroutine::getCid() > 0
            && \Swoole\Coroutine::isCanceled()
            && \swoole_last_error() === $error;
    }

    /**
     * Returns whether a stream is the stock PSR-7 stream, which reports a
     * cancellation thrown during its I/O as the cause of its own failure.
     */
    public static function isStockStream(StreamInterface $stream): bool
    {
        $class = \get_class($stream);

        return $class === Psr7\Stream::class || $class === Psr7\LazyOpenStream::class;
    }

    /**
     * Returns the cancellation a stream operation failed with: the failure
     * itself, or, for a stock stream, the cancellation it reports as the cause.
     * Another stream's failure keeps its own meaning, because the application
     * may have wrapped a cancellation that belongs to something else.
     */
    public static function fromStreamFailure(\Throwable $failure, bool $stockStream): ?CanceledException
    {
        if ($failure instanceof CanceledException) {
            return $failure;
        }

        $previous = $failure->getPrevious();

        return $stockStream && $failure instanceof \RuntimeException && $previous instanceof CanceledException
            ? $previous
            : null;
    }
}
