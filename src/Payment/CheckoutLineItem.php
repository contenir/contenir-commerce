<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\Money;

use function sprintf;

/**
 * @api
 */
final readonly class CheckoutLineItem
{
    /**
     * @throws InvalidArgumentException When the quantity is below 1.
     */
    public function __construct(
        public string $name,
        public Money $price,
        public int $quantity = 1,
        public ?string $description = null,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException(sprintf('Line item quantity must be at least 1, got %d', $quantity));
        }
    }
}
