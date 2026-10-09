<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractItemEntity;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;
use Override;

/**
 * A site's own item entity: an extra "medium" column, and a title that
 * falls back to it, standing for a title kept elsewhere.
 */
#[Table('item')]
final class SiteItemEntity extends AbstractItemEntity
{
    #[Column]
    public ?string $medium = null;

    #[Override]
    public function getTitle(): ?string
    {
        return $this->title ?? $this->medium;
    }
}
