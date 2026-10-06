<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit;

use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Module;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ModuleTest extends TestCase
{
    #[Test]
    public function exposesTheConfigProviderServicesToLaminasMvc(): void
    {
        static::assertSame(
            ['service_manager' => (new ConfigProvider())->getDependencies()],
            (new Module())->getConfig(),
        );
    }
}
