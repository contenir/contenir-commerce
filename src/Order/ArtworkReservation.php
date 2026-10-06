<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use DateTimeImmutable;

use function in_array;
use function sprintf;

/**
 * Availability of the works in an order, always read from the database,
 * and the claim that sells them: each original can be sold once, so a work
 * may appear in an order once.
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
        $lost = [];
        foreach ($items as $item) {
            if (true === $this->artworks->findCurrent($item->artworkId)?->isAvailable()) {
                continue;
            }

            $lost[] = $item->title;
        }

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
