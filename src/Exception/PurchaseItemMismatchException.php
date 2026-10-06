<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\PurchaseItem;
use UnexpectedValueException;

use function sprintf;

/**
 * A purchase item does not match its artwork as stored: the price differs,
 * or the artwork maps a title and the item's differs. The cart was built
 * from stale or untrusted data; rebuild it from the artwork rows.
 *
 * @api
 */
final class PurchaseItemMismatchException extends UnexpectedValueException implements ExceptionInterface
{
    private function __construct(
        string $message,
        private readonly int $artworkId,
    ) {
        parent::__construct($message);
    }

    public static function forPrice(PurchaseItem $item, Money $listed): self
    {
        return new self(
            sprintf(
                '"%s" (artwork %d) is priced %s, not %s',
                $item->title,
                $item->artworkId,
                $listed->format(),
                $item->price->format(),
            ),
            $item->artworkId,
        );
    }

    public static function forTitle(PurchaseItem $item, string $listed): self
    {
        return new self(
            sprintf('Artwork %d is titled "%s", not "%s"', $item->artworkId, $listed, $item->title),
            $item->artworkId,
        );
    }

    public function getArtworkId(): int
    {
        return $this->artworkId;
    }
}
