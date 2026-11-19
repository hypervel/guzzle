<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests;

use Swoole\Coroutine\CanceledException;

/**
 * A stream wrapper whose reads and writes throw a coroutine cancellation, as
 * Swoole does when it cancels a coroutine with an exception while the
 * coroutine waits on hooked file I/O.
 */
final class CancelingStreamWrapper
{
    private const PROTOCOL = 'guzzle-canceling';

    /** @var resource|null */
    public $context;

    /**
     * Opens a stream of unknown size whose reads and writes throw a
     * cancellation.
     *
     * @return resource
     */
    public static function open()
    {
        if (!\in_array(self::PROTOCOL, \stream_get_wrappers(), true)) {
            \stream_wrapper_register(self::PROTOCOL, self::class);
        }

        $stream = \fopen(self::PROTOCOL.'://stream', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open the canceling stream.');
        }

        return $stream;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        throw new CanceledException('Reading the stream was canceled.');
    }

    public function stream_write(string $data): int
    {
        throw new CanceledException('Writing the stream was canceled.');
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return true;
    }

    public function stream_tell(): int
    {
        return 0;
    }

    /**
     * @return false
     */
    public function stream_stat()
    {
        return false;
    }
}
