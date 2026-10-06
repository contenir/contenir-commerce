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
     * Expires an open checkout session so that it can no longer be paid,
     * and returns the session as it then stands (status "expired").
     *
     * A session that is already complete or expired cannot be expired. It
     * is returned as it is, without an error, and the caller decides from
     * its status and payment status what that means: a complete session
     * may already have been paid.
     *
     * @throws PaymentFailedException When the provider cannot be reached or refuses for any other reason.
     */
    public function expireCheckoutSession(string $sessionId): CheckoutSession;

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
