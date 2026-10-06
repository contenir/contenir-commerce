<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository\Factory;

use Contenir\Commerce\Config\ConfigReader;
use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Entity\AbstractArtistEnquiryEntity;
use Contenir\Commerce\Model\Entity\AbstractArtistEnquiryFileEntity;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Entity\AbstractEmailLogEntity;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Model\Entity\AbstractOrderItemEntity;
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
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the six repositories over the container's EntityManager, each for
 * the entity class configured under "contenir_commerce": "artwork_entity",
 * "order_entity", "order_item_entity", "artist_enquiry_entity",
 * "artist_enquiry_file_entity" and "email_log_entity". Without a key the
 * repository hydrates the package's default entity.
 *
 * ArtworkRepository also gets the database adapter the EntityManager runs on
 * (the service named by "contenir_db_model.adapter", AdapterInterface by
 * default) and contenir-db-model's TypeRegistry, for its atomic claim.
 *
 * @api
 */
final class RepositoryFactory
{
    /**
     * @throws ConfigurationException When a service or the entity class is not usable.
     * @throws ContainerExceptionInterface
     * @throws DbModelException When the entity class is not a valid mapping.
     */
    private function artworks(ContainerInterface $container, EntityManager $em, ConfigReader $config): ArtworkRepository
    {
        $adapter = ConfigReader::fromContainer($container, section: 'contenir_db_model')->string(
            'adapter',
            AdapterInterface::class,
        );

        return new ArtworkRepository(
            $em,
            ServiceLocator::get($container, AdapterInterface::class, $adapter),
            ServiceLocator::get($container, TypeRegistry::class),
            $config->className('artwork_entity', AbstractArtworkEntity::class, ArtworkEntity::class),
        );
    }

    /**
     * @return Repository<object>
     *
     * @throws ConfigurationException When an entity class, or the EntityManager service, is not usable.
     * @throws ContainerExceptionInterface
     * @throws DbModelException When the entity class is not a valid mapping.
     */
    public function __invoke(ContainerInterface $container, string $requestedName): Repository
    {
        $config = ConfigReader::fromContainer($container);
        $em     = ServiceLocator::get($container, EntityManager::class);

        return match ($requestedName) {
            ArtworkRepository::class           => $this->artworks($container, $em, $config),
            OrderRepository::class => new OrderRepository(
                $em,
                $config->className('order_entity', AbstractOrderEntity::class, OrderEntity::class),
            ),
            OrderItemRepository::class => new OrderItemRepository(
                $em,
                $config->className('order_item_entity', AbstractOrderItemEntity::class, OrderItemEntity::class),
            ),
            ArtistEnquiryRepository::class => new ArtistEnquiryRepository(
                $em,
                $config->className(
                    'artist_enquiry_entity',
                    AbstractArtistEnquiryEntity::class,
                    ArtistEnquiryEntity::class,
                ),
            ),
            ArtistEnquiryFileRepository::class => new ArtistEnquiryFileRepository(
                $em,
                $config->className(
                    'artist_enquiry_file_entity',
                    AbstractArtistEnquiryFileEntity::class,
                    ArtistEnquiryFileEntity::class,
                ),
            ),
            EmailLogRepository::class => new EmailLogRepository(
                $em,
                $config->className('email_log_entity', AbstractEmailLogEntity::class, EmailLogEntity::class),
            ),
            default                            => throw ConfigurationException::unknownRepository($requestedName),
        };
    }
}
