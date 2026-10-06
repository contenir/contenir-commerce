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
 * A GST-inclusive AUD amount held as integer cents. Every operation is
 * integer arithmetic: no amount ever passes through a float.
 *
 * Prices are GST-inclusive, so the GST component of an amount is one
 * eleventh of it, rounded to the nearest cent. One eleventh of a whole
 * number of cents is never exactly half a cent, so there is no tie to break.
 *
 * @api
 */
final readonly class Money
{
    /**
     * The GST rate is 10%, so a GST-inclusive amount is 11 parts, one of
     * them GST.
     */
    private const int GST_PARTS = 11;

    /**
     * A remainder of this many elevenths of a cent or more rounds up.
     */
    private const int GST_ROUND_UP_FROM = 6;

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
     * The GST included in this amount: one eleventh, rounded half up to the
     * nearest cent.
     */
    public function gstComponent(): self
    {
        $cents = intdiv($this->amount, self::GST_PARTS);
        if (($this->amount % self::GST_PARTS) >= self::GST_ROUND_UP_FROM) {
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
