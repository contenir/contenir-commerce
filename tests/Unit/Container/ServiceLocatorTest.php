<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Container;

use Contenir\Commerce\Clock\SystemClock;
use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Tests\TestAsset\Container\ArrayContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use stdClass;

#[Group('unit')]
final class ServiceLocatorTest extends TestCase
{
    #[Test]
    public function rejectsAServiceOfAnotherType(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Service "Psr\Clock\ClockInterface" must be a Psr\Clock\ClockInterface, got stdClass',
        );

        ServiceLocator::get(new ArrayContainer([ClockInterface::class => new stdClass()]), ClockInterface::class);
    }

    #[Test]
    public function returnsAServiceOfTheRequestedType(): void
    {
        $clock = new SystemClock();

        static::assertSame(
            $clock,
            ServiceLocator::get(new ArrayContainer([ClockInterface::class => $clock]), ClockInterface::class),
        );
    }
}
