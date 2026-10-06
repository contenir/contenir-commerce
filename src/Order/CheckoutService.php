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
use Contenir\Commerce\Payment\CheckoutLineItem;
use Contenir\Commerce\Payment\CheckoutRequest;
use Contenir\Commerce\Payment\CheckoutSession;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Throwable;

use function array_map;
use function sprintf;

/**
 * The public site's side of an order: create the pending order from the
 * cart and send the buyer to the payment provider.
 *
 * @api
 */
final readonly class CheckoutService
{
    private const string REF_PREFIX = 'LR';

    public function __construct(
        private OrderStore $store,
        private ArtworkReservation $reservation,
        private PurchaseItemCheck $itemCheck,
        private PaymentGatewayInterface $gateway,
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

        $items = $this->store->purchaseItemsFor($order);
        $this->reservation->assertAvailable($items);

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
        $order->updated                 = $this->store->now();
        $this->store->save($order);

        return $session;
    }

    /**
     * Creates a pending order with a snapshot of each item. Each item's
     * price, and its title when the artwork maps one, must match the
     * artwork as stored: the order never trusts the caller's prices. The
     * order and its lines are written in one transaction.
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
        if ([] === $items) {
            throw new InvalidArgumentException('An order requires at least one item');
        }

        $this->itemCheck->assertDistinct($items);
        $this->itemCheck->assertListed($this->reservation->available($items));

        $total = Money::zero();
        foreach ($items as $item) {
            $total = $total->add($item->price);
        }

        return $this->store->transactional(
            /**
             * @throws OrderNotFoundException
             * @throws DbModelException
             */
            function () use ($items, $customer, $total): AbstractOrderEntity {
                $now                  = $this->store->now();
                $order                = $this->store->newOrder();
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
                $this->store->save($order);

                $order->orderRef = sprintf('%s-%s-%04d', self::REF_PREFIX, $now->format('Y'), $order->getId());
                $this->store->save($order);

                foreach ($items as $item) {
                    $line             = $this->store->newLine();
                    $line->orderId    = $order->getId();
                    $line->artworkId  = $item->artworkId;
                    $line->title      = $item->title;
                    $line->artistName = $item->artistName;
                    $line->price      = $item->price->amount;
                    $line->created    = $now;
                    $this->store->save($line);
                }

                return $order;
            },
        );
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
        return $this->store->purchaseItemsFor($order);
    }
}
