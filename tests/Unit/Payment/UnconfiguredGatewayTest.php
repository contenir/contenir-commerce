<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Payment;

use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutLineItem;
use Contenir\Commerce\Payment\CheckoutRequest;
use Contenir\Commerce\Payment\UnconfiguredGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UnconfiguredGatewayTest extends TestCase
{
    /**
     * @return array<string, array{callable(UnconfiguredGateway): mixed}>
     */
    public static function operationProvider(): array
    {
        return [
            'create checkout session' => [
                static fn(UnconfiguredGateway $gateway): mixed => $gateway->createCheckoutSession(new CheckoutRequest(
                    [new CheckoutLineItem('Tote bag', Money::fromCents(3_500))],
                    'https://example.test/thanks',
                    'https://example.test/cart',
                )),
            ],
            'retrieve session'        => [
                static fn(UnconfiguredGateway $gateway): mixed => $gateway->retrieveCheckoutSession('cs_x'),
            ],
            'refund'                  => [
                static fn(UnconfiguredGateway $gateway): mixed => $gateway->refund('pi_x'),
            ],
        ];
    }

    /**
     * @param callable(UnconfiguredGateway): mixed $operation
     */
    #[DataProvider('operationProvider')]
    #[Test]
    public function everyPaymentOperationFailsCatchably(callable $operation): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Stripe is not configured: set stripe.secret_key in local configuration');

        $operation(new UnconfiguredGateway());
    }
}
