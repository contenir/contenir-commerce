<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock that always reads the same instant.
 */
final readonly class FixedClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
