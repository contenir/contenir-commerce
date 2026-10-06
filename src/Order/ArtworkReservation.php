<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;

use function in_array;
use function sprintf;

/**
 * Availability of the works in an order, always read from the database:
 * each original can be sold once, so a work may appear in an order once.
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
        $lost = $this->checkAvailability($items)['lost'];
        if ([] !== $lost) {
            throw ArtworkUnavailableException::forTitles($lost);
        }
    }

    /**
     * @param list<PurchaseItem> $items
     *
     * @throws InvalidArgumentException When a work appears more than once.
     */
    public function assertDistinct(array $items): void
    {
        $seen = [];
        foreach ($items as $item) {
            if (in_array($item->artworkId, $seen, strict: true)) {
                throw new InvalidArgumentException(sprintf('"%s" appears in the order more than once', $item->title));
            }

            $seen[] = $item->artworkId;
        }
    }

    /**
     * Reads each item's artwork as currently stored and splits the items
     * into the available artworks and the titles that are no longer
     * available (sold, withdrawn or deleted).
     *
     * @param list<PurchaseItem> $items
     *
     * @return array{available: list<AbstractArtworkEntity>, lost: list<string>}
     *
     * @throws DbModelException
     */
    public function checkAvailability(array $items): array
    {
        $available = [];
        $lost      = [];
        foreach ($items as $item) {
            $artwork = $this->artworks->findCurrent($item->artworkId);
            if (null !== $artwork && $artwork->isAvailable()) {
                $available[] = $artwork;
                continue;
            }

            $lost[] = $item->title;
        }

        return ['available' => $available, 'lost' => $lost];
    }
}
