<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<AbstractOrderEntity>
 *
 * @api
 */
final class OrderRepository extends Repository
{
    /**
     * @param class-string<AbstractOrderEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em, string $entityClass = OrderEntity::class)
    {
        parent::__construct($em, $entityClass);
    }

    /**
     * Orders in one status, newest first.
     *
     * @return list<AbstractOrderEntity>
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
    public function findOneByCheckoutSessionId(string $sessionId): ?AbstractOrderEntity
    {
        return $this->findOneBy(['stripeCheckoutSessionId' => $sessionId]);
    }

    /**
     * @throws DbModelException
     */
    public function findOneByOrderRef(string $orderRef): ?AbstractOrderEntity
    {
        return $this->findOneBy(['orderRef' => $orderRef]);
    }

    /**
     * A new, unsaved entity of the class this repository hydrates.
     */
    public function newEntity(): AbstractOrderEntity
    {
        return new $this->metadata->className();
    }
}
