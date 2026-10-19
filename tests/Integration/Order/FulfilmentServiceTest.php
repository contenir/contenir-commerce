<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Commerce\Payment\CheckoutSession;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\Trait\OrderServicesTrait;
use Override;
use PhpDb\Adapter\Exception\InvalidQueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;

/**
 * The back-office steps, against a real in-memory database and a
 * scriptable gateway. Each test builds a fresh database.
 */
#[Group('integration')]
final class FulfilmentServiceTest extends TestCase
{
    use OrderServicesTrait;

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function excessiveRefundProvider(): array
    {
        return [
            'more than the total'    => [
                0,
                283_001,
                'Cannot refund $2,830.01 on order "ORD-2026-0001": only $2,830.00 of its $2,830.00 total is left to refund',
            ],
            'more than what is left' => [
                50_000,
                233_001,
                'Cannot refund $2,330.01 on order "ORD-2026-0001": only $2,330.00 of its $2,830.00 total is left to refund',
            ],
        ];
    }

    /**
     * @return array<string, array{OrderStatus}>
     */
    public static function refundableStatusProvider(): array
    {
        return [
            'paid'            => [OrderStatus::Paid],
            'awaiting pickup' => [OrderStatus::AwaitingPickup],
            'collected'       => [OrderStatus::Collected],
        ];
    }

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
    public function aFullyRefundedOrderCannotBeRefundedAgain(): void
    {
        $order = $this->paidOrder();
        $this->fulfilment->refundOrder($order);

        try {
            $this->fulfilment->refundOrder($order);
            static::fail('Expected InvalidTransitionException');
        } catch (InvalidTransitionException $e) {
            static::assertSame(
                ['Order cannot move from "refunded" to "refunded"', 1],
                [$e->getMessage(), count($this->gateway->refunds)],
            );
        }
    }

    #[Test]
    public function anOrderThatCannotBeCancelledLeavesItsSessionOpen(): void
    {
        $order         = $this->checkedOutOrder();
        $order->status = OrderStatus::Refunded;

        try {
            $this->fulfilment->cancelOrder($order);
            static::fail('Expected InvalidTransitionException');
        } catch (InvalidTransitionException) {
            static::assertSame([], $this->gateway->expirations);
        }
    }

    #[DataProvider('refundableStatusProvider')]
    #[Test]
    public function aPartialRefundKeepsTheStatusAndRecordsTheAmountRefunded(OrderStatus $status): void
    {
        $order         = $this->paidOrder();
        $order->status = $status;
        $this->clock->moveTo('2026-08-25 15:00:00');

        $this->fulfilment->refundOrder($order, Money::fromCents(50_000));

        static::assertSame(
            [
                [[
                    'paymentIntentId' => 'pi_fake_1',
                    'amount'          => 50_000,
                    'idempotencyKey'  => 'contenir-commerce-staff-refund-pi_fake_1-0-50000',
                ]],
                [
                    'status'          => $status->value,
                    'refunded_amount' => 50_000,
                    'refunded_at'     => null,
                    'updated'         => '2026-08-25 15:00:00',
                ],
                0,
            ],
            [
                $this->gateway->refunds,
                $this->orderRow(['status', 'refunded_amount', 'refunded_at', 'updated']),
                $this->variantStock(1),
            ],
        );
    }

    #[DataProvider('excessiveRefundProvider')]
    #[Test]
    public function aRefundBeyondWhatIsLeftIsRejectedBeforeAnyMoneyMoves(
        int $refundedBefore,
        int $refund,
        string $message,
    ): void {
        $order                 = $this->paidOrder();
        $order->refundedAmount = $refundedBefore;
        $this->em->save($order);

        try {
            $this->fulfilment->refundOrder($order, Money::fromCents($refund));
            static::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            static::assertSame(
                [$message, [], ['status' => 'paid', 'refunded_amount' => $refundedBefore]],
                [$e->getMessage(), $this->gateway->refunds, $this->orderRow(['status', 'refunded_amount'])],
            );
        }
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
        $this->expectExceptionMessage('Order "ORD-2026-0001" has no Stripe payment to refund');

        $this->fulfilment->refundOrder($order);
    }

    #[Test]
    public function aRefundWithoutAnAmountRefundsWhatIsLeftAndClosesTheOrder(): void
    {
        $order = $this->paidOrder();
        $this->fulfilment->refundOrder($order, Money::fromCents(50_000));
        $this->clock->moveTo('2026-08-26 09:30:00');

        $this->fulfilment->refundOrder($order);

        static::assertSame(
            [
                [
                    'paymentIntentId' => 'pi_fake_1',
                    'amount'          => null,
                    'idempotencyKey'  => 'contenir-commerce-staff-refund-pi_fake_1-50000-233000',
                ],
                [
                    'status'          => 'refunded',
                    'refunded_amount' => 283_000,
                    'refunded_at'     => '2026-08-26 09:30:00',
                    'updated'         => '2026-08-26 09:30:00',
                ],
            ],
            [$this->gateway->refunds[1], $this->orderRow(['status', 'refunded_amount', 'refunded_at', 'updated'])],
        );
    }

    #[Test]
    public function aRefundWithoutAPaymentIsRejected(): void
    {
        $order = $this->checkedOutOrder();

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Order "ORD-2026-0001" has no Stripe payment to refund');

        $this->fulfilment->refundOrder($order);
    }

