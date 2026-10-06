<?php

declare(strict_types=1);

namespace Contenir\Commerce\Money;

use Contenir\Commerce\Exception\InvalidArgumentException;

use function is_finite;
use function round;
use function sprintf;
use function var_export;

/**
 * A tax rate held as an integer number of parts per million of the
 * pre-tax amount, so that tax arithmetic never passes through a float: 10%
 * is 100,000 parts per million. A percentage is honoured to four decimal
 * places; finer rates are rounded to the nearest part per million.
 *
 * @api
 */
final readonly class TaxRate
{
    /**
     * The pre-tax amount, in parts.
     */
    public const int PARTS_PER_MILLION = 1_000_000;

    private const int PARTS_PER_PERCENT = 10_000;

    private const int GST_PARTS_PER_MILLION = 100_000;

    private const int MAX_PERCENT = 100;

    private function __construct(
        public int $partsPerMillion,
    ) {}

    /**
     * @throws InvalidArgumentException When the rate is not a finite percentage from 0 to 100.
     */
    public static function fromPercent(int|float $percent): self
    {
        if (! is_finite($percent) || $percent < 0 || $percent > self::MAX_PERCENT) {
            throw new InvalidArgumentException(sprintf(
                'A tax rate must be a percentage from 0 to %d, got %s',
                self::MAX_PERCENT,
                var_export($percent, true),
            ));
        }

        return new self((int) round($percent * self::PARTS_PER_PERCENT));
    }

    /**
     * Australian GST, 10%: the package default.
     */
    public static function gst(): self
    {
        return new self(self::GST_PARTS_PER_MILLION);
    }

    public function equals(self $other): bool
    {
        return $this->partsPerMillion === $other->partsPerMillion;
    }
}
