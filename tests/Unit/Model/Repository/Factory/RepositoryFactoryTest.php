<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Model\Repository\Factory;

use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;
use Contenir\Commerce\Model\Entity\EmailLogEntity;
use Contenir\Commerce\Model\Entity\ItemEntity;
use Contenir\Commerce\Model\Entity\ItemVariantEntity;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Model\Repository\Factory\RepositoryFactory;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Tests\TestAsset\Container\ArrayContainer;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteEmailLogEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteItemEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteItemVariantEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteOrderEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteOrderItemEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The factory against an EntityManager over a stub adapter: building a
 * repository reads only entity metadata, so no query runs.
 */
#[Group('unit')]
final class RepositoryFactoryTest extends TestCase
{
    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function defaultEntityProvider(): array
    {
        return [
            'items'         => [ItemRepository::class, ItemEntity::class],
            'item variants' => [ItemVariantRepository::class, ItemVariantEntity::class],
            'orders'        => [OrderRepository::class, OrderEntity::class],
            'order items'   => [OrderItemRepository::class, OrderItemEntity::class],
            'email log'     => [EmailLogRepository::class, EmailLogEntity::class],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidEntityClassProvider(): array
    {
        return [
            'a class that does not exist'    => [
                'MissingVariantEntity',
                'Config "contenir_commerce.item_variant_entity" must name an existing subclass of '
                    . AbstractItemVariantEntity::class
                    . ', got "MissingVariantEntity"',
            ],
            'an entity of another table'     => [
                OrderEntity::class,
                'Config "contenir_commerce.item_variant_entity" must name an existing subclass of '
                    . AbstractItemVariantEntity::class
                    . ', got "'
                    . OrderEntity::class
                    . '"',
            ],
            'the abstract base itself'       => [
                AbstractItemVariantEntity::class,
                'Config "contenir_commerce.item_variant_entity" must name an existing subclass of '
                    . AbstractItemVariantEntity::class
                    . ', got "'
                    . AbstractItemVariantEntity::class
                    . '"',
            ],
            'a class name that is no string' => [
                42,
                'Config "contenir_commerce.item_variant_entity" must be a string, got int',
            ],
        ];
    }

    /**
     * @return array<string, array{class-string, string, class-string}>
     */
    public static function siteEntityProvider(): array
    {
        return [
            'items'         => [ItemRepository::class, 'item_entity', SiteItemEntity::class],
            'item variants' => [ItemVariantRepository::class, 'item_variant_entity', SiteItemVariantEntity::class],
            'orders'        => [OrderRepository::class, 'order_entity', SiteOrderEntity::class],
            'order items'   => [OrderItemRepository::class, 'order_item_entity', SiteOrderItemEntity::class],
            'email log'     => [EmailLogRepository::class, 'email_log_entity', SiteEmailLogEntity::class],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unusableConfigServiceProvider(): array
    {
        return [
            'no config service'         => [[]],
            'a config that is no array' => [['config' => 'nope']],
        ];
    }

    #[Test]
    public function aCommerceSectionThatIsNotAnArrayIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Config "contenir_commerce" must be an array, got string');

        (new RepositoryFactory())($this->container('item'), ItemVariantRepository::class);
    }

    #[Test]
    public function aNamedAdapterServiceOfTheWrongTypeIsRejected(): void
    {
        $container = new ArrayContainer([
            ...$this->services(),
            'config'     => ['contenir_db_model' => ['adapter' => 'db.gallery']],
            'db.gallery' => 'not an adapter',
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Service "db.gallery" must be a PhpDb\Adapter\AdapterInterface, got string');

        (new RepositoryFactory())($container, ItemVariantRepository::class);
    }

    #[DataProvider('invalidEntityClassProvider')]
    #[Test]
    public function anEntityClassThatDoesNotExtendTheBaseIsRejected(mixed $entityClass, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new RepositoryFactory())($this->container([
            'item_variant_entity' => $entityClass,
        ]), ItemVariantRepository::class);
    }

    #[Test]
    public function anUnknownRepositoryIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No repository "OtherRepository" is built by this factory');

        (new RepositoryFactory())($this->container([]), requestedName: 'OtherRepository');
    }

    /**
     * @param class-string $repository
     * @param class-string $entity
     */
    #[DataProvider('siteEntityProvider')]
    #[Test]
    public function eachRepositoryHydratesTheConfiguredSiteEntity(string $repository, string $key, string $entity): void
    {
        $built = (new RepositoryFactory())($this->container([$key => $entity]), $repository);

        static::assertSame($entity, $built->newEntity()::class);
    }

    /**
     * @param class-string $repository
     * @param class-string $entity
     */
    #[DataProvider('defaultEntityProvider')]
    #[Test]
    public function eachRepositoryHydratesTheDefaultEntityWithoutConfig(string $repository, string $entity): void
    {
        $built = (new RepositoryFactory())($this->container(null), $repository);

        static::assertSame([$repository, $entity], [$built::class, $built->newEntity()::class]);
    }

    #[Test]
    public function theVariantRepositoryUsesTheAdapterServiceContenirDbModelNames(): void
    {
        $services = $this->services();
        unset($services[AdapterInterface::class]);
        $container = new ArrayContainer([
            ...$services,
            'config'     => ['contenir_db_model' => ['adapter' => 'db.gallery']],
            'db.gallery' => static::createStub(AdapterInterface::class),
        ]);

        static::assertInstanceOf(
            ItemVariantRepository::class,
            (new RepositoryFactory())($container, ItemVariantRepository::class),
        );
    }

    /**
     * @param array<string, mixed> $services
     */
    #[DataProvider('unusableConfigServiceProvider')]
    #[Test]
    public function withoutAUsableConfigServiceTheDefaultEntityApplies(array $services): void
    {
        $container = new ArrayContainer([...$this->services(), ...$services]);

        static::assertSame(
            ItemVariantEntity::class,
            (new RepositoryFactory())($container, ItemVariantRepository::class)->newEntity()::class,
        );
    }

    /**
     * @param mixed $commerce the "contenir_commerce" section, or null to leave it out
     */
    private function container(mixed $commerce): ArrayContainer
    {
        return new ArrayContainer([
            ...$this->services(),
            'config' => null === $commerce ? [] : ['contenir_commerce' => $commerce],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function services(): array
    {
        $adapter = static::createStub(AdapterInterface::class);

        return [
            EntityManager::class    => new EntityManager($adapter),
            AdapterInterface::class => $adapter,
            TypeRegistry::class     => TypeRegistry::withDefaults(),
        ];
    }
}
