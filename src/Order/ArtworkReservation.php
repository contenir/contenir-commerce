<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use DateTimeImmutable;

/**
 * Availability of the works in an order, always read from the database,
 * and the claim that sells each original once.
 *
 * @internal
 */
final readonly class ArtworkReservation
{
    public function __construct(
        private ArtworkRepository $artworks,
    ) {}

    /**
     * @param list<PurchaseItem> $items
     *
     * @throws ArtworkUnavailableException When a work has been sold, withdrawn or deleted.
     * @throws DbModelException
     */
    public function assertAvailable(array $items): void
    {
        $this->available($items);
    }

    /**
     * Each item with its artwork as currently stored, when every work is
     * still available.
     *
     * @param list<PurchaseItem> $items
     *
     * @return list<array{PurchaseItem, AbstractArtworkEntity}>
     *
     * @throws ArtworkUnavailableException When a work has been sold, withdrawn or deleted.
     * @throws DbModelException
     */
    public function available(array $items): array
    {
        $listed = [];
        $lost   = [];
        foreach ($items as $item) {
            $artwork = $this->artworks->findCurrent($item->artworkId);
            if (null === $artwork || ! $artwork->isAvailable()) {
                $lost[] = $item->title;
                continue;
            }

            $listed[] = [$item, $artwork];
        }

        return [] === $lost ? $listed : throw ArtworkUnavailableException::forTitles($lost);
    }

    /**
     * Claims each work with its own conditional update and returns the
     * titles that could not be claimed: sold to another buyer first,
     * withdrawn or deleted. Claims that succeeded stay in place, so the
     * caller runs this in a transaction and rolls it back when any title is
     * returned.
     *
     * @param list<PurchaseItem> $items
     *
     * @return list<string>
     *
     * @throws DbModelException
     */
    public function claim(array $items, DateTimeImmutable $at): array
    {
        $lost = [];
        foreach ($items as $item) {
            if ($this->artworks->claim($item->artworkId, $at)) {
                continue;
            }

            $lost[] = $item->title;
        }

        return $lost;
    }

    /**
     * Re-reads each work, so that entities the EntityManager already holds
     * reflect a claim committed behind them.
     *
     * @param list<PurchaseItem> $items
     *
     * @throws DbModelException
     */
    public function refresh(array $items): void
    {
        foreach ($items as $item) {
            $this->artworks->findCurrent($item->artworkId);
        }
    }
}
