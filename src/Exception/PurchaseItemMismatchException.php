<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\PurchaseItem;
use UnexpectedValueException;

use function sprintf;

/**
 * A purchase item does not match its variant as stored: the unit price
 * differs, or the item has a title or the variant a label and the purchase
 * item's differs. The cart was built from stale or untrusted data; rebuild
 * it from the item and variant rows.
 *
 * @api
 */
final class PurchaseItemMismatchException extends UnexpectedValueException implements ExceptionInterface
{
    private function __construct(
        string $message,
        private readonly int $itemVariantId,
    ) {
        parent::__construct($message);
    }

    public static function forLabel(PurchaseItem $item, string $listed): self
    {
        return new self(
            sprintf(
                'Variant %d of "%s" is labelled "%s", not "%s"',
                $item->itemVariantId,
                $item->title,
                $listed,
                $item->variantLabel ?? '',
            ),
            $item->itemVariantId,
        );
    }

    public static function forPrice(PurchaseItem $item, Money $listed): self
    {
        return new self(
            sprintf(
                '"%s" (variant %d) is priced %s, not %s',
                $item->title,
                $item->itemVariantId,
                $listed->format(),
                $item->unitPrice->format(),
            ),
            $item->itemVariantId,
        );
    }

    public static function forTitle(PurchaseItem $item, string $listed): self
    {
        return new self(
            sprintf('The item of variant %d is titled "%s", not "%s"', $item->itemVariantId, $listed, $item->title),
            $item->itemVariantId,
        );
    }

    public function getItemVariantId(): int
    {
        return $this->itemVariantId;
    }
}
