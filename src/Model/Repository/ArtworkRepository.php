<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Artwork\ItemType;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * Finders for artworks, including the gallery listings keyed by the
 * artwork's resource id.
 *
 * @extends Repository<ArtworkEntity>
 *
 * @api
 */
final class ArtworkRepository extends Repository
{
    /**
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, ArtworkEntity::class);
    }

    /**
     * Ongoing works from selected artists: available originals that are not
     * part of any exhibition.
     *
     * @return array<int, ArtworkEntity> keyed by resource id
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
     * @return array<int, ArtworkEntity> keyed by resource id
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
     * @return array<int, ArtworkEntity> keyed by resource id
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
     * @return array<int, ArtworkEntity> keyed by resource id
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
    public function findCurrent(int $artworkId): ?ArtworkEntity
    {
        $artwork = $this->find($artworkId);
        if (null !== $artwork) {
            $this->em->refresh($artwork);
        }

        return $artwork;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<int, ArtworkEntity> keyed by resource id
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
