<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Artwork\ItemType;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * Finders for artworks, including the gallery listings keyed by the
 * artwork's resource id.
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
    public function __construct(EntityManager $em, string $entityClass = ArtworkEntity::class)
    {
        parent::__construct($em, $entityClass);
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
