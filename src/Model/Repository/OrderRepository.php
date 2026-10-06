<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<OrderEntity>
 *
 * @api
 */
final class OrderRepository extends Repository
{
    /**
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, OrderEntity::class);
    }

    /**
     * Orders in one status, newest first.
     *
     * @return list<OrderEntity>
     *
     * @throws DbModelException
     */
    public function findByStatus(OrderStatus $status): array
    {
        return $this->findBy(['status' => $status], ['orderId' => 'DESC']);
    }

    /**
     * @throws DbModelException
     */
    public function findOneByCheckoutSessionId(string $sessionId): ?OrderEntity
    {
        return $this->findOneBy(['stripeCheckoutSessionId' => $sessionId]);
    }

    /**
     * @throws DbModelException
     */
    public function findOneByOrderRef(string $orderRef): ?OrderEntity
    {
        return $this->findOneBy(['orderRef' => $orderRef]);
    }
}
