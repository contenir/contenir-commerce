<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model;

use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteArtworkEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteOrderEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteOrderItemEntity;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use Contenir\Db\Model\ConfigProvider as DbModelConfigProvider;
use Contenir\Db\Model\EntityManager;
use Laminas\ServiceManager\ServiceManager;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_merge_recursive;

/**
 * Site entities configured under "contenir_commerce", wired through a real
 * service manager over an in-memory database: the extra columns are read
 * and written, and a redeclared relation loads the site's line entity.
 */
#[Group('integration')]
#[Group('repository')]
final class SiteEntityTest extends TestCase
{
    use SqliteDatabaseTrait;

    private ServiceManager $container;

    #[Test]
    public function aRedeclaredRelationLoadsTheSitesOwnLineEntity(): void
    {
        $this->insert('gallery_order', ['order_ref' => 'LR-2026-0001', 'gift_message' => 'For June']);
        $this->insert('gallery_order_item', ['order_id' => 1, 'title' => 'Rip Tide', 'edition_note' => '3 of 10']);

        $order = $this->container->get(OrderRepository::class)->find(1);

        static::assertSame(
            [SiteOrderEntity::class, 'For June', [[SiteOrderItemEntity::class, '3 of 10']]],
            [
                $order::class,
                $order instanceof SiteOrderEntity ? $order->giftMessage : null,
                array_map(
                    static fn(object $line): array => [
                        $line::class,
                        $line instanceof SiteOrderItemEntity ? $line->editionNote : null,
                    ],
                    $order?->items->toArray() ?? [],
                ),
            ],
        );
    }

    #[Test]
    public function aSiteColumnIsWrittenThroughTheConfiguredEntity(): void
    {
        $artworks       = $this->container->get(ArtworkRepository::class);
        $artwork        = $artworks->newEntity();
        $artwork->price = 185_000;
        if ($artwork instanceof SiteArtworkEntity) {
            $artwork->title = 'Headland, Dawn';
        }

        $this->em->save($artwork);

        static::assertSame(
            [SiteArtworkEntity::class, 'Headland, Dawn', 'Headland, Dawn'],
            [$artwork::class, $this->column('artwork', 'title', 'artwork_id', 1), $artwork->getTitle()],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();

        $dependencies = array_merge_recursive(
            (new DbModelConfigProvider())()['dependencies'],
            (new ConfigProvider())()['dependencies'],
        );
        $dependencies['services'] = [
            'config'                => [
                ...(new DbModelConfigProvider())(),
                'contenir_commerce' => [
                    'artwork_entity'    => SiteArtworkEntity::class,
                    'order_entity'      => SiteOrderEntity::class,
                    'order_item_entity' => SiteOrderItemEntity::class,
                ],
            ],
            AdapterInterface::class => $this->adapter,
        ];
        $this->container = new ServiceManager($dependencies);
        $this->em        = $this->container->get(EntityManager::class);
    }
}
