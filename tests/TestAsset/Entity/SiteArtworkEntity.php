<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;
use Override;

/**
 * A site's own artwork entity that maps an extra "title" column and
 * exposes it through getTitle(), so new orders check item titles too.
 */
#[Table('artwork')]
final class SiteArtworkEntity extends AbstractArtworkEntity
{
    #[Column]
    public ?string $title = null;

    #[Override]
    public function getTitle(): ?string
    {
        return $this->title;
    }
}
