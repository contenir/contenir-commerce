<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\Money;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * One purchasable version of an item, such as a print size or a treatment
 * length, with its own price and stock. Stock is the number of units left
 * to sell, or null when it is not tracked (a service, or made to order).
 *
 * Extend it with a final class carrying #[Table('item_variant')] (or use
 * ItemVariantEntity) and add the site's own columns there; point the
 * "contenir_commerce.item_variant_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 */
abstract class AbstractItemVariantEntity
{
    #[Id(generated: true)]
    #[Column('item_variant_id')]
    public ?int $itemVariantId = null;

    #[Column('item_id')]
    public int $itemId;

    /**
     * What distinguishes this variant from its siblings, such as "A3";
     * null for an item's only variant.
     */
    #[Column]
    public ?string $label = null;

    #[Column]
    public ?string $sku = null;

    /**
     * Tax-inclusive price of one unit in cents.
     */
    #[Column]
    public int $price = 0;

    #[Column]
    public ?int $stock = null;

    #[Column]
    public int $sequence = 0;

    #[Column]
    public ?DateTimeImmutable $created = null;

    #[Column]
    public ?DateTimeImmutable $updated = null;

    /**
     * @throws InvalidArgumentException When the stored price is negative.
     */
    public function getPrice(): Money
    {
        return Money::fromCents($this->price);
    }

    /**
     * Whether $quantity units can be sold now: always, when stock is not
     * tracked.
     */
    public function hasStock(int $quantity = 1): bool
    {
        return null === $this->stock || $this->stock >= $quantity;
    }

    public function isStockTracked(): bool
    {
        return null !== $this->stock;
    }
}
