<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Money;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\OverflowException;
use Contenir\Commerce\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function intdiv;

use const PHP_INT_MAX;

#[Group('unit')]
final class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int}>
     */
    public static function additionProvider(): array
    {
        return [
            'two amounts'         => [1_000, 250, 1_250],
            'zero'                => [1_000, 0, 1_000],
            'up to the int limit' => [PHP_INT_MAX - 1, 1, PHP_INT_MAX],
        ];
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'thousands with separator' => [185_000, '$1,850.00'],
            'cents preserved'          => [1_999, '$19.99'],
            'single-digit cents'       => [105, '$1.05'],
            'cents only'               => [7, '$0.07'],
            'zero'                     => [0, '$0.00'],
            'millions'                 => [123_456_789, '$1,234,567.89'],
            'beyond float precision'   => [PHP_INT_MAX, '$92,233,720,368,547,758.07'],
        ];
    }

    /**
     * Every remainder of a division by eleven, plus large amounts.
     *
     * @return array<string, array{int, int}>
     */
    public static function gstProvider(): array
    {
        return [
            'zero'                          => [0, 0],
            'remainder 1 rounds down'       => [1, 0],
            'remainder 5 rounds down'       => [5, 0],
            'remainder 6 rounds up'         => [6, 1],
            'remainder 10 rounds up'        => [10, 1],
            'exact eleventh'                => [11_000, 1_000],
            'remainder 1 on a larger total' => [100, 9],
            'remainder 6 on a larger total' => [17, 2],
            'a typical artwork'             => [185_000, 16_818],
            'an order of two works'         => [283_000, 25_727],
            'the largest amount'            => [PHP_INT_MAX, 838_488_366_986_797_801],
        ];
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function multiplicationProvider(): array
    {
        return [
            'by three'            => [1_000, 3, 3_000],
            'by one'              => [1_000, 1, 1_000],
            'by zero'             => [1_000, 0, 0],
            'up to the int limit' => [intdiv(PHP_INT_MAX, num2: 2), 2, PHP_INT_MAX - 1],
        ];
    }

    #[Test]
    public function additionBeyondTheIntegerRangeIsRejected(): void
    {
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Money addition overflows the integer range of cents');

        Money::fromCents(PHP_INT_MAX)->add(Money::fromCents(1));
    }

    #[DataProvider('additionProvider')]
    #[Test]
    public function addsAmounts(int $left, int $right, int $sum): void
    {
        static::assertSame($sum, Money::fromCents($left)->add(Money::fromCents($right))->amount);
    }

    #[Test]
    public function equalityComparesAmounts(): void
    {
        static::assertSame(
            [true, false],
            [
                Money::fromCents(500)->equals(Money::fromCents(500)),
                Money::fromCents(500)->equals(Money::fromCents(501)),
            ],
        );
    }

    #[DataProvider('formatProvider')]
    #[Test]
    public function formatsAsDollarsWithoutFloatingPoint(int $amount, string $expected): void
    {
        static::assertSame($expected, Money::fromCents($amount)->format());
    }

    #[DataProvider('gstProvider')]
    #[Test]
    public function gstIsOneEleventhRoundedToTheNearestCent(int $amount, int $gst): void
    {
        static::assertSame($gst, Money::fromCents($amount)->gstComponent()->amount);
    }

    #[Test]
    public function holdsTheAmountInCents(): void
    {
        static::assertSame(185_000, (new Money(185_000))->amount);
    }

    #[Test]
    public function isZeroOnlyForNoCents(): void
    {
        static::assertSame(
            [true, false],
            [Money::zero()->isZero(), Money::fromCents(1)->isZero()],
        );
    }

    #[Test]
    public function multiplicationBeyondTheIntegerRangeIsRejected(): void
    {
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Money multiplication overflows the integer range of cents');

        Money::fromCents(intdiv(PHP_INT_MAX, num2: 2) + 1)->multiply(quantity: 2);
    }

    #[DataProvider('multiplicationProvider')]
    #[Test]
    public function multipliesByAQuantity(int $amount, int $quantity, int $product): void
    {
        static::assertSame($product, Money::fromCents($amount)->multiply($quantity)->amount);
    }

    #[Test]
    public function multiplyingByANegativeQuantityIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot be negative, got -1');

        Money::fromCents(1_000)->multiply(-1);
    }

    #[Test]
    public function negativeAmountsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Money cannot be negative, got -1 cents');

        Money::fromCents(-1);
    }

    #[Test]
    public function subtractionBelowZeroIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Money cannot be negative, got -1 cents');

        Money::fromCents(100)->subtract(Money::fromCents(101));
    }

    #[Test]
    public function subtractsAmountsDownToZero(): void
    {
        static::assertSame(
            [750, 0],
            [
                Money::fromCents(1_000)->subtract(Money::fromCents(250))->amount,
                Money::fromCents(250)->subtract(Money::fromCents(250))->amount,
            ],
        );
    }

    #[Test]
    public function zeroHasNoCents(): void
    {
        static::assertSame(0, Money::zero()->amount);
    }
}
