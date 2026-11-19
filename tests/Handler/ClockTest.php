<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Handler;

use GuzzleHttp\Handler\Clock;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Coroutine\CanceledException;
use Swoole\Coroutine\Channel;

class ClockTest extends TestCase
{
    use RunsInCoroutines;

    public function testNow(): void
    {
        self::assertGreaterThan(0, Clock::now());
    }

    public function testSleepThrowsOnlyWhenCancellationInterruptsIt(): void
    {
        $exception = null;
        $elapsed = null;

        self::runInCoroutine(static function () use (&$exception, &$elapsed): void {
            // A cancellation the coroutine handled leaves its flag set.
            self::cancelCurrentCoroutineAfter(0.01);
            (new Channel())->pop(5);
            self::assertTrue(Coroutine::isCanceled());
            Clock::sleep(1000);

            self::cancelCurrentCoroutineAfter(0.01);
            $start = Clock::now();
            try {
                Clock::sleep(5000000);
            } catch (CanceledException $e) {
                $exception = $e;
            }
            $elapsed = Clock::now() - $start;
        });

        self::assertInstanceOf(CanceledException::class, $exception);
        self::assertLessThan(1, $elapsed);
    }
}
