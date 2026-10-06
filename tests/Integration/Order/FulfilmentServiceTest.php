<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Tests\Trait\OrderServicesTrait;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The back-office steps, against a real in-memory database and a
 * scriptable gateway. Each test builds a fresh database.
 */
#[Group('integration')]
final class FulfilmentServiceTest extends TestCase
{
    use OrderServicesTrait;

    #[Test]
    public function aCollectedOrderCannotBeCancelled(): void
    {
        $order = $this->paidOrder();
        $this->fulfilment->markAwaitingPickup($order);
        $this->fulfilment->markCollected($order);

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Order cannot move from "collected" to "cancelled"');

        $this->fulfilment->cancelOrder($order);
    }

    #[Test]
    public function aRefundGoesThroughTheGatewayAndClosesTheOrder(): void
    {
        $order = $this->paidOrder();
        $this->clock->moveTo('2026-08-25 15:00:00');

        $this->fulfilment->refundOrder($order, Money::fromCents(50_000));

        static::assertSame(
            [
                [['paymentIntentId' => 'pi_fake_1', 'amount' => 50_000, 'idempotencyKey' => null]],
                ['status' => 'refunded', 'refunded_at' => '2026-08-25 15:00:00', 'updated' => '2026-08-25 15:00:00'],
                'sold',
            ],
            [$this->gateway->refunds, $this->orderRow(['status', 'refunded_at', 'updated']), $this->artworkStatus(1)],
        );
    }

    #[Test]
    public function aRefundIsCheckedAgainstTheLifecycleBeforeAnyMoneyMoves(): void
    {
        $order                        = $this->checkedOutOrder();
        $order->stripePaymentIntentId = 'pi_unexpected';

        try {
            $this->fulfilment->refundOrder($order);
            static::fail('Expected InvalidTransitionException');
        } catch (InvalidTransitionException $e) {
            static::assertSame(
                ['Order cannot move from "pending" to "refunded"', []],
                [$e->getMessage(), $this->gateway->refunds],
            );
        }
    }

    #[Test]
    public function aRefundWithAnEmptyPaymentIntentIsRejected(): void
    {
        $order                        = $this->paidOrder();
        $order->stripePaymentIntentId = '';

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Order "LR-2026-0001" has no Stripe payment to refund');

        $this->fulfilment->refundOrder($order);
    }

    #[Test]
    public function aRefundWithoutAPaymentIsRejected(): void
    {
        $order = $this->checkedOutOrder();

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Order "LR-2026-0001" has no Stripe payment to refund');

        $this->fulfilment->refundOrder($order);
    }

    #[Test]
    public function thePickupLifecycleReachesCollected(): void
    {
        $order = $this->paidOrder();
        $this->clock->moveTo('2026-08-21 09:00:00');
        $this->fulfilment->markAwaitingPickup($order);
        $awaiting = $this->orderRow(['status', 'updated']);
        $this->clock->moveTo('2026-08-22 14:00:00');

        $this->fulfilment->markCollected($order);

        static::assertSame(
            [
                ['status' => 'awaiting_pickup', 'updated' => '2026-08-21 09:00:00'],
                ['status' => 'collected', 'collected_at' => '2026-08-22 14:00:00', 'updated' => '2026-08-22 14:00:00'],
            ],
            [$awaiting, $this->orderRow(['status', 'collected_at', 'updated'])],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpOrderServices();
    }
}
