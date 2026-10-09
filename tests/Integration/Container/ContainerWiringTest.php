<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Container;

use Contenir\Commerce\Clock\SystemClock;
use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Module;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\FulfilmentService;
use Contenir\Commerce\Order\ItemInventory;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Order\PurchaseItem;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Commerce\Payment\StripeGateway;
use Contenir\Commerce\Payment\UnconfiguredGateway;
use Contenir\Commerce\Tests\TestAsset\Payment\FakePaymentGateway;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use Contenir\Db\Model\ConfigProvider as DbModelConfigProvider;
use Laminas\ServiceManager\ServiceManager;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

use function array_merge_recursive;

/**
 * The shipped wiring in a real laminas-servicemanager, alongside
 * contenir-db-model's own ConfigProvider, over an in-memory database.
 */
#[Group('integration')]
final class ContainerWiringTest extends TestCase
{
    use SqliteDatabaseTrait;

    /**
     * @return array<string, array{class-string}>
     */
    public static function serviceProvider(): array
    {
        return [
            'items'              => [ItemRepository::class],
            'item variants'      => [ItemVariantRepository::class],
            'orders'             => [OrderRepository::class],
            'order items'        => [OrderItemRepository::class],
            'item inventory'     => [ItemInventory::class],
            'email log'          => [EmailLogRepository::class],
            'order manager'      => [OrderManager::class],
            'commerce settings'  => [CommerceSettings::class],
            'checkout service'   => [CheckoutService::class],
            'completion service' => [CompletionService::class],
            'fulfilment service' => [FulfilmentService::class],
        ];
    }

    #[Test]
    public function aConfiguredSecretKeyGivesTheStripeGateway(): void
    {
        $container = $this->container(['stripe' => ['secret_key' => 'sk_test_fake']]);

        static::assertInstanceOf(StripeGateway::class, $container->get(PaymentGatewayInterface::class));
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('serviceProvider')]
    #[Test]
    public function everyServiceResolves(string $service): void
    {
        static::assertInstanceOf($service, $this->container([])->get($service));
    }

    #[Test]
    public function theClockIsTheSystemClockAndStripeStaysUnconfiguredWithoutAKey(): void
    {
        $container = $this->container([]);

        static::assertSame(
            [SystemClock::class, UnconfiguredGateway::class],
            [$container->get(ClockInterface::class)::class, $container->get(PaymentGatewayInterface::class)::class],
        );
    }

    #[Test]
    public function theLaminasMvcModuleWiresTheSameServices(): void
    {
        $dependencies = array_merge_recursive(
            (new DbModelConfigProvider())->getDependencies(),
            (new Module())->getConfig()['service_manager'],
        );
        $dependencies['services'] = [
            'config'                => (new DbModelConfigProvider())(),
            AdapterInterface::class => $this->adapter,
        ];

        static::assertInstanceOf(OrderManager::class, (new ServiceManager($dependencies))->get(OrderManager::class));
    }

    #[Test]
    public function theSettingsComeFromTheCommerceConfigKey(): void
    {
        $settings = $this->container(['contenir_commerce' => ['order_reference_prefix' => 'GG']])->get(
            CommerceSettings::class,
        );

        static::assertSame('GG', $settings->orderReferencePrefix);
    }

    #[Test]
    public function theWiredCompletionClaimsStockInsideTheEntityManagersTransaction(): void
    {
        $this->insertTote();
        $gateway   = new FakePaymentGateway();
        $container = $this->container([]);
        $container->setService(PaymentGatewayInterface::class, $gateway);
        $manager = $container->get(OrderManager::class);
        $order   = $manager->createPendingOrder(
            [new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500))],
            new CustomerDetails('Avery Buyer', 'avery@example.test'),
        );
        $manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
        $gateway->completeSession('cs_fake_1', 'pi_fake_1');

        static::assertSame(
            [CompletionOutcome::Completed, 9],
            [
                $manager->completeFromCheckoutSession('cs_fake_1')->outcome,
                $this->column('item_variant', 'stock', 'item_variant_id', 1),
            ],
        );
    }

    #[Test]
    public function theWiredOrderManagerWritesThroughTheSharedEntityManager(): void
    {
        $this->insertTote();
        $manager = $this->container([])->get(OrderManager::class);

        $order = $manager->createPendingOrder(
            [new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500))],
            new CustomerDetails('Avery Buyer', 'avery@example.test'),
        );

        static::assertSame(3_500, $this->column('commerce_order', 'total', 'order_id', $order->getId()));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config): ServiceManager
    {
        $dependencies = array_merge_recursive(
            (new DbModelConfigProvider())()['dependencies'],
            (new ConfigProvider())()['dependencies'],
        );
        $dependencies['services'] = [
            'config'                => [...(new DbModelConfigProvider())(), ...$config],
            AdapterInterface::class => $this->adapter,
        ];

        return new ServiceManager($dependencies);
    }

    private function insertTote(): void
    {
        $this->insert('item', ['title' => 'Tote bag', 'status' => 'listed']);
        $this->insert('item_variant', ['item_id' => 1, 'price' => 3_500, 'stock' => 10]);
    }
}
