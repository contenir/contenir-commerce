<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Trait;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\FulfilmentService;
use Contenir\Commerce\Order\ItemInventory;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\PurchaseItem;
use Contenir\Commerce\Order\PurchaseItemCheck;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Tests\TestAsset\Clock\MovableClock;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\TestAsset\Payment\FakePaymentGateway;
use Contenir\Db\Model\Type\TypeRegistry;

/**
 * The three order services over a fresh in-memory database, a scriptable
 * gateway and a movable clock. Items 1 and 2 are listed, each with one
 * variant (1 and 2) of a single unit, priced 1,850.00 and 980.00. Call
 * setUpOrderServices() from setUp().
 */
trait OrderServicesTrait
{
    use SqliteDatabaseTrait;

    private ItemVariantRepository $variants;

    private CheckoutService $checkout;

    private MovableClock $clock;

    private CompletionService $completion;

    private FulfilmentService $fulfilment;

    private FakePaymentGateway $gateway;

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

    private function checkoutWith(
        ItemRepository $items,
        CommerceSettings $settings = new CommerceSettings(),
    ): CheckoutService {
        return new CheckoutService(
            $this->store(),
            new ItemInventory($items, $this->variants),
            new PurchaseItemCheck(),
            $this->gateway,
            $settings,
        );
    }

    private function completionWith(FakePaymentGateway $gateway): CompletionService
    {
        return new CompletionService(
            $this->store(),
            new ItemInventory(new ItemRepository($this->em), $this->variants),
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
            new PurchaseItem(1, 'Headland, Dawn', Money::fromCents(185_000), description: 'June Hollis'),
            new PurchaseItem(2, 'Swan Bay Nocturne', Money::fromCents(98_000), description: 'Marcus Tran'),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function lineRow(int $orderItemId): array
    {
        $row = $this->row('commerce_order_item', 'order_item_id', $orderItemId);

        return [
            $row['order_id'],
            $row['item_id'],
            $row['item_variant_id'],
            $row['title'],
            $row['variant_label'],
            $row['description'],
            $row['unit_price'],
            $row['quantity'],
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
        $row      = $this->row('commerce_order', 'order_id', $orderId);
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
     * A second buyer carted variant 1 before the first paid for its only
     * unit, and pays through session cs_fake_2.
     */
    private function secondBuyerPays(?string $paymentIntentId = 'pi_second'): void
    {
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 1 WHERE item_variant_id = 1');
        $second = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1)],
            $this->buyer('Second Buyer'),
        );
        $this->checkout->beginCheckout($second, 'https://example.test/thanks', 'https://example.test/cart');
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 0 WHERE item_variant_id = 1');
        $this->gateway->completeSession('cs_fake_2', $paymentIntentId);
    }

    private function setUpOrderServices(): void
    {
        $this->setUpDatabase();
        $this->clock      = new MovableClock('2026-08-20 10:00:00');
        $this->gateway    = new FakePaymentGateway();
        $this->variants   = new ItemVariantRepository($this->em, $this->adapter, TypeRegistry::withDefaults());
        $this->checkout   = $this->checkoutWith(new ItemRepository($this->em));
        $this->completion = $this->completionWith($this->gateway);
        $this->fulfilment = $this->fulfilmentWith($this->gateway);

        foreach (['Headland, Dawn' => 185_000, 'Swan Bay Nocturne' => 98_000] as $title => $price) {
            $item = CommerceFactory::item($title);
            $this->em->save($item);
            $this->em->save(CommerceFactory::variant((int) $item->itemId, $price));
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

    /**
     * @return array<string, mixed>
     */
    private function variantRow(int $itemVariantId): array
    {
        $row = $this->row('item_variant', 'item_variant_id', $itemVariantId);

        return ['stock' => $row['stock'], 'updated' => $row['updated']];
    }

    private function variantStock(int $itemVariantId): mixed
    {
        return $this->column('item_variant', 'stock', 'item_variant_id', $itemVariantId);
    }
}
