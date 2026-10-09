<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit;

use Contenir\Commerce\Clock\SystemClock;
use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Config\Factory\CommerceSettingsFactory;
use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Model\Repository\Factory\RepositoryFactory;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\Factory\CheckoutServiceFactory;
use Contenir\Commerce\Order\Factory\CompletionServiceFactory;
use Contenir\Commerce\Order\Factory\FulfilmentServiceFactory;
use Contenir\Commerce\Order\Factory\ItemInventoryFactory;
use Contenir\Commerce\Order\Factory\OrderManagerFactory;
use Contenir\Commerce\Order\Factory\OrderStoreFactory;
use Contenir\Commerce\Order\Factory\RefunderFactory;
use Contenir\Commerce\Order\FulfilmentService;
use Contenir\Commerce\Order\ItemInventory;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\PurchaseItemCheck;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Payment\Factory\StripeGatewayFactory;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
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
    public function registersTheRepositoriesOrderServicesGatewayAndClock(): void
    {
        static::assertSame(
            [
                'aliases'    => [ClockInterface::class => SystemClock::class],
                'invokables' => [
                    SystemClock::class       => SystemClock::class,
                    PurchaseItemCheck::class => PurchaseItemCheck::class,
                ],
                'factories'  => [
                    ItemRepository::class          => RepositoryFactory::class,
                    ItemVariantRepository::class   => RepositoryFactory::class,
                    OrderRepository::class         => RepositoryFactory::class,
                    OrderItemRepository::class     => RepositoryFactory::class,
                    EmailLogRepository::class      => RepositoryFactory::class,
                    CheckoutService::class         => CheckoutServiceFactory::class,
                    CompletionService::class       => CompletionServiceFactory::class,
                    FulfilmentService::class       => FulfilmentServiceFactory::class,
                    OrderManager::class            => OrderManagerFactory::class,
                    OrderStore::class              => OrderStoreFactory::class,
                    ItemInventory::class           => ItemInventoryFactory::class,
                    Refunder::class                => RefunderFactory::class,
                    PaymentGatewayInterface::class => StripeGatewayFactory::class,
                    CommerceSettings::class        => CommerceSettingsFactory::class,
                ],
            ],
            (new ConfigProvider())->getDependencies(),
        );
    }
}
