<?php

declare(strict_types=1);

namespace Contenir\Commerce\Money;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\OverflowException;

use function intdiv;
use function number_format;
use function sprintf;

use const PHP_INT_MAX;

/**
 * A tax-inclusive amount held as integer cents, in the site's one currency
 * (AUD unless configured otherwise; Money does not carry it). Every
 * operation is integer arithmetic: no amount ever passes through a float.
 *
 * Prices are tax-inclusive, so the tax component of an amount at rate r is
 * amount × r / (1 + r), rounded half up to the nearest cent. At the default
 * 10% GST that is one eleventh, which is never exactly half a cent, so the
 * result is the same as RC1's for every amount.
 *
 * @api
 */
final readonly class Money
{
    private const int CENTS_PER_DOLLAR = 100;

    /**
     * @throws InvalidArgumentException When the amount is negative.
     */
    public function __construct(
        public int $amount,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException(sprintf('Money cannot be negative, got %d cents', $amount));
        }
    }

    /**
     * @throws InvalidArgumentException When the amount is negative.
     */
    public static function fromCents(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * @throws InvalidArgumentException When the amount is negative.
     * @throws OverflowException When the sum exceeds the integer range.
     */
    public function add(self $other): self
    {
        if ($other->amount > (PHP_INT_MAX - $this->amount)) {
            throw OverflowException::forOperation('addition');
        }

        return new self($this->amount + $other->amount);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    /**
     * Dollars with thousands separators and two decimal places: "$1,850.00".
     */
    public function format(): string
    {
        return sprintf(
            '$%s.%02d',
            number_format(num: intdiv($this->amount, self::CENTS_PER_DOLLAR)),
            $this->amount % self::CENTS_PER_DOLLAR,
        );
    }

    /**
     * The tax included in this amount at $rate (10% GST when null), rounded
     * half up to the nearest cent: a remainder of exactly half a cent rounds
     * up. The name predates configurable rates; pass the configured
     * CommerceSettings::$taxRate for another tax.
     *
     * With p the rate in parts per million and W = 1,000,000 + p the
     * tax-inclusive whole, the amount is split as q × W + r so that neither
     * product can overflow: the tax is q × p plus r × p / W, rounded.
     */
    public function gstComponent(?TaxRate $rate = null): self
    {
        $parts = ($rate ?? TaxRate::gst())->partsPerMillion;
        $whole = TaxRate::PARTS_PER_MILLION + $parts;
        $rest  = ($this->amount % $whole) * $parts;
        $cents = (intdiv($this->amount, $whole) * $parts) + intdiv($rest, $whole);
        if ((2 * ($rest % $whole)) >= $whole) {
            ++$cents;
        }

        return new self($cents);
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    /**
     * @throws InvalidArgumentException When the quantity is negative.
     * @throws OverflowException When the product exceeds the integer range.
     */
    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException(sprintf('Quantity cannot be negative, got %d', $quantity));
        }

        if ($quantity > 0 && $this->amount > intdiv(PHP_INT_MAX, $quantity)) {
            throw OverflowException::forOperation('multiplication');
        }

        return new self($this->amount * $quantity);
    }

    /**
     * @throws InvalidArgumentException When the result would be negative.
     */
    public function subtract(self $other): self
    {
        return new self($this->amount - $other->amount);
    }
}
