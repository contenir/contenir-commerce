<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Trait;

use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\ArtworkReservation;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\FulfilmentService;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\PurchaseItem;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Tests\TestAsset\Clock\MovableClock;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\TestAsset\Payment\FakePaymentGateway;
use Contenir\Db\Model\Type\TypeRegistry;

/**
 * The three order services over a fresh in-memory database, a scriptable
 * gateway and a movable clock. Artworks 1 and 2 are available, priced
 * 1,850.00 and 980.00. Call setUpOrderServices() from setUp().
 */
trait OrderServicesTrait
{
    use SqliteDatabaseTrait;

    private ArtworkRepository $artworks;

    private CheckoutService $checkout;

    private MovableClock $clock;

    private CompletionService $completion;

    private FulfilmentService $fulfilment;

    private FakePaymentGateway $gateway;

    /**
     * @return array<string, mixed>
     */
    private function artworkRow(int $artworkId): array
    {
        $row = $this->row('artwork', 'artwork_id', $artworkId);

        return ['status' => $row['status'], 'updated' => $row['updated']];
    }

    private function artworkStatus(int $artworkId): mixed
    {
        return $this->column('artwork', 'status', 'artwork_id', $artworkId);
    }

    private function buyer(string $name): CustomerDetails
    {
        return new CustomerDetails($name, 'buyer@example.test');
    }

    private function checkedOutOrder(): AbstractOrderEntity
    {
        $order = $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');

        return $order;
    }

    private function completionWith(FakePaymentGateway $gateway): CompletionService
    {
        return new CompletionService(
            $this->store(),
            new ArtworkReservation($this->artworks),
            new Refunder($gateway),
            $gateway,
        );
    }

    private function fulfilmentWith(FakePaymentGateway $gateway): FulfilmentService
    {
        return new FulfilmentService($this->store(), new Refunder($gateway), $gateway);
    }

    /**
     * @return list<PurchaseItem>
     */
    private function items(): array
    {
        return [
            new PurchaseItem(1, 'Headland, Dawn', Money::fromCents(185_000), 'June Hollis'),
            new PurchaseItem(2, 'Swan Bay Nocturne', Money::fromCents(98_000), 'Marcus Tran'),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function lineRow(int $orderItemId): array
    {
        $row = $this->row('gallery_order_item', 'order_item_id', $orderItemId);

        return [
            $row['order_id'],
            $row['artwork_id'],
            $row['title'],
            $row['artist_name'],
            $row['price'],
            $row['created'],
        ];
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function orderRow(array $columns, int $orderId = 1): array
    {
        $row      = $this->row('gallery_order', 'order_id', $orderId);
        $selected = [];
        foreach ($columns as $column) {
            $selected[$column] = $row[$column];
        }

        return $selected;
    }

    private function paidOrder(): AbstractOrderEntity
    {
        $this->checkedOutOrder();
        $this->gateway->completeSession('cs_fake_1', 'pi_fake_1');

        return $this->completion->completeFromCheckoutSession('cs_fake_1')->order;
    }

    /**
     * A second buyer carted work 1 before the first paid for it, and pays
     * through session cs_fake_2.
     */
    private function secondBuyerPays(?string $paymentIntentId = 'pi_second'): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET status = 'available' WHERE artwork_id = 1");
        $second = $this->checkout->createPendingOrder([CommerceFactory::item(1)], $this->buyer('Second Buyer'));
        $this->checkout->beginCheckout($second, 'https://example.test/thanks', 'https://example.test/cart');
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 1");
        $this->gateway->completeSession('cs_fake_2', $paymentIntentId);
    }

    private function setUpOrderServices(): void
    {
        $this->setUpDatabase();
        $this->clock    = new MovableClock('2026-08-20 10:00:00');
        $this->gateway  = new FakePaymentGateway();
        $this->artworks = new ArtworkRepository($this->em, $this->adapter, TypeRegistry::withDefaults());
        $this->checkout = new CheckoutService(
            $this->store(),
            new ArtworkReservation($this->artworks),
            $this->gateway,
        );
        $this->completion = $this->completionWith($this->gateway);
        $this->fulfilment = $this->fulfilmentWith($this->gateway);

        foreach ([185_000, 98_000] as $price) {
            $this->em->save(CommerceFactory::artwork($price));
        }
    }

    private function store(): OrderStore
    {
        return new OrderStore(
            $this->em,
            new OrderRepository($this->em),
            new OrderItemRepository($this->em),
            $this->clock,
        );
    }
}
