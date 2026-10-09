<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\AbstractEmailLogEntity;
use Contenir\Commerce\Model\Entity\EmailLogEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<AbstractEmailLogEntity>
 *
 * @api
 */
final class EmailLogRepository extends Repository
{
    /**
     * @param class-string<AbstractEmailLogEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em, string $entityClass = EmailLogEntity::class)
    {
        parent::__construct($em, $entityClass);
    }

    /**
     * The emails sent about one order, newest first.
     *
     * @return list<AbstractEmailLogEntity>
     *
     * @throws DbModelException
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findBy(['orderId' => $orderId], ['emailLogId' => 'DESC']);
    }

    /**
     * A new, unsaved entity of the class this repository hydrates.
     */
    public function newEntity(): AbstractEmailLogEntity
    {
        return new $this->metadata->className();
    }
}
