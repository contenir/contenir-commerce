<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model;

use Contenir\Commerce\ConfigProvider;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteItemEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteItemVariantEntity;
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
        $this->insert('commerce_order', ['order_ref' => 'LR-2026-0001', 'gift_message' => 'For June']);
        $this->insert('commerce_order_item', ['order_id' => 1, 'title' => 'Rip Tide', 'edition_note' => '3 of 10']);

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
        $item = $this->container->get(ItemRepository::class)->newEntity();
        if ($item instanceof SiteItemEntity) {
            $item->medium = 'Oil on linen';
        }

        $this->em->save($item);

        static::assertSame(
            [SiteItemEntity::class, 'Oil on linen', 'Oil on linen'],
            [$item::class, $this->column('item', 'medium', 'item_id', 1), $item->getTitle()],
        );
    }

    #[Test]
    public function aSiteVariantColumnIsWrittenThroughTheConfiguredEntity(): void
    {
        $variant         = $this->container->get(ItemVariantRepository::class)->newEntity();
        $variant->itemId = 1;
        if ($variant instanceof SiteItemVariantEntity) {
            $variant->frame = 'Oak';
        }

        $this->em->save($variant);

        static::assertSame(
            [SiteItemVariantEntity::class, 'Oak'],
            [$variant::class, $this->column('item_variant', 'frame', 'item_variant_id', 1)],
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
                    'item_entity'         => SiteItemEntity::class,
                    'item_variant_entity' => SiteItemVariantEntity::class,
                    'order_entity'        => SiteOrderEntity::class,
                    'order_item_entity'   => SiteOrderItemEntity::class,
                ],
            ],
            AdapterInterface::class => $this->adapter,
        ];
        $this->container = new ServiceManager($dependencies);
        $this->em        = $this->container->get(EntityManager::class);
    }
}
