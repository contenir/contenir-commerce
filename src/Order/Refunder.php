<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Payment\PaymentGatewayInterface;

use function sprintf;

/**
 * Finds the payment behind an order and refunds it in full with an
 * idempotency key, for the refunds completion issues on its own.
 *
 * @internal
 */
final readonly class Refunder
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @throws PaymentFailedException When the order has no payment.
     */
    public function paymentIntentOf(AbstractOrderEntity $order): string
    {
        $paymentIntentId = $order->stripePaymentIntentId ?? '';

        return '' === $paymentIntentId
            ? throw PaymentFailedException::nothingToRefund($order->orderRef)
            : $paymentIntentId;
    }

    /**
     * Refunds the whole payment with an idempotency key derived from the
     * reason and the payment, so repeating the call refunds once.
     *
     * @throws PaymentFailedException When the order has no payment or the provider refuses the refund.
     */
    public function refundInFull(AbstractOrderEntity $order, string $reason): void
    {
        $paymentIntentId = $this->paymentIntentOf($order);

        $this->gateway->refund(
            $paymentIntentId,
            idempotencyKey: sprintf('contenir-commerce-%s-refund-%s', $reason, $paymentIntentId),
        );
    }
}
