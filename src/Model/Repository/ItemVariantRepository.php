<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;
use Contenir\Commerce\Model\Entity\ItemVariantEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Repository;
use Contenir\Db\Model\Type\TypeRegistry;
use DateTimeImmutable;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument;
use PhpDb\Sql\Expression;
use PhpDb\Sql\Sql;

/**
 * Finders for item variants, and the atomic claim that takes units out of
 * stock.
 *
 * The adapter must be the one the EntityManager runs on (contenir-db-model's
 * "contenir_db_model.adapter" service), so that a claim joins the
 * EntityManager's transaction, and the type registry the one it converts
 * values with.
 *
 * @extends Repository<AbstractItemVariantEntity>
 *
 * @api
 */
final class ItemVariantRepository extends Repository
{
    /**
     * @param class-string<AbstractItemVariantEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(
        EntityManager $em,
        private readonly AdapterInterface $adapter,
        private readonly TypeRegistry $types,
        string $entityClass = ItemVariantEntity::class,
    ) {
        parent::__construct($em, $entityClass);
    }

    /**
     * Takes $quantity units out of the variant's stock if, and only if, at
     * least that many remain, in one conditional UPDATE whose affected-row
     * count says whether this call got them. The UPDATE reads the current
     * row whatever the transaction's isolation level, so of two buyers
     * claiming the last unit at the same instant exactly one succeeds, on
     * SQLite, MySQL and PostgreSQL alike. A variant whose stock is not
     * tracked, or that has been deleted, cannot be claimed: check
     * isStockTracked() first.
     *
     * Run it inside the EntityManager's transaction, so that rolling back
     * returns the units. An entity of this variant the EntityManager already
     * holds is not updated; findCurrent() re-reads it.
     *
     * @throws DbModelException
     */
    public function claim(int $itemVariantId, int $quantity, DateTimeImmutable $at): bool
    {
        $id      = $this->metadata->getField('itemVariantId');
        $stock   = $this->metadata->getField('stock');
        $updated = $this->metadata->getField('updated');
        $sql     = new Sql($this->adapter);
        $claim   = $sql->update($this->metadata->getTableIdentifier())->set([
            $stock->columnName   => new Expression('? - ?', [
                Argument::identifier($stock->columnName),
                Argument::value($quantity),
            ]),
            $updated->columnName => $this->types->toDatabase($updated, $at),
        ]);
        $claim
            ->where
            ->equalTo(Argument::identifier($id->columnName), Argument::value($itemVariantId))
            ->greaterThanOrEqualTo(Argument::identifier($stock->columnName), Argument::value($quantity));

        $result = $sql->prepareStatementForSqlObject($claim)->execute() ?? throw PersistenceException::noResult();

        return 1 === $result->getAffectedRows();
    }

    /**
     * An item's variants, in display order.
     *
     * @return list<AbstractItemVariantEntity>
     *
     * @throws DbModelException
     */
    public function findByItemId(int $itemId): array
    {
        return $this->findBy(['itemId' => $itemId], ['sequence' => 'ASC', 'itemVariantId' => 'ASC']);
    }

    /**
     * The variant as stored now, or null once its row is gone: an entity
     * the EntityManager already holds is re-read, so a claim made since it
     * was loaded shows in its stock.
     *
     * @throws DbModelException
     */
    public function findCurrent(int $itemVariantId): ?AbstractItemVariantEntity
    {
        $variant = $this->findOneBy(['itemVariantId' => $itemVariantId]);
        if (null !== $variant) {
            $this->em->refresh($variant);
        }

        return $variant;
    }

    public function newEntity(): AbstractItemVariantEntity
    {
        return new $this->metadata->className();
    }
}
