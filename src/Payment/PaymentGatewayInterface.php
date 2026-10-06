<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;

/**
 * The payment provider behind checkout and refunds. StripeGateway is the
 * shipped implementation; implement this interface for another provider or
 * a test double.
 *
 * @api
 */
interface PaymentGatewayInterface
{
    /**
     * @throws PaymentFailedException
     */
    public function createCheckoutSession(CheckoutRequest $request): CheckoutSession;

    /**
     * A null amount refunds the full payment. Requests repeated with the same
     * idempotency key refund at most once.
     *
     * @throws PaymentFailedException
     */
    public function refund(
        string $paymentIntentId,
        ?Money $amount = null,
        ?string $idempotencyKey = null,
    ): RefundResult;

    /**
     * @throws PaymentFailedException
     */
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession;
}
