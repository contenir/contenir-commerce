<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit;

use Contenir\Commerce\Clock\SystemClock;
use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Model\Repository\ArtistEnquiryFileRepository;
use Contenir\Commerce\Model\Repository\ArtistEnquiryRepository;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Order\Factory\OrderManagerFactory;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Payment\Factory\StripeGatewayFactory;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\Container\RepositoryFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function invokingReturnsTheDependenciesUnderTheDependenciesKey(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(['dependencies' => $provider->getDependencies()], $provider());
    }

    #[Test]
    public function registersTheRepositoriesManagerGatewayAndClock(): void
    {
        static::assertSame(
            [
                'aliases'    => [ClockInterface::class => SystemClock::class],
                'invokables' => [SystemClock::class => SystemClock::class],
                'factories'  => [
                    ArtworkRepository::class           => RepositoryFactory::class,
                    OrderRepository::class             => RepositoryFactory::class,
                    OrderItemRepository::class         => RepositoryFactory::class,
                    ArtistEnquiryRepository::class     => RepositoryFactory::class,
                    ArtistEnquiryFileRepository::class => RepositoryFactory::class,
                    EmailLogRepository::class          => RepositoryFactory::class,
                    OrderManager::class                => OrderManagerFactory::class,
                    PaymentGatewayInterface::class     => StripeGatewayFactory::class,
                ],
            ],
            (new ConfigProvider())->getDependencies(),
        );
    }
}
