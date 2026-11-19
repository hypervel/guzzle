<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

use GuzzleHttp\NonSerializableTrait;
use GuzzleHttp\Psr7\Exception\TimeoutException;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use Swoole\Coroutine\CanceledException;

/**
 * Decorates the stock stream of a blocking response transport so a read that
 * canceling the coroutine interrupted throws the cancellation instead of a
 * read failure.
 *
 * @internal
 */
final class CancelableTransportStream implements StreamInterface
{
    use NonSerializableTrait;
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private StreamCancellation $cancellation;

    public function __construct(StreamInterface $stream, StreamCancellation $cancellation)
    {
        $this->stream = $stream;
        $this->cancellation = $cancellation;
    }

    public function __clone()
    {
        // Detaching a clone must not detach this body's transport.
        $this->stream = clone $this->stream;
    }

    public function read(int $length): string
    {
        $this->cancellation->begin();

        try {
            return $this->stream->read($length);
        } catch (\RuntimeException $e) {
            $cancellation = CoroutineCancellation::fromStreamFailure($e, true);
            if ($cancellation !== null) {
                throw $cancellation;
            }

            if ($e->getPrevious() === null && !$e instanceof TimeoutException && $this->cancellation->interrupted()) {
                throw new CanceledException('Reading the response body was canceled.', 0, $e);
            }

            throw $e;
        }
    }
}
