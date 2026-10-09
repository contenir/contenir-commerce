<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractOrderItemEntity;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's own order line entity with an extra "edition_note" column.
 */
#[Table('commerce_order_item')]
final class SiteOrderItemEntity extends AbstractOrderItemEntity
{
    #[Column('edition_note')]
    public ?string $editionNote = null;
}
