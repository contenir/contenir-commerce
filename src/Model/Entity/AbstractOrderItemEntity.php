<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\OverflowException;
use Contenir\Commerce\Money\Money;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * One line of an order: a quantity of one item variant. Title, variant
 * label, description and unit price are copied from the item and variant at
 * the moment of sale so the order remains accurate if they later change.
 * The item and variant ids are null once the item or variant is deleted.
 *
 * Extend it with a final class carrying #[Table('commerce_order_item')] (or
 * use OrderItemEntity) and add the site's own columns there; point the
 * "contenir_commerce.order_item_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 */
abstract class AbstractOrderItemEntity
{
    #[Id(generated: true)]
    #[Column('order_item_id')]
    public ?int $orderItemId = null;

    #[Column('order_id')]
    public int $orderId;

    #[Column('item_id')]
    public ?int $itemId = null;

    #[Column('item_variant_id')]
    public ?int $itemVariantId = null;

    #[Column]
    public string $title;

    #[Column('variant_label')]
    public ?string $variantLabel = null;

    #[Column]
    public ?string $description = null;

    /**
     * Tax-inclusive price of one unit in cents.
     */
    #[Column('unit_price')]
    public int $unitPrice = 0;

    #[Column]
    public int $quantity = 1;

    #[Column]
    public ?DateTimeImmutable $created = null;

    /**
     * @throws InvalidArgumentException When the stored price is negative.
     * @throws OverflowException When the total exceeds the integer range of cents.
     */
    public function getTotal(): Money
    {
        return $this->getUnitPrice()->multiply($this->quantity);
    }

    /**
     * @throws InvalidArgumentException When the stored price is negative.
     */
    public function getUnitPrice(): Money
    {
        return Money::fromCents($this->unitPrice);
    }
}
