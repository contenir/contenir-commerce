<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's own order entity: an extra "gift_message" column, and the items
 * relation redeclared to load the site's own order line entity.
 */
#[Table('commerce_order')]
final class SiteOrderEntity extends AbstractOrderEntity
{
    #[Column('gift_message')]
    public ?string $giftMessage = null;

    /**
     * @var Collection<SiteOrderItemEntity>
     */
    #[HasMany(SiteOrderItemEntity::class, foreignKey: 'order_id', orderBy: ['order_item_id' => 'ASC'])]
    public Collection $items;
}
