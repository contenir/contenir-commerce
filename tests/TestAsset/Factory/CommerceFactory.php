<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Factory;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\PurchaseItem;

/**
 * Builds valid commerce objects for tests.
 */
final class CommerceFactory
{
    public static function artwork(
        int $price = 185_000,
        ArtworkStatus $status = ArtworkStatus::Available,
    ): ArtworkEntity {
        $artwork         = new ArtworkEntity();
        $artwork->price  = $price;
        $artwork->status = $status;

        return $artwork;
    }

    public static function customer(): CustomerDetails
    {
        return new CustomerDetails('Avery Buyer', 'avery@example.test', '0400 000 000', 'Will collect Saturday');
    }

    public static function item(int $artworkId, string $title = 'Headland, Dawn', int $price = 185_000): PurchaseItem
    {
        return new PurchaseItem($artworkId, $title, Money::fromCents($price), 'June Hollis');
    }
}
