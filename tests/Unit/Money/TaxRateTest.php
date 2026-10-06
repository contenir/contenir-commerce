<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Money;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\TaxRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const INF;
use const NAN;

#[Group('unit')]
final class TaxRateTest extends TestCase
{
    /**
     * @return array<string, array{float|int, string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'negative'            => [-0.01, 'A tax rate must be a percentage from 0 to 100, got -0.01'],
            'over one hundred'    => [100.01, 'A tax rate must be a percentage from 0 to 100, got 100.01'],
            'a whole number over' => [101, 'A tax rate must be a percentage from 0 to 100, got 101'],
            'not a number'        => [NAN, 'A tax rate must be a percentage from 0 to 100, got NAN'],
            'infinite'            => [INF, 'A tax rate must be a percentage from 0 to 100, got INF'],
        ];
    }

    /**
     * @return array<string, array{int|float, int}>
     */
    public static function percentProvider(): array
    {
        return [
            'no tax'                     => [0, 0],
            'Australian GST'             => [10, 100_000],
            'a fractional rate'          => [12.5, 125_000],
            'four decimal places'        => [8.8755, 88_755],
            'a float just below a part'  => [0.57, 5_700],
            'a float just above a part'  => [0.07, 700],
            'finer than a part, rounded' => [0.000_04, 0],
            'the whole price is tax'     => [100, 1_000_000],
        ];
    }

    #[DataProvider('percentProvider')]
    #[Test]
    public function aPercentageIsHeldInPartsPerMillion(int|float $percent, int $partsPerMillion): void
    {
        static::assertSame($partsPerMillion, TaxRate::fromPercent($percent)->partsPerMillion);
    }

    #[DataProvider('invalidProvider')]
    #[Test]
    public function aRateOutsideZeroToOneHundredIsRejected(int|float $percent, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        TaxRate::fromPercent($percent);
    }

    #[Test]
    public function gstIsTenPercent(): void
    {
        static::assertSame(
            [true, false],
            [TaxRate::gst()->equals(TaxRate::fromPercent(10)), TaxRate::gst()->equals(TaxRate::fromPercent(15))],
        );
    }
}
