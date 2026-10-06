<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;

/**
 * The back-office steps staff take in the CMS: pickup, collection, refunds
 * and cancellation.
 *
 * @api
 */
final readonly class FulfilmentService
{
    public function __construct(
        private OrderStore $store,
        private Refunder $refunder,
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Cancels the order. A pending order whose checkout has begun has its
     * checkout session expired first, so the buyer can no longer pay
     * through it.
     *
     * If the session is already complete (the buyer paid, or a delayed
     * payment is on its way), it cannot be expired; the order is cancelled
     * all the same, and completing the session later refunds the payment in
     * full (CompletionOutcome::RefundedCancelled). If the provider cannot
     * be reached, the order is left as it was and the exception propagates,
     * so that cancelling can be retried.
     *
     * @throws InvalidTransitionException When the order can no longer be cancelled.
     * @throws PaymentFailedException When the checkout session could not be expired.
     * @throws DbModelException
     */
    public function cancelOrder(AbstractOrderEntity $order): void
    {
        $next      = $order->status->transitionTo(OrderStatus::Cancelled);
        $sessionId = $order->stripeCheckoutSessionId;
        if (OrderStatus::Pending === $order->status && null !== $sessionId) {
            $this->gateway->expireCheckoutSession($sessionId);
        }

        $now                = $this->store->now();
        $order->status      = $next;
        $order->cancelledAt = $now;
        $order->updated     = $now;
        $this->store->save($order);
    }

    /**
     * @throws InvalidTransitionException When the order is not paid.
     * @throws DbModelException
     */
    public function markAwaitingPickup(AbstractOrderEntity $order): void
    {
        $order->status  = $order->status->transitionTo(OrderStatus::AwaitingPickup);
        $order->updated = $this->store->now();
        $this->store->save($order);
    }

    /**
     * @throws InvalidTransitionException When the order is not awaiting pickup.
     * @throws DbModelException
     */
    public function markCollected(AbstractOrderEntity $order): void
    {
        $now                = $this->store->now();
        $order->status      = $order->status->transitionTo(OrderStatus::Collected);
        $order->collectedAt = $now;
        $order->updated     = $now;
        $this->store->save($order);
    }

    /**
     * Refunds through the payment provider and closes the order; a null
     * amount refunds in full. Artwork availability is left untouched:
     * returning a work to sale is a curatorial decision made in the CMS, not
     * a side effect.
     *
     * @throws PaymentFailedException When the order has no payment or the provider refuses the refund.
     * @throws InvalidTransitionException When the order cannot be refunded.
     * @throws DbModelException
     */
    public function refundOrder(AbstractOrderEntity $order, ?Money $amount = null): void
    {
        $paymentIntentId = $this->refunder->paymentIntentOf($order);
        $next            = $order->status->transitionTo(OrderStatus::Refunded);
        $this->gateway->refund($paymentIntentId, $amount);

        $now               = $this->store->now();
        $order->status     = $next;
        $order->refundedAt = $now;
        $order->updated    = $now;
        $this->store->save($order);
    }
}
