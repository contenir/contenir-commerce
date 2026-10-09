<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's own variant entity with an extra "frame" column.
 */
#[Table('item_variant')]
final class SiteItemVariantEntity extends AbstractItemVariantEntity
{
    #[Column]
    public ?string $frame = null;
}
