<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Factory;

use Contenir\Commerce\Item\ItemStatus;
use Contenir\Commerce\Model\Entity\ItemEntity;
use Contenir\Commerce\Model\Entity\ItemVariantEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\PurchaseItem;

/**
 * Builds valid commerce objects for tests.
 */
final class CommerceFactory
{
    public static function customer(): CustomerDetails
    {
        return new CustomerDetails('Avery Buyer', 'avery@example.test', '0400 000 000', 'Will collect Saturday');
    }

    public static function item(
        ?string $title = 'Headland, Dawn',
        ItemStatus $status = ItemStatus::Listed,
    ): ItemEntity {
        $item         = new ItemEntity();
        $item->title  = $title;
        $item->status = $status;

        return $item;
    }

    public static function purchaseItem(
        int $itemVariantId,
        string $title = 'Headland, Dawn',
        int $price = 185_000,
        int $quantity = 1,
        ?string $variantLabel = null,
    ): PurchaseItem {
        return new PurchaseItem(
            $itemVariantId,
            $title,
            Money::fromCents($price),
            $quantity,
            $variantLabel,
            'June Hollis',
        );
    }

    public static function variant(
        int $itemId,
        int $price = 185_000,
        ?int $stock = 1,
        ?string $label = null,
    ): ItemVariantEntity {
        $variant         = new ItemVariantEntity();
        $variant->itemId = $itemId;
        $variant->price  = $price;
        $variant->stock  = $stock;
        $variant->label  = $label;

        return $variant;
    }
}
