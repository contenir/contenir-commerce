<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Clock;

use Contenir\Commerce\Clock\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The one place that reads the real time: it is the production clock.
 */
#[Group('unit')]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function readsTheCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $now    = (new SystemClock())->now();
        $after  = new DateTimeImmutable();

        static::assertTrue($before <= $now && $now <= $after);
    }
}
