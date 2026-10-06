<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Artwork\ItemType;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\Money;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use DateTimeImmutable;

/**
 * A saleable item: an original artwork or a retail product. The resource
 * ids link it to the Contenir resource pages for the work, its artist and
 * its exhibition.
 *
 * @api
 *
 * @mago-expect lint:too-many-properties One property per column of the artwork table.
 */
#[Table('artwork')]
final class ArtworkEntity
{
    #[Id(generated: true)]
    #[Column('artwork_id')]
    public ?int $artworkId = null;

    #[Column('resource_id')]
    public ?int $resourceId = null;

    #[Column('artist_resource_id')]
    public ?int $artistResourceId = null;

    #[Column('exhibition_resource_id')]
    public ?int $exhibitionResourceId = null;

    #[Column('item_type')]
    public ItemType $itemType = ItemType::Artwork;

    /**
     * GST-inclusive price in cents.
     */
    #[Column]
    public int $price = 0;

    #[Column]
    public ArtworkStatus $status = ArtworkStatus::Available;

    #[Column]
    public ?string $medium = null;

    #[Column]
    public ?string $dimensions = null;

    #[Column]
    public ?string $year = null;

    #[Column('edition_details')]
    public ?string $editionDetails = null;

    #[Column('external_sale_url')]
    public ?string $externalSaleUrl = null;

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

    public function isAvailable(): bool
    {
        return ArtworkStatus::Available === $this->status;
    }
}