    #[Test]
    public function aRetryAfterTheOrderFailedToSaveReusesTheIdempotencyKey(): void
    {
        $order = $this->paidOrder();
        $this->updateBehindTheManager(
            "CREATE TRIGGER refund_not_saved BEFORE UPDATE ON commerce_order BEGIN SELECT RAISE(ABORT, 'disk full'); END",
        );

        try {
            $this->fulfilment->refundOrder($order, Money::fromCents(50_000));
            static::fail('Expected InvalidQueryException');
        } catch (InvalidQueryException) {
            $this->updateBehindTheManager('DROP TRIGGER refund_not_saved');
        }

        $this->em->refresh($order);
        $this->fulfilment->refundOrder($order, Money::fromCents(50_000));

        static::assertSame(
            [
                [
                    'contenir-commerce-staff-refund-pi_fake_1-0-50000',
                    'contenir-commerce-staff-refund-pi_fake_1-0-50000',
                ],
                ['status' => 'paid', 'refunded_amount' => 50_000],
            ],
            [
                array_map(static fn(array $refund): ?string => $refund['idempotencyKey'], $this->gateway->refunds),
                $this->orderRow(['status', 'refunded_amount']),
            ],
        );
    }

    #[Test]
    public function aSessionPaidBeforeStaffCancelledIsRefundedWhenItCompletes(): void
    {
        $order = $this->checkedOutOrder();
        $this->gateway->completeSession('cs_fake_1', 'pi_paid_first');

        $this->fulfilment->cancelOrder($order);
        $result = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [
                ['cs_fake_1'],
                CompletionOutcome::RefundedCancelled,
                [[
                    'paymentIntentId' => 'pi_paid_first',
                    'amount'          => null,
                    'idempotencyKey'  => 'contenir-commerce-cancelled-refund-pi_paid_first',
                ]],
                'cancelled',
            ],
            [
                $this->gateway->expirations,
                $result->outcome,
                $this->gateway->refunds,
                $this->orderRow(['status'])['status'],
            ],
        );
    }

    #[Test]
    public function aWebhookRetryAfterAPartialRefundRefundsNothingMore(): void
    {
        $order = $this->paidOrder();
        $this->fulfilment->refundOrder($order, Money::fromCents(50_000));

        static::assertSame(
            [CompletionOutcome::AlreadyCompleted, 1],
            [$this->completion->completeFromCheckoutSession('cs_fake_1')->outcome, count($this->gateway->refunds)],
        );
    }

    #[Test]
    public function cancellingAPaidOrderLeavesItsCompleteSessionAlone(): void
    {
        $order = $this->paidOrder();

        $this->fulfilment->cancelOrder($order);

        static::assertSame([[], 'cancelled'], [$this->gateway->expirations, $this->orderRow(['status'])['status']]);
    }

    #[Test]
    public function cancellingAPendingOrderBeforeCheckoutContactsNoProvider(): void
    {
        $order = $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());

        $this->fulfilment->cancelOrder($order);

        static::assertSame([[], 'cancelled'], [$this->gateway->expirations, $this->orderRow(['status'])['status']]);
    }

    #[Test]
    public function cancellingAPendingOrderExpiresItsCheckoutSession(): void
    {
        $order = $this->checkedOutOrder();
        $this->clock->moveTo('2026-08-20 10:05:00');

        $this->fulfilment->cancelOrder($order);

        static::assertSame(
            [
                ['cs_fake_1'],
                CheckoutSession::STATUS_EXPIRED,
                ['status' => 'cancelled', 'cancelled_at' => '2026-08-20 10:05:00', 'updated' => '2026-08-20 10:05:00'],
            ],
            [
                $this->gateway->expirations,
                $this->gateway->retrieveCheckoutSession('cs_fake_1')->status,
                $this->orderRow(['status', 'cancelled_at', 'updated']),
            ],
        );
    }

    #[Test]
    public function refundsThatAddUpToTheTotalCloseTheOrder(): void
    {
        $order = $this->paidOrder();
        $this->fulfilment->refundOrder($order, Money::fromCents(50_000));
        $this->clock->moveTo('2026-08-27 11:00:00');

        $this->fulfilment->refundOrder($order, Money::fromCents(233_000));

        static::assertSame(
            [
                [
                    'paymentIntentId' => 'pi_fake_1',
                    'amount'          => 233_000,
                    'idempotencyKey'  => 'contenir-commerce-staff-refund-pi_fake_1-50000-233000',
                ],
                ['status' => 'refunded', 'refunded_amount' => 283_000, 'refunded_at' => '2026-08-27 11:00:00'],
            ],
            [$this->gateway->refunds[1], $this->orderRow(['status', 'refunded_amount', 'refunded_at'])],
        );
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

    #[Test]
    public function whenTheSessionCannotBeExpiredTheOrderStaysPending(): void
    {
        $order = $this->checkedOutOrder();
        $this->gateway->failExpiries();

        try {
            $this->fulfilment->cancelOrder($order);
            static::fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            static::assertSame(
                [
                    'Unable to expire Stripe checkout session',
                    OrderStatus::Pending,
                    ['status' => 'pending', 'cancelled_at' => null],
                ],
                [$e->getMessage(), $order->status, $this->orderRow(['status', 'cancelled_at'])],
            );
        }
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpOrderServices();
    }
}
