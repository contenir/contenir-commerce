<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Commerce\Tests\TestAsset\Payment\FakePaymentGateway;
use Contenir\Commerce\Tests\Trait\OrderServicesTrait;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;

/**
 * Settling orders from their checkout sessions, against a real in-memory
 * database and a scriptable gateway. Each test builds a fresh database.
 */
#[Group('integration')]
final class CompletionServiceTest extends TestCase
{
    use OrderServicesTrait;

    #[Test]
    public function aCancelledOrderThatIsPaidAfterwardsIsRefundedAndStaysCancelled(): void
    {
        $order = $this->checkedOutOrder();
        $this->clock->moveTo('2026-08-20 10:05:00');
        $this->fulfilment->cancelOrder($order);
        $this->gateway->completeSession('cs_fake_1', 'pi_late');
        $this->clock->moveTo('2026-08-20 10:10:00');

        $result = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [
                CompletionOutcome::RefundedCancelled,
                [[
                    'paymentIntentId' => 'pi_late',
                    'amount'          => null,
                    'idempotencyKey'  => 'contenir-commerce-cancelled-refund-pi_late',
                ]],
                [
                    'status'                   => 'cancelled',
                    'stripe_payment_intent_id' => 'pi_late',
                    'paid_at'                  => '2026-08-20 10:10:00',
                    'refunded_at'              => '2026-08-20 10:10:00',
                    'cancelled_at'             => '2026-08-20 10:05:00',
                    'updated'                  => '2026-08-20 10:10:00',
                ],
                1,
            ],
            [
                $result->outcome,
                $this->gateway->refunds,
                $this->orderRow([
                    'status',
                    'stripe_payment_intent_id',
                    'paid_at',
                    'refunded_at',
                    'cancelled_at',
                    'updated',
                ]),
                $this->variantStock(1),
            ],
        );
    }

    #[Test]
    public function aCancelledOrderWhoseSessionWasNeverPaidReportsNotPaid(): void
    {
        $order = $this->checkedOutOrder();
        $this->fulfilment->cancelOrder($order);

        static::assertSame(
            [CompletionOutcome::NotPaid, [], 'cancelled'],
            [
                $this->completion->completeFromCheckoutSession('cs_fake_1')->outcome,
                $this->gateway->refunds,
                $this->orderRow(['status'])['status'],
            ],
        );
    }

    #[Test]
    public function aCheckoutSessionWithNoOrderIsRejected(): void
    {
        $this->expectException(OrderNotFoundException::class);
        $this->expectExceptionMessage('No order for checkout session "cs_nowhere"');

        $this->completion->completeFromCheckoutSession('cs_nowhere');
    }

    #[Test]
    public function aFailedRaceRefundRollsBackAndARetryRefundsOnce(): void
    {
        $this->paidOrder();
        $this->secondBuyerPays();
        $this->gateway->failRefunds();

        try {
            $this->completion->completeFromCheckoutSession('cs_fake_2');
            static::fail('Expected PaymentFailedException');
        } catch (PaymentFailedException) {
            $afterFailure = $this->orderRow(['status', 'stripe_payment_intent_id'], 2);
        }

        $retry = new FakePaymentGateway();
        $retry->completeSession('cs_fake_2', 'pi_second');
        $result = $this->completionWith($retry)->completeFromCheckoutSession('cs_fake_2');

        static::assertSame(
            [
                ['status' => 'pending', 'stripe_payment_intent_id' => null],
                CompletionOutcome::RefundedRace,
                ['contenir-commerce-race-refund-pi_second'],
            ],
            [
                $afterFailure,
                $result->outcome,
                array_map(static fn(array $refund): ?string => $refund['idempotencyKey'], $retry->refunds),
            ],
        );
    }

    #[Test]
    public function anItemSoldBehindTheManagersBackIsStillCaughtAtCompletion(): void
    {
        $this->checkedOutOrder();
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 0 WHERE item_variant_id = 2');
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        $result = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::RefundedRace, ['Swan Bay Nocturne'], 1],
            [$result->outcome, $result->unavailableTitles, $this->variantStock(1)],
        );
    }

    #[Test]
    public function anOrderStillOpenAtStripeIsNotPaid(): void
    {
        $this->checkedOutOrder();

        static::assertSame(
            [CompletionOutcome::NotPaid, 'pending', 1],
            [
                $this->completion->completeFromCheckoutSession('cs_fake_1')->outcome,
                $this->orderRow(['status'])['status'],
                $this->variantStock(1),
            ],
        );
    }

    #[Test]
    public function aPaidSessionWithoutAPaymentIntentCannotBeRefundedForARace(): void
    {
        $this->paidOrder();
        $this->secondBuyerPays(null);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Order "ORD-2026-0002" has no Stripe payment to refund');

        $this->completion->completeFromCheckoutSession('cs_fake_2');
    }

    #[Test]
    public function aSessionCompletedWithFundsStillToComeLeavesTheOrderPending(): void
    {
        $this->checkedOutOrder();
        $this->gateway->completeSessionAwaitingFunds('cs_fake_1', 'pi_becs');

        $result = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::NotPaid, ['status' => 'pending', 'stripe_payment_intent_id' => null], 1],
            [$result->outcome, $this->orderRow(['status', 'stripe_payment_intent_id']), $this->variantStock(1)],
        );
    }

    #[Test]
    public function aSettledCancelledOrderIsNotRefundedTwice(): void
    {
        $order = $this->checkedOutOrder();
        $this->fulfilment->cancelOrder($order);
        $this->gateway->completeSession('cs_fake_1', 'pi_late');
        $this->completion->completeFromCheckoutSession('cs_fake_1');

        $again = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::RefundedCancelled, 1, 1],
            [$again->outcome, $this->gateway->retrievals, count($this->gateway->refunds)],
        );
    }

    #[Test]
    public function aVariantDeletedBeforePaymentIsReportedLostAndRefunded(): void
    {
        $this->checkedOutOrder();
        $this->updateBehindTheManager('DELETE FROM item_variant WHERE item_variant_id = 2');
        $this->em->clear();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        $result = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::RefundedRace, ['Swan Bay Nocturne'], 1],
            [$result->outcome, $result->unavailableTitles, $this->variantStock(1)],
        );
    }

    #[Test]
    public function completingMarksTheOrderPaidAndTakesTheStock(): void
    {
        $this->checkedOutOrder();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');
        $this->clock->moveTo('2026-08-20 10:20:00');

        $result = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [
                CompletionOutcome::Completed,
                [],
                [
                    'status'                   => 'paid',
                    'stripe_payment_intent_id' => 'pi_fake_1',
                    'paid_at'                  => '2026-08-20 10:20:00',
                    'updated'                  => '2026-08-20 10:20:00',
                ],
                ['stock' => 0, 'updated' => '2026-08-20 10:20:00'],
                ['stock' => 0, 'updated' => '2026-08-20 10:20:00'],
                [],
            ],
            [
                $result->outcome,
                $result->unavailableTitles,
                $this->orderRow(['status', 'stripe_payment_intent_id', 'paid_at', 'updated']),
                $this->variantRow(1),
                $this->variantRow(2),
                $this->gateway->refunds,
            ],
        );
    }

    #[Test]
    public function completingUpdatesVariantsTheEntityManagerAlreadyHolds(): void
    {
        $held = $this->variants->find(1);
        $this->checkedOutOrder();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(0, $held?->stock);
    }

    #[Test]
    public function completionIsIdempotentAcrossWebhookRetries(): void
    {
        $order = $this->paidOrder();
        $this->clock->moveTo('2026-08-21 09:00:00');

        $again = $this->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::AlreadyCompleted, $order, 1, '2026-08-20 10:00:00'],
            [$again->outcome, $again->order, $this->gateway->retrievals, $this->orderRow(['paid_at'])['paid_at']],
        );
    }

    #[Test]
    public function expiringACheckoutCancelsOnlyAPendingOrder(): void
    {
        $this->checkedOutOrder();
        $this->clock->moveTo('2026-08-20 10:30:00');

        $cancelled = $this->completion->expireCheckout('cs_fake_1');

        static::assertSame(
            [
                OrderStatus::Cancelled,
                ['status' => 'cancelled', 'cancelled_at' => '2026-08-20 10:30:00', 'updated' => '2026-08-20 10:30:00'],
                null,
                null,
            ],
            [
                $cancelled?->status,
                $this->orderRow(['status', 'cancelled_at', 'updated']),
                $this->completion->expireCheckout('cs_fake_1'),
                $this->completion->expireCheckout('cs_never_existed'),
            ],
        );
    }

    #[Test]
    public function otherSettledOrdersReportAlreadyCompleted(): void
    {
        $order = $this->paidOrder();
        $this->fulfilment->markAwaitingPickup($order);

        static::assertSame(
            [CompletionOutcome::AlreadyCompleted, 1],
            [$this->completion->completeFromCheckoutSession('cs_fake_1')->outcome, $this->gateway->retrievals],
        );
    }

    #[Test]
    public function theFirstCompletedPaymentWinsAndTheSecondIsRefundedInFull(): void
    {
        $this->paidOrder();
        $this->secondBuyerPays();
        $this->clock->moveTo('2026-08-20 11:00:00');

        $result = $this->completion->completeFromCheckoutSession('cs_fake_2');

        static::assertSame(
            [
                CompletionOutcome::RefundedRace,
                ['Headland, Dawn'],
                [[
                    'paymentIntentId' => 'pi_second',
                    'amount'          => null,
                    'idempotencyKey'  => 'contenir-commerce-race-refund-pi_second',
                ]],
                [
                    'status'                   => 'refunded',
                    'stripe_payment_intent_id' => 'pi_second',
                    'paid_at'                  => '2026-08-20 11:00:00',
                    'refunded_at'              => '2026-08-20 11:00:00',
                    'updated'                  => '2026-08-20 11:00:00',
                ],
            ],
            [
                $result->outcome,
                $result->unavailableTitles,
                $this->gateway->refunds,
                $this->orderRow(['status', 'stripe_payment_intent_id', 'paid_at', 'refunded_at', 'updated'], 2),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpOrderServices();
    }
}
