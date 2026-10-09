<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Item\ItemStatus;
use Contenir\Commerce\Model\Entity\AbstractItemEntity;
use Contenir\Commerce\Model\Entity\ItemEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<AbstractItemEntity>
 *
 * @api
 */
final class ItemRepository extends Repository
{
    /**
     * @param class-string<AbstractItemEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em, string $entityClass = ItemEntity::class)
    {
        parent::__construct($em, $entityClass);
    }

    /**
     * The item as stored now, or null once its row is gone: an entity the
     * EntityManager already holds is re-read, so a change made since it was
     * loaded shows.
     *
     * @throws DbModelException
     */
    public function findCurrent(int $itemId): ?AbstractItemEntity
    {
        $item = $this->findOneBy(['itemId' => $itemId]);
        if (null !== $item) {
            $this->em->refresh($item);
        }

        return $item;
    }

    /**
     * The items offered for sale, oldest first.
     *
     * @return list<AbstractItemEntity>
     *
     * @throws DbModelException
     */
    public function findListed(): array
    {
        return $this->findBy(['status' => ItemStatus::Listed], ['itemId' => 'ASC']);
    }

    public function newEntity(): AbstractItemEntity
    {
        return new $this->metadata->className();
    }
}
