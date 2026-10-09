<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\CompletionResult;
use Contenir\Commerce\Order\PurchaseItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[Group('unit')]
final class ValueObjectsTest extends TestCase
{
    /**
     * @return array<string, array{int}>
     */
    public static function invalidQuantityProvider(): array
    {
        return [
            'none'     => [0],
            'negative' => [-1],
        ];
    }

    #[Test]
    public function aCompletionResultHasNoLostTitlesByDefault(): void
    {
        static::assertSame(
            [],
            (new CompletionResult(new OrderEntity(), CompletionOutcome::Completed))->unavailableTitles,
        );
    }

    #[Test]
    public function aPurchaseItemIsOneUnitOfAnOnlyVariantByDefault(): void
    {
        $item = new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500));

        static::assertSame([1, null, null], [$item->quantity, $item->variantLabel, $item->description]);
    }

    #[DataProvider('invalidQuantityProvider')]
    #[Test]
    public function aPurchaseItemNeedsAtLeastOneUnit(int $quantity): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Purchase item quantity must be at least 1, got %d', $quantity));

        new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500), $quantity);
    }

    #[Test]
    public function aPurchaseItemTotalsItsQuantity(): void
    {
        static::assertSame(10_500, (new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500), 3))->getTotal()->amount);
    }
}
