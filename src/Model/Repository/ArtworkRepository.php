<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Artwork\ItemType;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Repository;
use Contenir\Db\Model\Type\TypeRegistry;
use DateTimeImmutable;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Sql;

/**
 * Finders for artworks, including the gallery listings keyed by the
 * artwork's resource id, and the atomic claim that sells a work once.
 *
 * The adapter must be the one the EntityManager runs on (contenir-db-model's
 * "contenir_db_model.adapter" service), so that a claim joins the
 * EntityManager's transaction, and the type registry the one it converts
 * values with.
 *
 * @extends Repository<AbstractArtworkEntity>
 *
 * @api
 */
final class ArtworkRepository extends Repository
{
    /**
     * @param class-string<AbstractArtworkEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(
        EntityManager $em,
        private readonly AdapterInterface $adapter,
        private readonly TypeRegistry $types,
        string $entityClass = ArtworkEntity::class,
    ) {
        parent::__construct($em, $entityClass);
    }

    /**
     * Marks the work sold if, and only if, it is still available, in one
     * conditional UPDATE whose affected-row count says whether this call
     * won it. Of two buyers claiming the same work at the same instant
     * exactly one succeeds, on SQLite, MySQL and PostgreSQL alike, without
     * a row lock held across the read. A work that is sold, withdrawn or
     * deleted cannot be claimed.
     *
     * Run it inside the EntityManager's transaction, so that rolling back
     * releases the claim. An entity of this work the EntityManager already
     * holds is not updated; findCurrent() re-reads it.
     *
     * @throws DbModelException
     */
    public function claim(int $artworkId, DateTimeImmutable $at): bool
    {
        $id      = $this->metadata->getField('artworkId');
        $status  = $this->metadata->getField('status');
        $updated = $this->metadata->getField('updated');
        $sql     = new Sql($this->adapter);
        $claim   = $sql->update($this->metadata->getTableIdentifier())
            ->set([
                $status->columnName  => $this->types->toDatabase($status, ArtworkStatus::Sold),
                $updated->columnName => $this->types->toDatabase($updated, $at),
            ])
            ->where([
                $id->columnName     => $artworkId,
                $status->columnName => $this->types->toDatabase($status, ArtworkStatus::Available),
            ]);

        $result = $sql->prepareStatementForSqlObject($claim)->execute() ?? throw PersistenceException::noResult();

        return 1 === $result->getAffectedRows();
    }

    /**
     * Ongoing works from selected artists: available originals that are not
     * part of any exhibition.
     *
     * @return array<int, AbstractArtworkEntity> keyed by resource id
     *
     * @throws DbModelException
     */
    public function findAvailableOngoing(): array
    {
        return $this->mapByResourceId([
            'exhibitionResourceId' => null,
            'itemType'             => ItemType::Artwork,
            'status'               => ArtworkStatus::Available,
        ]);
    }

    /**
     * @return array<int, AbstractArtworkEntity> keyed by resource id
     *
     * @throws DbModelException
     */
    public function findByArtistResourceId(int $artistResourceId): array
    {
        return $this->mapByResourceId(['artistResourceId' => $artistResourceId]);
    }

    /**
     * Every work in an exhibition, sold ones included (they keep a badge).
     *
     * @return array<int, AbstractArtworkEntity> keyed by resource id
     *
     * @throws DbModelException
     */
    public function findByExhibitionResourceId(int $exhibitionResourceId): array
    {
        return $this->mapByResourceId(['exhibitionResourceId' => $exhibitionResourceId]);
    }

    /**
     * @param list<int> $resourceIds
     *
     * @return array<int, AbstractArtworkEntity> keyed by resource id
     *
     * @throws DbModelException
     */
    public function findByResourceIds(array $resourceIds): array
    {
        if ([] === $resourceIds) {
            return [];
        }

        return $this->mapByResourceId(['resourceId' => $resourceIds]);
    }

    /**
     * The artwork as currently stored, re-read even when this entity manager
     * already holds it, so availability checks never act on a stale copy.
     * Unsaved edits to that artwork are discarded.
     *
     * @throws DbModelException
     */
    public function findCurrent(int $artworkId): ?AbstractArtworkEntity
    {
        $artwork = $this->find($artworkId);
        if (null !== $artwork) {
            $this->em->refresh($artwork);
        }

        return $artwork;
    }

    /**
     * A new, unsaved entity of the class this repository hydrates.
     */
    public function newEntity(): AbstractArtworkEntity
    {
        return new $this->metadata->className();
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<int, AbstractArtworkEntity> keyed by resource id
     *
     * @throws DbModelException
     */
    private function mapByResourceId(array $criteria): array
    {
        $map = [];
        foreach ($this->findBy($criteria) as $artwork) {
            if (null === $artwork->resourceId) {
                continue;
            }

            $map[$artwork->resourceId] = $artwork;
        }

        return $map;
    }
}
