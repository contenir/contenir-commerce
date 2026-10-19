<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;

use function sprintf;

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
     * Refunds through the payment provider and adds the amount to the
     * order's refunded amount; a null amount refunds whatever is left. The
     * order keeps its status until its refunds add up to the total, and
     * then moves to refunded. Stock is left untouched: putting refunded
     * units back on sale is a decision made in the CMS, not
     * a side effect.
     *
     * The idempotency key is derived from the payment, the amount refunded
     * before and this refund, so a retry after the order failed to save
     * refunds once.
     *
     * @throws PaymentFailedException When the order has no payment or the provider refuses the refund.
     * @throws InvalidTransitionException When the order cannot be refunded.
     * @throws InvalidArgumentException When the amount is more than is left to refund, or the stored amounts are invalid.
     * @throws DbModelException
     */
    public function refundOrder(AbstractOrderEntity $order, ?Money $amount = null): void
    {
        $paymentIntentId = $this->refunder->paymentIntentOf($order);
        $next            = $order->status->transitionTo(OrderStatus::Refunded);
        $total           = $order->getTotal();
        $refunded        = $order->getRefundedAmount();
        $left            = $total->subtract($refunded);
        $refund          = $amount ?? $left;
        if ($refund->amount > $left->amount) {
            throw new InvalidArgumentException(sprintf(
                'Cannot refund %s on order "%s": only %s of its %s total is left to refund',
                $refund->format(),
                $order->orderRef,
                $left->format(),
                $total->format(),
            ));
        }

        $this->gateway->refund(
            $paymentIntentId,
            $amount,
            sprintf('contenir-commerce-staff-refund-%s-%d-%d', $paymentIntentId, $refunded->amount, $refund->amount),
        );

        $now                   = $this->store->now();
        $order->refundedAmount = $refunded->amount + $refund->amount;
        $order->updated        = $now;
        if ($refund->equals($left)) {
            $order->status     = $next;
            $order->refundedAt = $now;
        }

        $this->store->save($order);
    }
}
