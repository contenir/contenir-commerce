<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Override;

/**
 * Stands in when no Stripe secret key is configured, so the container and
 * everything depending on the gateway stay constructible. Any attempt to
 * move money fails with the same catchable exception a Stripe outage
 * would produce.
 *
 * @api
 */
final class UnconfiguredGateway implements PaymentGatewayInterface
{
    /**
     * @throws PaymentFailedException Always.
     */
    #[Override]
    public function createCheckoutSession(CheckoutRequest $request): CheckoutSession
    {
        throw PaymentFailedException::notConfigured();
    }

    /**
     * @throws PaymentFailedException Always.
     */
    #[Override]
    public function expireCheckoutSession(string $sessionId): CheckoutSession
    {
        throw PaymentFailedException::notConfigured();
    }

    /**
     * @throws PaymentFailedException Always.
     */
    #[Override]
    public function refund(
        string $paymentIntentId,
        ?Money $amount = null,
        ?string $idempotencyKey = null,
    ): RefundResult {
        throw PaymentFailedException::notConfigured();
    }

    /**
     * @throws PaymentFailedException Always.
     */
    #[Override]
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession
    {
        throw PaymentFailedException::notConfigured();
    }
}
