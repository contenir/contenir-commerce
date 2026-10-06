<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Exception\OverflowException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Exception\PurchaseItemMismatchException;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutSession;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Throwable;

/**
 * One entry point for the whole order lifecycle, shared by the public site
 * (create, checkout, webhook completion) and the CMS (pickup, refund,
 * cancel). It delegates each step to CheckoutService, CompletionService or
 * FulfilmentService; inject one of those directly when a class needs only
 * its part of the lifecycle.
 *
 * Every status change goes through OrderStatus::transitionTo(), so a move
 * the lifecycle does not allow throws InvalidTransitionException.
 *
 * @api
 */
final readonly class OrderManager
{
    public function __construct(
        private CheckoutService $checkout,
        private CompletionService $completion,
        private FulfilmentService $fulfilment,
    ) {}

    /**
     * @see CheckoutService::beginCheckout()
     *
     * @throws ArtworkUnavailableException When a work has been sold since the order was created.
     * @throws InvalidTransitionException When the order is not pending or checkout has already begun.
     * @throws OrderNotFoundException When the order has not been saved.
     * @throws PaymentFailedException
     * @throws InvalidArgumentException When a stored price is negative.
     * @throws DbModelException
     */
    public function beginCheckout(AbstractOrderEntity $order, string $successUrl, string $cancelUrl): CheckoutSession
    {
        return $this->checkout->beginCheckout($order, $successUrl, $cancelUrl);
    }

    /**
     * @see FulfilmentService::cancelOrder()
     *
     * @throws InvalidTransitionException When the order can no longer be cancelled.
     * @throws PaymentFailedException When the checkout session could not be expired.
     * @throws DbModelException
     */
    public function cancelOrder(AbstractOrderEntity $order): void
    {
        $this->fulfilment->cancelOrder($order);
    }

    /**
     * @see CompletionService::completeFromCheckoutSession()
     *
     * @throws OrderNotFoundException When no order has this checkout session.
     * @throws PaymentFailedException
     * @throws DbModelException
     * @throws Throwable Database and payment errors, after rolling back.
     */
    public function completeFromCheckoutSession(string $sessionId): CompletionResult
    {
        return $this->completion->completeFromCheckoutSession($sessionId);
    }

    /**
     * @see CheckoutService::createPendingOrder()
     *
     * @param list<PurchaseItem> $items
     *
     * @throws ArtworkUnavailableException When a work has been sold since it was carted.
     * @throws PurchaseItemMismatchException When an item's price or title differs from its artwork's.
     * @throws InvalidArgumentException When there are no items or a work appears twice.
     * @throws OverflowException When the total exceeds the integer range of cents.
     * @throws DbModelException
     * @throws Throwable Database errors, after rolling back.
     */
    public function createPendingOrder(array $items, CustomerDetails $customer): AbstractOrderEntity
    {
        return $this->checkout->createPendingOrder($items, $customer);
    }

    /**
     * @see CompletionService::expireCheckout()
     *
     * @throws InvalidTransitionException Never in practice: a pending order may always be cancelled.
     * @throws DbModelException
     */
    public function expireCheckout(string $sessionId): ?AbstractOrderEntity
    {
        return $this->completion->expireCheckout($sessionId);
    }

    /**
     * @see FulfilmentService::markAwaitingPickup()
     *
     * @throws InvalidTransitionException When the order is not paid.
     * @throws DbModelException
     */
    public function markAwaitingPickup(AbstractOrderEntity $order): void
    {
        $this->fulfilment->markAwaitingPickup($order);
    }

    /**
     * @see FulfilmentService::markCollected()
     *
     * @throws InvalidTransitionException When the order is not awaiting pickup.
     * @throws DbModelException
     */
    public function markCollected(AbstractOrderEntity $order): void
    {
        $this->fulfilment->markCollected($order);
    }

    /**
     * @see CheckoutService::purchaseItemsFor()
     *
     * @return list<PurchaseItem>
     *
     * @throws OrderNotFoundException When the order has not been saved.
     * @throws InvalidArgumentException When a stored price is negative.
     * @throws DbModelException
     */
    public function purchaseItemsFor(AbstractOrderEntity $order): array
    {
        return $this->checkout->purchaseItemsFor($order);
    }

    /**
     * @see FulfilmentService::refundOrder()
     *
     * @throws PaymentFailedException When the order has no payment or the provider refuses the refund.
     * @throws InvalidTransitionException When the order cannot be refunded.
     * @throws DbModelException
     */
    public function refundOrder(AbstractOrderEntity $order, ?Money $amount = null): void
    {
        $this->fulfilment->refundOrder($order, $amount);
    }
}
