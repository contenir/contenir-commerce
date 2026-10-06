<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Order;

use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\CompletionResult;
use Contenir\Commerce\Order\PurchaseItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ValueObjectsTest extends TestCase
{
    #[Test]
    public function aCompletionResultHasNoLostTitlesByDefault(): void
    {
        static::assertSame(
            [],
            (new CompletionResult(new OrderEntity(), CompletionOutcome::Completed))->unavailableTitles,
        );
    }

    #[Test]
    public function aPurchaseItemNeedNotNameAnArtist(): void
    {
        static::assertNull((new PurchaseItem(1, 'Tote bag', Money::fromCents(3_500)))->artistName);
    }
}
