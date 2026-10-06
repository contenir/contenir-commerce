<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Commerce\Order\PurchaseItem;
use Contenir\Commerce\Tests\TestAsset\Clock\MovableClock;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\TestAsset\Payment\FakePaymentGateway;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use Override;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;

/**
 * The full order lifecycle against a real in-memory database and a
 * scriptable gateway. Artworks 1 and 2 are available, priced 1,850.00 and
 * 980.00. Each test builds a fresh database.
 */
#[Group('integration')]
final class OrderManagerTest extends TestCase
{
    use SqliteDatabaseTrait;

    private const string CREATED = '2026-08-20 10:00:00';

    private ArtworkRepository $artworks;

    private MovableClock $clock;

    private FakePaymentGateway $gateway;

    private OrderManager $manager;

    #[Test]
    public function aCancelledOrderThatIsPaidAfterwardsIsRefundedAndStaysCancelled(): void
    {
        $order = $this->checkedOutOrder();
        $this->clock->moveTo('2026-08-20 10:05:00');
        $this->manager->cancelOrder($order);
        $this->gateway->completeSession('cs_fake_1', 'pi_late');
        $this->clock->moveTo('2026-08-20 10:10:00');

        $result = $this->manager->completeFromCheckoutSession('cs_fake_1');

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
                'available',
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
                $this->artworkStatus(1),
            ],
        );
    }

    #[Test]
    public function aCancelledOrderWhoseSessionWasNeverPaidReportsNotPaid(): void
    {
        $order = $this->checkedOutOrder();
        $this->manager->cancelOrder($order);

        static::assertSame(
            [CompletionOutcome::NotPaid, [], 'cancelled'],
            [
                $this->manager->completeFromCheckoutSession('cs_fake_1')->outcome,
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

        $this->manager->completeFromCheckoutSession('cs_nowhere');
    }

    #[Test]
    public function aCollectedOrderCannotBeCancelled(): void
    {
        $order = $this->paidOrder();
        $this->manager->markAwaitingPickup($order);
        $this->manager->markCollected($order);

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Order cannot move from "collected" to "cancelled"');

        $this->manager->cancelOrder($order);
    }

    #[Test]
    public function aFailedRaceRefundRollsBackAndARetryRefundsOnce(): void
    {
        $this->paidOrder();
        $this->secondBuyerPays();
        $this->gateway->failRefunds();

        try {
            $this->manager->completeFromCheckoutSession('cs_fake_2');
            static::fail('Expected PaymentFailedException');
        } catch (PaymentFailedException) {
            $afterFailure = $this->orderRow(['status', 'stripe_payment_intent_id'], 2);
        }

        $retry = new FakePaymentGateway();
        $retry->completeSession('cs_fake_2', 'pi_second');
        $result = $this->managerWith($retry)->completeFromCheckoutSession('cs_fake_2');

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
    public function anEmptyOrderIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An order requires at least one item');

        $this->manager->createPendingOrder([], CommerceFactory::customer());
    }

    #[Test]
    public function anOrderStillOpenAtStripeIsNotPaid(): void
    {
        $this->checkedOutOrder();

        static::assertSame(
            [CompletionOutcome::NotPaid, 'pending', 'available'],
            [
                $this->manager->completeFromCheckoutSession('cs_fake_1')->outcome,
                $this->orderRow(['status'])['status'],
                $this->artworkStatus(1),
            ],
        );
    }

    #[Test]
    public function aPaidSessionWithoutAPaymentIntentCannotBeRefundedForARace(): void
    {
        $this->paidOrder();
        $this->secondBuyerPays(null);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Order "LR-2026-0002" has no Stripe payment to refund');

        $this->manager->completeFromCheckoutSession('cs_fake_2');
    }

    #[Test]
    public function aRefundGoesThroughTheGatewayAndClosesTheOrder(): void
    {
        $order = $this->paidOrder();
        $this->clock->moveTo('2026-08-25 15:00:00');

        $this->manager->refundOrder($order, Money::fromCents(50_000));

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
            $this->manager->refundOrder($order);
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

        $this->manager->refundOrder($order);
    }

    #[Test]
    public function aRefundWithoutAPaymentIsRejected(): void
    {
        $order = $this->checkedOutOrder();

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Order "LR-2026-0001" has no Stripe payment to refund');

        $this->manager->refundOrder($order);
    }

    #[Test]
    public function aSessionCompletedWithFundsStillToComeLeavesTheOrderPending(): void
    {
        $this->checkedOutOrder();
        $this->gateway->completeSessionAwaitingFunds('cs_fake_1', 'pi_becs');

        $result = $this->manager->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::NotPaid, ['status' => 'pending', 'stripe_payment_intent_id' => null], 'available'],
            [$result->outcome, $this->orderRow(['status', 'stripe_payment_intent_id']), $this->artworkStatus(1)],
        );
    }

    #[Test]
    public function aSettledCancelledOrderIsNotRefundedTwice(): void
    {
        $order = $this->checkedOutOrder();
        $this->manager->cancelOrder($order);
        $this->gateway->completeSession('cs_fake_1', 'pi_late');
        $this->manager->completeFromCheckoutSession('cs_fake_1');

        $again = $this->manager->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::RefundedCancelled, 1, 1],
            [$again->outcome, $this->gateway->retrievals, count($this->gateway->refunds)],
        );
    }

    #[Test]
    public function aWorkCannotAppearTwiceInOneOrder(): void
    {
        try {
            $this->manager->createPendingOrder(
                [CommerceFactory::item(1), CommerceFactory::item(2, 'Swan Bay'), CommerceFactory::item(1)],
                CommerceFactory::customer(),
            );
            static::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            static::assertSame(
                ['"Headland, Dawn" appears in the order more than once', 0],
                [$e->getMessage(), $this->rowCount('gallery_order')],
            );
        }
    }

    #[Test]
    public function aWorkDeletedAfterCartingIsReportedUnavailable(): void
    {
        $this->expectException(ArtworkUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Lost Work');

        $this->manager->createPendingOrder(
            [CommerceFactory::item(1), CommerceFactory::item(99, 'Lost Work')],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function aWorkSoldBehindTheManagersBackIsStillCaughtAtCompletion(): void
    {
        $this->checkedOutOrder();
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 2");
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        $result = $this->manager->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::RefundedRace, ['Swan Bay Nocturne'], 'available'],
            [$result->outcome, $result->unavailableTitles, $this->artworkStatus(1)],
        );
    }

    #[Test]
    public function beginningCheckoutAgainForTheSameOrderIsRefused(): void
    {
        $order = $this->checkedOutOrder();

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Checkout has already begun for order "LR-2026-0001"');

        $this->manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
    }

    #[Test]
    public function beginningCheckoutForAnOrderThatIsNotPendingIsRefused(): void
    {
        $order = $this->paidOrder();

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Checkout can only begin for a pending order, not a "paid" one');

        $this->manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
    }

    #[Test]
    public function beginningCheckoutRechecksAvailability(): void
    {
        $order = $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 1");

        try {
            $this->manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
            static::fail('Expected ArtworkUnavailableException');
        } catch (ArtworkUnavailableException $e) {
            static::assertSame(
                [['Headland, Dawn'], [], null],
                [$e->getTitles(), $this->gateway->checkoutRequests, $order->stripeCheckoutSessionId],
            );
        }
    }

    #[Test]
    public function beginningCheckoutSendsTheSnapshotsAndStoresTheSession(): void
    {
        $order = $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->clock->moveTo('2026-08-20 10:01:00');

        $session = $this->manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
        $request = $this->gateway->checkoutRequests[0];

        static::assertSame(
            [
                'cs_fake_1',
                ['stripe_checkout_session_id' => 'cs_fake_1', 'updated' => '2026-08-20 10:01:00'],
                'https://example.test/thanks',
                'https://example.test/cart',
                'avery@example.test',
                ['order_ref' => 'LR-2026-0001', 'order_id' => '1'],
                [
                    ['Headland, Dawn',    185_000, 1, 'June Hollis'],
                    ['Swan Bay Nocturne', 98_000,  1, 'Marcus Tran'],
                ],
            ],
            [
                $session->id,
                $this->orderRow(['stripe_checkout_session_id', 'updated']),
                $request->successUrl,
                $request->cancelUrl,
                $request->customerEmail,
                $request->metadata,
                array_map(
                    static fn($line): array => [$line->name, $line->price->amount, $line->quantity, $line->description],
                    $request->lineItems,
                ),
            ],
        );
    }

    #[Test]
    public function completingMarksTheOrderPaidAndTheWorksSold(): void
    {
        $this->checkedOutOrder();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');
        $this->clock->moveTo('2026-08-20 10:20:00');

        $result = $this->manager->completeFromCheckoutSession('cs_fake_1');

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
                ['status' => 'sold', 'updated' => '2026-08-20 10:20:00'],
                ['status' => 'sold', 'updated' => '2026-08-20 10:20:00'],
                [],
            ],
            [
                $result->outcome,
                $result->unavailableTitles,
                $this->orderRow(['status', 'stripe_payment_intent_id', 'paid_at', 'updated']),
                $this->artworkRow(1),
                $this->artworkRow(2),
                $this->gateway->refunds,
            ],
        );
    }

    #[Test]
    public function completionIsIdempotentAcrossWebhookRetries(): void
    {
        $order = $this->paidOrder();
        $this->clock->moveTo('2026-08-21 09:00:00');

        $again = $this->manager->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [CompletionOutcome::AlreadyCompleted, $order, 1, '2026-08-20 10:00:00'],
            [$again->outcome, $again->order, $this->gateway->retrievals, $this->orderRow(['paid_at'])['paid_at']],
        );
    }

    #[Test]
    public function creatingAnOrderRecordsTheCustomerTotalsAndSnapshots(): void
    {
        $order = $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());

        static::assertSame(
            [
                [
                    'order_id'       => 1,
                    'order_ref'      => 'LR-2026-0001',
                    'customer_name'  => 'Avery Buyer',
                    'customer_email' => 'avery@example.test',
                    'customer_phone' => '0400 000 000',
                    'status'         => 'pending',
                    'total'          => 283_000,
                    'gst_amount'     => 25_727,
                    'customer_notes' => 'Will collect Saturday',
                    'created'        => self::CREATED,
                    'updated'        => self::CREATED,
                ],
                [
                    [1, 1, 'Headland, Dawn', 'June Hollis', 185_000, self::CREATED],
                    [1, 2, 'Swan Bay Nocturne', 'Marcus Tran', 98_000, self::CREATED],
                ],
                'LR-2026-0001',
            ],
            [
                $this->orderRow([
                    'order_id',
                    'order_ref',
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'status',
                    'total',
                    'gst_amount',
                    'customer_notes',
                    'created',
                    'updated',
                ]),
                [$this->lineRow(1), $this->lineRow(2)],
                $order->orderRef,
            ],
        );
    }

    #[Test]
    public function expiringACheckoutCancelsOnlyAPendingOrder(): void
    {
        $this->checkedOutOrder();
        $this->clock->moveTo('2026-08-20 10:30:00');

        $cancelled = $this->manager->expireCheckout('cs_fake_1');

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
                $this->manager->expireCheckout('cs_fake_1'),
                $this->manager->expireCheckout('cs_never_existed'),
            ],
        );
    }

    #[Test]
    public function ordersAreNumberedPerYearFromTheirId(): void
    {
        $this->manager->createPendingOrder([CommerceFactory::item(1)], CommerceFactory::customer());
        $this->clock->moveTo('2027-01-02 08:00:00');

        $second = $this->manager->createPendingOrder([CommerceFactory::item(
            2,
            'Swan Bay',
        )], CommerceFactory::customer());

        static::assertSame('LR-2027-0002', $second->orderRef);
    }

    #[Test]
    public function otherSettledOrdersReportAlreadyCompleted(): void
    {
        $order = $this->paidOrder();
        $this->manager->markAwaitingPickup($order);

        static::assertSame(
            [CompletionOutcome::AlreadyCompleted, 1],
            [$this->manager->completeFromCheckoutSession('cs_fake_1')->outcome, $this->gateway->retrievals],
        );
    }

    #[Test]
    public function purchaseItemsKeepTheirSnapshotsAfterTheArtworkIsDeleted(): void
    {
        $order = $this->manager->createPendingOrder(
            [new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500))],
            CommerceFactory::customer(),
        );
        $this->updateBehindTheManager('UPDATE gallery_order_item SET artwork_id = NULL');

        $this->em->clear();

        $items = $this->manager->purchaseItemsFor($order);

        static::assertEquals([new PurchaseItem(0, 'Tote bag', Money::fromCents(3_500))], $items);
    }

    #[Test]
    public function soldWorksCannotBeOrdered(): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 2");

        try {
            $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());
            static::fail('Expected ArtworkUnavailableException');
        } catch (ArtworkUnavailableException $e) {
            static::assertSame([['Swan Bay Nocturne'], 0], [$e->getTitles(), $this->rowCount('gallery_order')]);
        }
    }

    #[Test]
    public function theFirstCompletedPaymentWinsAndTheSecondIsRefundedInFull(): void
    {
        $this->paidOrder();
        $this->secondBuyerPays();
        $this->clock->moveTo('2026-08-20 11:00:00');

        $result = $this->manager->completeFromCheckoutSession('cs_fake_2');

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

    #[Test]
    public function theOrderAndItsLinesAreWrittenTogetherOrNotAtAll(): void
    {
        $this->updateBehindTheManager('DROP TABLE gallery_order_item');

        try {
            $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());
            static::fail('Expected PDOException');
        } catch (PDOException) {
            static::assertSame(0, $this->rowCount('gallery_order'));
        }
    }

    #[Test]
    public function thePickupLifecycleReachesCollected(): void
    {
        $order = $this->paidOrder();
        $this->clock->moveTo('2026-08-21 09:00:00');
        $this->manager->markAwaitingPickup($order);
        $awaiting = $this->orderRow(['status', 'updated']);
        $this->clock->moveTo('2026-08-22 14:00:00');

        $this->manager->markCollected($order);

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
        $this->setUpDatabase();
        $this->clock    = new MovableClock(self::CREATED);
        $this->gateway  = new FakePaymentGateway();
        $this->artworks = new ArtworkRepository($this->em);
        $this->manager  = $this->managerWith($this->gateway);

        foreach ([185_000, 98_000] as $price) {
            $this->em->save(CommerceFactory::artwork($price));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function artworkRow(int $artworkId): array
    {
        $row = $this->row('artwork', 'artwork_id', $artworkId);

        return ['status' => $row['status'], 'updated' => $row['updated']];
    }

    private function artworkStatus(int $artworkId): mixed
    {
        return $this->column('artwork', 'status', 'artwork_id', $artworkId);
    }

    private function buyer(string $name): CustomerDetails
    {
        return new CustomerDetails($name, 'buyer@example.test');
    }

    private function checkedOutOrder(): OrderEntity
    {
        $order = $this->manager->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->manager->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');

        return $order;
    }

    /**
     * @return list<PurchaseItem>
     */
    private function items(): array
    {
        return [
            new PurchaseItem(1, 'Headland, Dawn', Money::fromCents(185_000), 'June Hollis'),
            new PurchaseItem(2, 'Swan Bay Nocturne', Money::fromCents(98_000), 'Marcus Tran'),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function lineRow(int $orderItemId): array
    {
        $row = $this->row('gallery_order_item', 'order_item_id', $orderItemId);

        return [
            $row['order_id'],
            $row['artwork_id'],
            $row['title'],
            $row['artist_name'],
            $row['price'],
            $row['created'],
        ];
    }

    private function managerWith(FakePaymentGateway $gateway): OrderManager
    {
        return new OrderManager(
            $this->em,
            new OrderRepository($this->em),
            new OrderItemRepository($this->em),
            $this->artworks,
            $gateway,
            $this->clock,
        );
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function orderRow(array $columns, int $orderId = 1): array
    {
        $row      = $this->row('gallery_order', 'order_id', $orderId);
        $selected = [];
        foreach ($columns as $column) {
            $selected[$column] = $row[$column];
        }

        return $selected;
    }

    private function paidOrder(): OrderEntity
    {
        $this->checkedOutOrder();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        return $this->manager->completeFromCheckoutSession('cs_fake_1')->order;
    }

    /**
     * A second buyer carted work 1 before the first paid for it, and pays
     * through session cs_fake_2.
     */
    private function secondBuyerPays(?string $paymentIntentId = 'pi_second'): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET status = 'available' WHERE artwork_id = 1");
        $second = $this->manager->createPendingOrder([CommerceFactory::item(1)], $this->buyer('Second Buyer'));
        $this->manager->beginCheckout($second, 'https://example.test/thanks', 'https://example.test/cart');
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 1");
        $this->gateway->completeSession('cs_fake_2', $paymentIntentId);
    }
}
