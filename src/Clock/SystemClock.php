<?php

declare(strict_types=1);

namespace Contenir\Commerce\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The PSR-20 clock registered for ClockInterface by default. Everything in
 * the package reads the time through ClockInterface, so tests and sites can
 * substitute their own clock.
 *
 * @api
 */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
