<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Throwable;

/**
 * Settles orders from their checkout sessions, for the payment provider's
 * webhook and the thank-you page.
 *
 * There are no customer holds: if two buyers pay for the same work, the
 * first completed payment wins and the second is refunded in full.
 * Completion is idempotent: webhook retries and thank-you page revisits for
 * an order that is already settled change nothing, and the refunds it
 * issues carry idempotency keys, so a retry after a failure part-way
 * through never refunds twice.
 *
 * @api
 */
final readonly class CompletionService
{
    public function __construct(
        private OrderStore $store,
        private ArtworkReservation $reservation,
        private Refunder $refunder,
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Settles an order from its checkout session. Safe to call repeatedly
     * (webhook retries, thank-you page revisits).
     *
     * @throws OrderNotFoundException When no order has this checkout session.
     * @throws PaymentFailedException
     * @throws DbModelException
     * @throws Throwable Database and payment errors, after rolling back.
     */
    public function completeFromCheckoutSession(string $sessionId): CompletionResult
    {
        $order = $this->store->findByCheckoutSession($sessionId)
            ?? throw OrderNotFoundException::forCheckoutSession($sessionId);

        return OrderStatus::Pending === $order->status
            ? $this->completePending($order, $sessionId)
            : $this->settleSettled($order, $sessionId);
    }

    /**
     * checkout.session.expired: release the pending order. No stock was
     * ever held and the session is already closed, so this is bookkeeping
     * only. Returns null when there is no such order or it is no longer
     * pending.
     *
     * @throws InvalidTransitionException Never in practice: a pending order may always be cancelled.
     * @throws DbModelException
     */
    public function expireCheckout(string $sessionId): ?AbstractOrderEntity
    {
        $order = $this->store->findByCheckoutSession($sessionId);
        if (null === $order || OrderStatus::Pending !== $order->status) {
            return null;
        }

        $now                = $this->store->now();
        $order->status      = $order->status->transitionTo(OrderStatus::Cancelled);
        $order->cancelledAt = $now;
        $order->updated     = $now;
        $this->store->save($order);

        return $order;
    }

    /**
     * @throws PaymentFailedException
     * @throws Throwable Database and payment errors, after rolling back.
     */
    private function completePending(AbstractOrderEntity $order, string $sessionId): CompletionResult
    {
        $session = $this->gateway->retrieveCheckoutSession($sessionId);
        if (! $session->isPaid()) {
            return new CompletionResult($order, CompletionOutcome::NotPaid);
        }

        return $this->store->transactional(
            /**
             * @throws PaymentFailedException
             * @throws OrderNotFoundException
             * @throws InvalidArgumentException
             * @throws DbModelException
             */
            function () use ($order, $session): CompletionResult {
                $order->stripePaymentIntentId = $session->paymentIntentId;

                ['available' => $artworks, 'lost' => $lost] = $this->reservation->checkAvailability(
                    $this->store->purchaseItemsFor($order),
                );
                if ([] !== $lost) {
                    return $this->refundRace($order, $lost);
                }

                $now            = $this->store->now();
                $order->status  = $order->status->transitionTo(OrderStatus::Paid);
                $order->paidAt  = $now;
                $order->updated = $now;
                $this->store->save($order);

                foreach ($artworks as $artwork) {
                    $artwork->status  = ArtworkStatus::Sold;
                    $artwork->updated = $now;
                    $this->store->save($artwork);
                }

                return new CompletionResult($order, CompletionOutcome::Completed);
            },
        );
    }

    /**
     * First completed payment wins: this payment arrived second for at least
     * one work, so refund it in full and close the order as paid-then-refunded.
     *
     * @param list<string> $lost
     *
     * @throws PaymentFailedException
     * @throws InvalidTransitionException
     * @throws DbModelException
     */
    private function refundRace(AbstractOrderEntity $order, array $lost): CompletionResult
    {
        $this->refunder->refundInFull($order, 'race');

        $now               = $this->store->now();
        $order->status     = $order->status->transitionTo(OrderStatus::Paid)->transitionTo(OrderStatus::Refunded);
        $order->paidAt     = $now;
        $order->refundedAt = $now;
        $order->updated    = $now;
        $this->store->save($order);

        return new CompletionResult($order, CompletionOutcome::RefundedRace, $lost);
    }

    /**
     * A cancelled order whose session was paid after all (the order was
     * cancelled while the buyer was paying) is refunded in full and stays
     * cancelled.
     *
     * @throws PaymentFailedException
     * @throws DbModelException
     */
    private function settleCancelled(AbstractOrderEntity $order, string $sessionId): CompletionResult
    {
        if (null !== $order->refundedAt) {
            return new CompletionResult($order, CompletionOutcome::RefundedCancelled);
        }

        $session = $this->gateway->retrieveCheckoutSession($sessionId);
        if (! $session->isPaid()) {
            return new CompletionResult($order, CompletionOutcome::NotPaid);
        }

        $order->stripePaymentIntentId = $session->paymentIntentId;
        $this->refunder->refundInFull($order, 'cancelled');

        $now               = $this->store->now();
        $order->paidAt     = $now;
        $order->refundedAt = $now;
        $order->updated    = $now;
        $this->store->save($order);

        return new CompletionResult($order, CompletionOutcome::RefundedCancelled);
    }

    /**
     * An order that is no longer pending: a cancelled one may still need
     * its late payment refunded; any other is already settled.
     *
     * @throws PaymentFailedException
     * @throws DbModelException
     */
    private function settleSettled(AbstractOrderEntity $order, string $sessionId): CompletionResult
    {
        return OrderStatus::Cancelled === $order->status
            ? $this->settleCancelled($order, $sessionId)
            : new CompletionResult($order, CompletionOutcome::AlreadyCompleted);
    }
}
