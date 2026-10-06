<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<OrderItemEntity>
 *
 * @api
 */
final class OrderItemRepository extends Repository
{
    /**
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, OrderItemEntity::class);
    }

    /**
     * The lines of one order, in the order they were added.
     *
     * @return list<OrderItemEntity>
     *
     * @throws DbModelException
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findBy(['orderId' => $orderId], ['orderItemId' => 'ASC']);
    }
}
