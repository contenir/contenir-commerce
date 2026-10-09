<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * An order. Change its status through the order services, which enforce
 * the OrderStatus lifecycle; the timestamps record when each step happened.
 *
 * Extend it with a final class carrying #[Table('commerce_order')] (or use
 * OrderEntity) and add the site's own columns there; point the
 * "contenir_commerce.order_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 *
 * @mago-expect lint:too-many-properties One property per column of the commerce_order table.
 */
abstract class AbstractOrderEntity
{
    #[Id(generated: true)]
    #[Column('order_id')]
    public ?int $orderId = null;

    #[Column('order_ref')]
    public string $orderRef = '';

    #[Column('customer_name')]
    public ?string $customerName = null;

    #[Column('customer_email')]
    public ?string $customerEmail = null;

    #[Column('customer_phone')]
    public ?string $customerPhone = null;

    #[Column]
    public OrderStatus $status = OrderStatus::Pending;

    /**
     * Tax-inclusive total in cents.
     */
    #[Column]
    public int $total = 0;

    /**
     * The tax included in the total, in cents, at the rate configured when the order was created.
     */
    #[Column('gst_amount')]
    public int $gstAmount = 0;

    #[Column('stripe_checkout_session_id')]
    public ?string $stripeCheckoutSessionId = null;

    #[Column('stripe_payment_intent_id')]
    public ?string $stripePaymentIntentId = null;

    #[Column('customer_notes')]
    public ?string $customerNotes = null;

    #[Column('staff_notes')]
    public ?string $staffNotes = null;

    #[Column('paid_at')]
    public ?DateTimeImmutable $paidAt = null;

    #[Column('collected_at')]
    public ?DateTimeImmutable $collectedAt = null;

    #[Column('refunded_at')]
    public ?DateTimeImmutable $refundedAt = null;

    #[Column('cancelled_at')]
    public ?DateTimeImmutable $cancelledAt = null;

    #[Column]
    public ?DateTimeImmutable $created = null;

    #[Column]
    public ?DateTimeImmutable $updated = null;

    /**
     * The order's lines as the default OrderItemEntity. A site that
     * configures its own order item entity redeclares this property on its
     * order entity with that class in #[HasMany].
     *
     * @var Collection<AbstractOrderItemEntity>
     */
    #[HasMany(OrderItemEntity::class, foreignKey: 'order_id', orderBy: ['order_item_id' => 'ASC'])]
    public Collection $items;

    /**
     * @throws InvalidArgumentException When the stored amount is negative.
     */
    public function getGstAmount(): Money
    {
        return Money::fromCents($this->gstAmount);
    }

    /**
     * @throws OrderNotFoundException When the order has not been saved.
     */
    public function getId(): int
    {
        return $this->orderId ?? throw OrderNotFoundException::unsaved();
    }

    /**
     * @throws InvalidArgumentException When the stored amount is negative.
     */
    public function getTotal(): Money
    {
        return Money::fromCents($this->total);
    }
}
