<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Item\ItemStatus;
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * Something the site sells: an artwork, a print, a treatment. It is bought
 * through one of its variants, which carry the price and stock; an item
 * sold in one form only has a single variant.
 *
 * Extend it with a final class carrying #[Table('item')] (or use
 * ItemEntity) and add the site's own columns there; point the
 * "contenir_commerce.item_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 */
abstract class AbstractItemEntity
{
    #[Id(generated: true)]
    #[Column('item_id')]
    public ?int $itemId = null;

    #[Column]
    public ?string $title = null;

    #[Column]
    public ?string $description = null;

    #[Column]
    public ItemStatus $status = ItemStatus::Listed;

    #[Column]
    public ?DateTimeImmutable $created = null;

    #[Column]
    public ?DateTimeImmutable $updated = null;

    /**
     * The item's variants as the default ItemVariantEntity. A site that
     * configures its own variant entity redeclares this property on its
     * item entity with that class in #[HasMany].
     *
     * @var Collection<AbstractItemVariantEntity>
     */
    #[HasMany(
        ItemVariantEntity::class,
        foreignKey: 'item_id',
        orderBy: ['sequence' => 'ASC', 'item_variant_id' => 'ASC'],
    )]
    public Collection $variants;

    /**
     * The title new orders check each purchase item's title against; null
     * skips the check. Override it when the title lives elsewhere, such as
     * on a CMS page the item belongs to.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function isListed(): bool
    {
        return ItemStatus::Listed === $this->status;
    }
}
