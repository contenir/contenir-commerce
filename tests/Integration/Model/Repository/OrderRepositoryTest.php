<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('repository')]
final class OrderRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private OrderRepository $repository;

    #[Test]
    public function findsAnOrderByItsCheckoutSession(): void
    {
        static::assertSame(
            [2, null],
            [
                $this->repository->findOneByCheckoutSessionId('cs_2')?->orderId,
                $this->repository->findOneByCheckoutSessionId('cs_nowhere'),
            ],
        );
    }

    #[Test]
    public function findsAnOrderByItsReference(): void
    {
        static::assertSame(
            [3, null],
            [
                $this->repository->findOneByOrderRef('LR-2026-0003')?->orderId,
                $this->repository->findOneByOrderRef('LR-2026-9999'),
            ],
        );
    }

    #[Test]
    public function findsOrdersInOneStatusNewestFirst(): void
    {
        static::assertSame(
            [3, 1],
            array_map(
                static fn(OrderEntity $order): ?int => $order->orderId,
                $this->repository->findByStatus(OrderStatus::Paid),
            ),
        );
    }

    #[Test]
    public function itsItemsLoadInTheOrderTheyWereAdded(): void
    {
        $this->insert('commerce_order_item', ['order_id' => 1, 'title' => 'Zebra Finch', 'unit_price' => 100]);
        $this->insert('commerce_order_item', ['order_id' => 2, 'title' => 'Elsewhere', 'unit_price' => 100]);
        $this->insert('commerce_order_item', ['order_id' => 1, 'title' => 'Apple Gum', 'unit_price' => 200]);

        static::assertSame(
            ['Zebra Finch', 'Apple Gum'],
            array_map(
                static fn(OrderItemEntity $item): string => $item->title,
                $this->repository->find(1)?->items->toArray() ?? [],
            ),
        );
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('commerce_order', [
            'order_id'                   => 9,
            'order_ref'                  => 'LR-2026-0009',
            'customer_name'              => 'Avery Buyer',
            'customer_email'             => 'avery@example.test',
            'customer_phone'             => '0400 000 000',
            'status'                     => 'collected',
            'total'                      => 283_000,
            'gst_amount'                 => 25_727,
            'stripe_checkout_session_id' => 'cs_9',
            'stripe_payment_intent_id'   => 'pi_9',
            'customer_notes'             => 'Saturday',
            'staff_notes'                => 'Wrapped',
            'paid_at'                    => '2026-08-20 10:00:00',
            'collected_at'               => '2026-08-22 11:00:00',
            'refunded_at'                => '2026-08-23 12:00:00',
            'cancelled_at'               => '2026-08-24 13:00:00',
            'created'                    => '2026-08-20 09:00:00',
            'updated'                    => '2026-08-24 13:00:00',
        ]);

        $order = $this->repository->find(9);

        static::assertEquals(
            [
                9,
                'LR-2026-0009',
                'Avery Buyer',
                'avery@example.test',
                '0400 000 000',
                OrderStatus::Collected,
                283_000,
                25_727,
                'cs_9',
                'pi_9',
                'Saturday',
                'Wrapped',
                new DateTimeImmutable('2026-08-20 10:00:00'),
                new DateTimeImmutable('2026-08-22 11:00:00'),
                new DateTimeImmutable('2026-08-23 12:00:00'),
                new DateTimeImmutable('2026-08-24 13:00:00'),
                new DateTimeImmutable('2026-08-20 09:00:00'),
                new DateTimeImmutable('2026-08-24 13:00:00'),
            ],
            [
                $order?->orderId,
                $order?->orderRef,
                $order?->customerName,
                $order?->customerEmail,
                $order?->customerPhone,
                $order?->status,
                $order?->total,
                $order?->gstAmount,
                $order?->stripeCheckoutSessionId,
                $order?->stripePaymentIntentId,
                $order?->customerNotes,
                $order?->staffNotes,
                $order?->paidAt,
                $order?->collectedAt,
                $order?->refundedAt,
                $order?->cancelledAt,
                $order?->created,
                $order?->updated,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new OrderRepository($this->em);

        $this->insert('commerce_order', [
            'order_ref'                  => 'LR-2026-0001',
            'status'                     => 'paid',
            'stripe_checkout_session_id' => 'cs_1',
        ]);
        $this->insert('commerce_order', [
            'order_ref'                  => 'LR-2026-0002',
            'status'                     => 'pending',
            'stripe_checkout_session_id' => 'cs_2',
        ]);
        $this->insert('commerce_order', [
            'order_ref'                  => 'LR-2026-0003',
            'status'                     => 'paid',
            'stripe_checkout_session_id' => 'cs_3',
        ]);
    }
}
