<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock the test moves forward explicitly, so each step of a lifecycle
 * gets a distinguishable timestamp.
 */
final class MovableClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function moveTo(string $now): void
    {
        $this->now = new DateTimeImmutable($now);
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
