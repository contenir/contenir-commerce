<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Exception\OverflowException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Model\Entity\AbstractOrderItemEntity;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutLineItem;
use Contenir\Commerce\Payment\CheckoutRequest;
use Contenir\Commerce\Payment\CheckoutSession;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Psr\Clock\ClockInterface;
use Throwable;

use function array_map;
use function in_array;
use function sprintf;

/**
 * Orchestrates the order lifecycle shared by the public site (create,
 * checkout, webhook completion) and the CMS (pickup, refund, cancel). Every
 * status change goes through OrderStatus::transitionTo(), so a move the
 * lifecycle does not allow throws InvalidTransitionException.
 *
 * There are no customer holds: availability is checked when the order is
 * created, again when checkout begins, and again when payment completes. If
 * two buyers pay for the same work, the first completed payment wins and the
 * second is refunded in full.
 *
 * Completion is idempotent: webhook retries and thank-you page revisits for
 * an order that is already settled change nothing, and the refunds it issues
 * carry idempotency keys, so a retry after a failure part-way through never
 * refunds twice.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods The lifecycle steps share their availability and refund helpers.
 * @mago-expect lint:cyclomatic-complexity The lifecycle's branches, kept in one class for 2.0.
 * @mago-expect lint:kan-defect The lifecycle's branches, kept in one class for 2.0.
 */
