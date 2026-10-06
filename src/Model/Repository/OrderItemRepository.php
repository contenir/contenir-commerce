<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\AbstractOrderItemEntity;
use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<AbstractOrderItemEntity>
 *
 * @api
 */
final class OrderItemRepository extends Repository
{
    /**
     * @param class-string<AbstractOrderItemEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em, string $entityClass = OrderItemEntity::class)
    {
        parent::__construct($em, $entityClass);
    }

    /**
     * The lines of one order, in the order they were added.
     *
     * @return list<AbstractOrderItemEntity>
     *
     * @throws DbModelException
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findBy(['orderId' => $orderId], ['orderItemId' => 'ASC']);
    }

    /**
     * A new, unsaved entity of the class this repository hydrates.
     */
    public function newEntity(): AbstractOrderItemEntity
    {
        return new $this->metadata->className();
    }
}
