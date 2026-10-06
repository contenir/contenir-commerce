<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\Money;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * One line of an order. Title, artist name and price are copied from the
 * artwork at the moment of sale so the order remains accurate if the
 * artwork later changes. The artwork id is null once the artwork is deleted.
 *
 * Extend it with a final class carrying #[Table('gallery_order_item')] (or use
 * OrderItemEntity) and add the site's own columns there; point the
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

    #[Column('artwork_id')]
    public ?int $artworkId = null;

    #[Column]
    public string $title;

    #[Column('artist_name')]
    public ?string $artistName = null;

    /**
     * GST-inclusive price in cents.
     */
    #[Column]
    public int $price = 0;

    #[Column]
    public ?DateTimeImmutable $created = null;

    /**
     * @throws InvalidArgumentException When the stored price is negative.
     */
    public function getPrice(): Money
    {
        return Money::fromCents($this->price);
    }
}
