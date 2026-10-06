<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Model\Repository\Factory;

use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Entity\ArtistEnquiryEntity;
use Contenir\Commerce\Model\Entity\ArtistEnquiryFileEntity;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Commerce\Model\Entity\EmailLogEntity;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Commerce\Model\Repository\ArtistEnquiryFileRepository;
use Contenir\Commerce\Model\Repository\ArtistEnquiryRepository;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Model\Repository\Factory\RepositoryFactory;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Tests\TestAsset\Container\ArrayContainer;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteArtistEnquiryEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteArtistEnquiryFileEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteArtworkEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteEmailLogEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteOrderEntity;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteOrderItemEntity;
use Contenir\Db\Model\EntityManager;
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
            'artworks'             => [ArtworkRepository::class, ArtworkEntity::class],
            'orders'               => [OrderRepository::class, OrderEntity::class],
            'order items'          => [OrderItemRepository::class, OrderItemEntity::class],
            'artist enquiries'     => [ArtistEnquiryRepository::class, ArtistEnquiryEntity::class],
            'artist enquiry files' => [ArtistEnquiryFileRepository::class, ArtistEnquiryFileEntity::class],
            'email log'            => [EmailLogRepository::class, EmailLogEntity::class],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidEntityClassProvider(): array
    {
        return [
            'a class that does not exist'    => [
                'MissingArtworkEntity',
                'Config "contenir_commerce.artwork_entity" must name an existing subclass of '
                    . AbstractArtworkEntity::class
                    . ', got "MissingArtworkEntity"',
            ],
            'an entity of another table'     => [
                OrderEntity::class,
                'Config "contenir_commerce.artwork_entity" must name an existing subclass of '
                    . AbstractArtworkEntity::class
                    . ', got "'
                    . OrderEntity::class
                    . '"',
            ],
            'the abstract base itself'       => [
                AbstractArtworkEntity::class,
                'Config "contenir_commerce.artwork_entity" must name an existing subclass of '
                    . AbstractArtworkEntity::class
                    . ', got "'
                    . AbstractArtworkEntity::class
                    . '"',
            ],
            'a class name that is no string' => [
                42,
                'Config "contenir_commerce.artwork_entity" must be a string, got int',
            ],
        ];
    }

    /**
     * @return array<string, array{class-string, string, class-string}>
     */
    public static function siteEntityProvider(): array
    {
        return [
            'artworks'             => [ArtworkRepository::class, 'artwork_entity', SiteArtworkEntity::class],
            'orders'               => [OrderRepository::class, 'order_entity', SiteOrderEntity::class],
            'order items'          => [OrderItemRepository::class, 'order_item_entity', SiteOrderItemEntity::class],
            'artist enquiries'     => [
                ArtistEnquiryRepository::class,
                'artist_enquiry_entity',
                SiteArtistEnquiryEntity::class,
            ],
            'artist enquiry files' => [
                ArtistEnquiryFileRepository::class,
                'artist_enquiry_file_entity',
                SiteArtistEnquiryFileEntity::class,
            ],
            'email log'            => [EmailLogRepository::class, 'email_log_entity', SiteEmailLogEntity::class],
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

        (new RepositoryFactory())($this->container('artwork'), ArtworkRepository::class);
    }

    #[DataProvider('invalidEntityClassProvider')]
    #[Test]
    public function anEntityClassThatDoesNotExtendTheBaseIsRejected(mixed $entityClass, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new RepositoryFactory())($this->container(['artwork_entity' => $entityClass]), ArtworkRepository::class);
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

    /**
     * @param array<string, mixed> $services
     */
    #[DataProvider('unusableConfigServiceProvider')]
    #[Test]
    public function withoutAUsableConfigServiceTheDefaultEntityApplies(array $services): void
    {
        $container = new ArrayContainer([
            ...$services,
            EntityManager::class => new EntityManager(static::createStub(AdapterInterface::class)),
        ]);

        static::assertSame(
            ArtworkEntity::class,
            (new RepositoryFactory())($container, ArtworkRepository::class)->newEntity()::class,
        );
    }

    /**
     * @param mixed $commerce the "contenir_commerce" section, or null to leave it out
     */
    private function container(mixed $commerce): ArrayContainer
    {
        return new ArrayContainer([
            'config'             => null === $commerce ? [] : ['contenir_commerce' => $commerce],
            EntityManager::class => new EntityManager(static::createStub(AdapterInterface::class)),
        ]);
    }
}