final readonly class OrderManager
{
    private const string REF_PREFIX = 'LR';

    /**
     * @mago-expect lint:excessive-parameter-list The repositories, gateway and clock the lifecycle needs.
     */
    public function __construct(
        private EntityManager $em,
        private OrderRepository $orders,
        private OrderItemRepository $orderItems,
        private ArtworkRepository $artworks,
        private PaymentGatewayInterface $gateway,
        private ClockInterface $clock,
    ) {}

    /**
     * Sends the buyer to the payment provider for a pending order that has
     * not started checkout yet. Each checkout attempt needs its own order,
     * so that a payment through an older session can still be matched.
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
        if (OrderStatus::Pending !== $order->status) {
            throw InvalidTransitionException::checkoutNotPending($order->status);
        }

        if (null !== $order->stripeCheckoutSessionId) {
            throw InvalidTransitionException::checkoutAlreadyStarted($order->orderRef);
        }

        $items = $this->purchaseItemsFor($order);
        $this->assertAvailable($items);

        $session = $this->gateway->createCheckoutSession(new CheckoutRequest(
            array_map(
                static fn(PurchaseItem $item): CheckoutLineItem => new CheckoutLineItem(
                    $item->title,
                    $item->price,
                    description: $item->artistName,
                ),
                $items,
            ),
            $successUrl,
            $cancelUrl,
            $order->customerEmail,
            [
                'order_ref' => $order->orderRef,
                'order_id'  => (string) $order->getId(),
            ],
        ));

        $order->stripeCheckoutSessionId = $session->id;
        $order->updated                 = $this->clock->now();
        $this->em->save($order);

        return $session;
    }

    /**
     * @throws InvalidTransitionException When the order can no longer be cancelled.
     * @throws DbModelException
     */
    public function cancelOrder(AbstractOrderEntity $order): void
    {
        $now                = $this->clock->now();
        $order->status      = $order->status->transitionTo(OrderStatus::Cancelled);
        $order->cancelledAt = $now;
        $order->updated     = $now;
        $this->em->save($order);
    }

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
        $order = $this->orders->findOneByCheckoutSessionId($sessionId)
            ?? throw OrderNotFoundException::forCheckoutSession($sessionId);

        return match ($order->status) {
            OrderStatus::Pending   => $this->completePending($order, $sessionId),
            OrderStatus::Cancelled => $this->settleCancelled($order, $sessionId),
            default                => new CompletionResult($order, CompletionOutcome::AlreadyCompleted),
        };
    }

    /**
     * Creates a pending order with a snapshot of each item. The order and its
     * lines are written in one transaction.
     *
     * @param list<PurchaseItem> $items
     *
     * @throws ArtworkUnavailableException When a work has been sold since it was carted.
     * @throws InvalidArgumentException When there are no items or a work appears twice.
     * @throws OverflowException When the total exceeds the integer range of cents.
     * @throws DbModelException
     * @throws Throwable Database errors, after rolling back.
     */
    public function createPendingOrder(array $items, CustomerDetails $customer): AbstractOrderEntity
    {
        if ([] === $items) {
            throw new InvalidArgumentException('An order requires at least one item');
        }

        $this->assertDistinct($items);
        $this->assertAvailable($items);

        $total = Money::zero();
        foreach ($items as $item) {
            $total = $total->add($item->price);
        }

        return $this->em->transactional(
            /**
             * @throws OrderNotFoundException
             * @throws DbModelException
             */
            function () use ($items, $customer, $total): AbstractOrderEntity {
                $now                  = $this->clock->now();
                $order                = $this->orders->newEntity();
                $order->orderRef      = sprintf('%s-PENDING', self::REF_PREFIX);
                $order->customerName  = $customer->name;
                $order->customerEmail = $customer->email;
                $order->customerPhone = $customer->phone;
                $order->customerNotes = $customer->notes;
                $order->status        = OrderStatus::Pending;
                $order->total         = $total->amount;
                $order->gstAmount     = $total->gstComponent()->amount;
                $order->created       = $now;
                $order->updated       = $now;
                $this->em->save($order);

                $order->orderRef = sprintf('%s-%s-%04d', self::REF_PREFIX, $now->format('Y'), $order->getId());
                $this->em->save($order);

                foreach ($items as $item) {
                    $line             = $this->orderItems->newEntity();
                    $line->orderId    = $order->getId();
                    $line->artworkId  = $item->artworkId;
                    $line->title      = $item->title;
                    $line->artistName = $item->artistName;
                    $line->price      = $item->price->amount;
                    $line->created    = $now;
                    $this->em->save($line);
                }

                return $order;
            },
        );
    }

    /**
     * checkout.session.expired: release the pending order. No stock was
     * ever held, so this is bookkeeping only. Returns null when there is no
     * such order or it is no longer pending.
     *
     * @throws DbModelException
     */
    public function expireCheckout(string $sessionId): ?AbstractOrderEntity
    {
        $order = $this->orders->findOneByCheckoutSessionId($sessionId);
        if (null === $order || OrderStatus::Pending !== $order->status) {
            return null;
        }

        $this->cancelOrder($order);

        return $order;
    }

    /**
     * @throws InvalidTransitionException When the order is not paid.
     * @throws DbModelException
     */
    public function markAwaitingPickup(AbstractOrderEntity $order): void
    {
        $order->status  = $order->status->transitionTo(OrderStatus::AwaitingPickup);
        $order->updated = $this->clock->now();
        $this->em->save($order);
    }

    /**
     * @throws InvalidTransitionException When the order is not awaiting pickup.
     * @throws DbModelException
     */
    public function markCollected(AbstractOrderEntity $order): void
    {
        $now                = $this->clock->now();
        $order->status      = $order->status->transitionTo(OrderStatus::Collected);
        $order->collectedAt = $now;
        $order->updated     = $now;
        $this->em->save($order);
    }

    /**
     * The order's lines as purchase items, in the order they were added. A
     * line whose artwork has since been deleted has artwork id 0.
     *
     * @return list<PurchaseItem>
     *
     * @throws OrderNotFoundException When the order has not been saved.
     * @throws InvalidArgumentException When a stored price is negative.
     * @throws DbModelException
     */
    public function purchaseItemsFor(AbstractOrderEntity $order): array
    {
        return array_map(
            static fn(AbstractOrderItemEntity $line): PurchaseItem => new PurchaseItem(
                $line->artworkId ?? 0,
                $line->title,
                $line->getPrice(),
                $line->artistName,
            ),
            $this->orderItems->findByOrderId($order->getId()),
        );
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
        $paymentIntentId = $order->stripePaymentIntentId ?? '';
        if ('' === $paymentIntentId) {
            throw PaymentFailedException::nothingToRefund($order->orderRef);
        }

        $next = $order->status->transitionTo(OrderStatus::Refunded);
        $this->gateway->refund($paymentIntentId, $amount);

        $now               = $this->clock->now();
        $order->status     = $next;
        $order->refundedAt = $now;
        $order->updated    = $now;
        $this->em->save($order);
    }

    /**
     * @param list<PurchaseItem> $items
     *
     * @throws ArtworkUnavailableException
     * @throws DbModelException
     */
    private function assertAvailable(array $items): void
    {
        $lost = $this->checkAvailability($items)['lost'];
        if ([] !== $lost) {
            throw ArtworkUnavailableException::forTitles($lost);
        }
    }

    /**
     * Each original can be sold once, so a work may appear in an order only
     * once.
     *
     * @param list<PurchaseItem> $items
     *
     * @throws InvalidArgumentException
     */
    private function assertDistinct(array $items): void
    {
        $seen = [];
        foreach ($items as $item) {
            if (in_array($item->artworkId, $seen, strict: true)) {
                throw new InvalidArgumentException(sprintf('"%s" appears in the order more than once', $item->title));
            }

            $seen[] = $item->artworkId;
        }
    }

    /**
     * Reads each item's artwork as currently stored and splits the items
     * into the available artworks and the titles that are no longer
     * available (sold, withdrawn or deleted).
     *
     * @param list<PurchaseItem> $items
     *
     * @return array{available: list<AbstractArtworkEntity>, lost: list<string>}
     *
     * @throws DbModelException
     */
    private function checkAvailability(array $items): array
    {
        $available = [];
        $lost      = [];
        foreach ($items as $item) {
            $artwork = $this->artworks->findCurrent($item->artworkId);
            if (null !== $artwork && $artwork->isAvailable()) {
                $available[] = $artwork;
                continue;
            }

            $lost[] = $item->title;
        }

        return ['available' => $available, 'lost' => $lost];
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

        return $this->em->transactional(
            /**
             * @throws PaymentFailedException
             * @throws OrderNotFoundException
             * @throws InvalidArgumentException
             * @throws DbModelException
             */
            function () use ($order, $session): CompletionResult {
                $order->stripePaymentIntentId = $session->paymentIntentId;

                ['available' => $artworks, 'lost' => $lost] = $this->checkAvailability($this->purchaseItemsFor($order));
                if ([] !== $lost) {
                    return $this->refundRace($order, $lost);
                }

                $now            = $this->clock->now();
                $order->status  = $order->status->transitionTo(OrderStatus::Paid);
                $order->paidAt  = $now;
                $order->updated = $now;
                $this->em->save($order);

                foreach ($artworks as $artwork) {
                    $artwork->status  = ArtworkStatus::Sold;
                    $artwork->updated = $now;
                    $this->em->save($artwork);
                }

                return new CompletionResult($order, CompletionOutcome::Completed);
            },
        );
    }

    /**
     * Refunds the whole payment with an idempotency key derived from the
     * payment, so repeating the call refunds once.
     *
     * @throws PaymentFailedException
     */
    private function refundInFull(AbstractOrderEntity $order, string $reason): void
    {
        $paymentIntentId = $order->stripePaymentIntentId ?? '';
        if ('' === $paymentIntentId) {
            throw PaymentFailedException::nothingToRefund($order->orderRef);
        }

        $this->gateway->refund(
            $paymentIntentId,
            idempotencyKey: sprintf('contenir-commerce-%s-refund-%s', $reason, $paymentIntentId),
        );
    }

    /**
     * First completed payment wins: this payment arrived second for at least
     * one work, so refund it in full and close the order as paid-then-refunded.
     *
     * @param list<string> $lost
     *
     * @throws PaymentFailedException
     * @throws DbModelException
     */
    private function refundRace(AbstractOrderEntity $order, array $lost): CompletionResult
    {
        $this->refundInFull($order, 'race');

        $now               = $this->clock->now();
        $order->status     = $order->status->transitionTo(OrderStatus::Paid)->transitionTo(OrderStatus::Refunded);
        $order->paidAt     = $now;
        $order->refundedAt = $now;
        $order->updated    = $now;
        $this->em->save($order);

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
        $this->refundInFull($order, 'cancelled');

        $now               = $this->clock->now();
        $order->paidAt     = $now;
        $order->refundedAt = $now;
        $order->updated    = $now;
        $this->em->save($order);

        return new CompletionResult($order, CompletionOutcome::RefundedCancelled);
    }
}
