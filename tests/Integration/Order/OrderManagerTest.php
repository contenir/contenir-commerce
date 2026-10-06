<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\Trait\OrderServicesTrait;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * The facade reaches each step of the three services. The behaviour of each
 * step is covered by the service's own test.
 */
#[Group('integration')]
final class OrderManagerTest extends TestCase
{
    use OrderServicesTrait;

    private OrderManager $manager;

    #[Test]
    public function anExpiredCheckoutCancelsItsOrder(): void
    {
        $this->managedCheckout();

        static::assertSame(OrderStatus::Cancelled, $this->manager->expireCheckout('cs_fake_1')?->status);
    }

    #[Test]
    public function aPaidOrderIsRefundedByTheGivenAmount(): void
    {
        $order = $this->managedPayment();

        $this->manager->refundOrder($order, Money::fromCents(50_000));

        static::assertSame(
            ['refunded', [['paymentIntentId' => 'pi_fake_1', 'amount' => 50_000, 'idempotencyKey' => null]]],
            [$this->orderRow(['status'])['status'], $this->gateway->refunds],
        );
    }

    #[Test]
    public function aPaidOrderRunsThroughPickupAndCollection(): void
    {
        $order = $this->managedPayment();
        $this->manager->markAwaitingPickup($order);
        $this->manager->markCollected($order);

        static::assertSame('collected', $this->orderRow(['status'])['status']);
    }

    #[Test]
    public function aPendingOrderCanBeCancelled(): void
    {
        $order = $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());

        $this->manager->cancelOrder($order);

        static::assertSame('cancelled', $this->orderRow(['status'])['status']);
    }

    #[Test]
    public function checkoutAndCompletionRunThroughTheFacade(): void
    {
        $order = $this->managedCheckout();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        $result = $this->manager->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            ['cs_fake_1', CompletionOutcome::Completed, $order, 2],
            [
                $order->stripeCheckoutSessionId,
                $result->outcome,
                $result->order,
                count($this->manager->purchaseItemsFor($order)),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpOrderServices();
        $this->manager = new OrderManager($this->checkout, $this->completion, $this->fulfilment);
    }

    private function managedCheckout(): AbstractOrderEntity
    {
        $order = $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');

        return $order;
    }

    private function managedPayment(): AbstractOrderEntity
    {
        $order = $this->managedCheckout();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');
        $this->manager->completeFromCheckoutSession('cs_fake_1');

        return $order;
    }
}
