<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Payment;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutLineItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CheckoutLineItemTest extends TestCase
{
    #[Test]
    public function defaultsToASingleItemWithNoDescription(): void
    {
        $item = new CheckoutLineItem('Coastal Dawn', Money::fromCents(185_000));

        static::assertSame([1, null], [$item->quantity, $item->description]);
    }

    #[Test]
    public function rejectsAQuantityBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line item quantity must be at least 1, got 0');

        new CheckoutLineItem('Tote bag', Money::fromCents(3_500), 0);
    }
}
