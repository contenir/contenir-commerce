<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Container;

use Contenir\Commerce\Clock\SystemClock;
use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Model\Repository\ArtistEnquiryFileRepository;
use Contenir\Commerce\Model\Repository\ArtistEnquiryRepository;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Module;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\FulfilmentService;
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
            'artworks'             => [ArtworkRepository::class],
            'orders'               => [OrderRepository::class],
            'order items'          => [OrderItemRepository::class],
            'artist enquiries'     => [ArtistEnquiryRepository::class],
            'artist enquiry files' => [ArtistEnquiryFileRepository::class],
            'email log'            => [EmailLogRepository::class],
            'order manager'        => [OrderManager::class],
            'commerce settings'    => [CommerceSettings::class],
            'checkout service'     => [CheckoutService::class],
            'completion service'   => [CompletionService::class],
            'fulfilment service'   => [FulfilmentService::class],
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
    public function theWiredCompletionClaimsWorksInsideTheEntityManagersTransaction(): void
    {
        $this->insert('artwork', ['price' => 3_500, 'status' => 'available']);
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
            [CompletionOutcome::Completed, 'sold'],
            [
                $manager->completeFromCheckoutSession('cs_fake_1')->outcome,
                $this->column('artwork', 'status', 'artwork_id', 1),
            ],
        );
    }

    #[Test]
    public function theWiredOrderManagerWritesThroughTheSharedEntityManager(): void
    {
        $this->insert('artwork', ['price' => 3_500, 'status' => 'available']);
        $manager = $this->container([])->get(OrderManager::class);

        $order = $manager->createPendingOrder(
            [new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500))],
            new CustomerDetails('Avery Buyer', 'avery@example.test'),
        );

        static::assertSame(3_500, $this->column('gallery_order', 'total', 'order_id', $order->getId()));
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
}
