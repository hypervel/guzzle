<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

/**
 * Tracks whether canceling the coroutine interrupted one stream handler
 * operation, such as opening the transport or reading the response body.
 *
 * A notification callback can run after the interrupted wait and before the
 * operation returns, and its own I/O can reset the coroutine's cancellation
 * state, so the state is captured before the callback runs.
 *
 * @internal
 */
final class StreamCancellation
{
    private bool $captured = false;

    /**
     * Starts tracking an operation.
     */
    public function begin(): void
    {
        $this->captured = false;
        \swoole_clear_error();
    }

    /**
     * Returns whether canceling the coroutine interrupted the operation, which
     * must have failed.
     */
    public function interrupted(): bool
    {
        return $this->captured || CoroutineCancellation::interrupted(\SOCKET_ECANCELED);
    }

    /**
     * Wraps a stream notification callback so it captures the cancellation
     * state before running.
     */
    public function wrapNotification(callable $notification): callable
    {
        return function (...$arguments) use ($notification): void {
            $this->captured = $this->captured || CoroutineCancellation::interrupted(\SOCKET_ECANCELED);
            $notification(...$arguments);

            // A callback's own socket error must not look like the transport's.
            \swoole_clear_error();
        };
    }
}
