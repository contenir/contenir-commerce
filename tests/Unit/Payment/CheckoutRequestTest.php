<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Payment;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutLineItem;
use Contenir\Commerce\Payment\CheckoutRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[Group('unit')]
final class CheckoutRequestTest extends TestCase
{
    /**
     * @return array<string, array{int}>
     */
    public static function acceptedExpiryProvider(): array
    {
        return [
            'the minimum' => [30],
            'the maximum' => [1_440],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function rejectedExpiryProvider(): array
    {
        return [
            'below the minimum' => [29],
            'above the maximum' => [1_441],
        ];
    }

    #[DataProvider('acceptedExpiryProvider')]
    #[Test]
    public function acceptsAnExpiryWithinStripesRange(int $minutes): void
    {
        static::assertSame($minutes, $this->request($minutes)->expiresAfterMinutes);
    }

    #[Test]
    public function defaultsToNoEmailNoMetadataAndThirtyMinutes(): void
    {
        $request = new CheckoutRequest(
            [new CheckoutLineItem('Coastal Dawn', Money::fromCents(185_000))],
            'https://example.test/thanks',
            'https://example.test/cart',
        );

        static::assertSame([null, [], 30], [
            $request->customerEmail,
            $request->metadata,
            $request->expiresAfterMinutes,
        ]);
    }

    #[Test]
    public function rejectsAnEmptyCheckout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Checkout requires at least one line item');

        new CheckoutRequest([], 'https://example.test/thanks', 'https://example.test/cart');
    }

    #[DataProvider('rejectedExpiryProvider')]
    #[Test]
    public function rejectsAnExpiryOutsideStripesRange(int $minutes): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Stripe checkout sessions must expire 30 to 1440 minutes after creation, got %d',
            $minutes,
        ));

        $this->request($minutes);
    }

    private function request(int $minutes): CheckoutRequest
    {
        return new CheckoutRequest(
            [new CheckoutLineItem('Coastal Dawn', Money::fromCents(185_000))],
            'https://example.test/thanks',
            'https://example.test/cart',
            expiresAfterMinutes: $minutes,
        );
    }
}
