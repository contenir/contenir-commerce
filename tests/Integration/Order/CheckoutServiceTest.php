<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\ItemUnavailableException;
use Contenir\Commerce\Exception\PurchaseItemMismatchException;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\PurchaseItem;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteItemEntity;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\Trait\OrderServicesTrait;
use Override;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Creating pending orders and beginning checkout, against a real in-memory
 * database and a scriptable gateway. Each test builds a fresh database.
 */
#[Group('integration')]
final class CheckoutServiceTest extends TestCase
{
    use OrderServicesTrait;

    /**
     * @return array<string, array{?string, string}>
     */
    public static function mislabelledProvider(): array
    {
        return [
            'another label' => ['A2', 'Variant 3 of "Headland, Dawn" is labelled "A3", not "A2"'],
            'no label'      => [null, 'Variant 3 of "Headland, Dawn" is labelled "A3", not ""'],
        ];
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function mispricedProvider(): array
    {
        return [
            'a cent cheaper' => [184_999, '"Headland, Dawn" (variant 1) is priced $1,850.00, not $1,849.99'],
            'dearer'         => [200_000, '"Headland, Dawn" (variant 1) is priced $1,850.00, not $2,000.00'],
            'free'           => [0, '"Headland, Dawn" (variant 1) is priced $1,850.00, not $0.00'],
        ];
    }

    #[Test]
    public function aConfiguredPrefixAndTaxRateShapeTheOrder(): void
    {
        $checkout = $this->checkoutWith(new ItemRepository($this->em), new CommerceSettings('GG', 'NZD', 15, 'GST'));

        $order = $checkout->createPendingOrder($this->items(), CommerceFactory::customer());

        static::assertSame(
            ['GG-2026-0001', ['total' => 283_000, 'gst_amount' => 36_913]],
            [$order->orderRef, $this->orderRow(['total', 'gst_amount'])],
        );
    }

    #[DataProvider('mislabelledProvider')]
    #[Test]
    public function aLabelThatDiffersFromTheVariantsIsRefused(?string $label, string $message): void
    {
        $this->addPrintEdition();

        $this->expectException(PurchaseItemMismatchException::class);
        $this->expectExceptionMessage($message);

        $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(3, price: 12_000, variantLabel: $label)],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function aMatchingTitleAndPriceAreAccepted(): void
    {
        $order = $this->checkout->createPendingOrder([CommerceFactory::purchaseItem(1)], CommerceFactory::customer());

        static::assertSame(185_000, $order->total);
    }

    #[Test]
    public function anEmptyOrderIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An order requires at least one item');

        $this->checkout->createPendingOrder([], CommerceFactory::customer());
    }

    #[Test]
    public function anItemWithoutATitleAcceptsTheCartTitle(): void
    {
        $this->updateBehindTheManager('UPDATE item SET title = NULL WHERE item_id = 1');

        $order = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1, 'Headland at Dawn')],
            CommerceFactory::customer(),
        );

        static::assertSame('ORD-2026-0001', $order->orderRef);
    }

    #[Test]
    public function anUnavailableItemIsReportedBeforeAMispricedOne(): void
    {
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 0 WHERE item_variant_id = 2');

        $this->expectException(ItemUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Swan Bay Nocturne');

        $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1, price: 1), CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000)],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function anUnlistedItemCannotBeOrdered(): void
    {
        $this->updateBehindTheManager("UPDATE item SET status = 'unlisted' WHERE item_id = 1");

        $this->expectException(ItemUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Headland, Dawn');

        $this->checkout->createPendingOrder([CommerceFactory::purchaseItem(1)], CommerceFactory::customer());
    }

    #[DataProvider('mispricedProvider')]
    #[Test]
    public function aPriceThatDiffersFromTheVariantsIsRefused(int $price, string $message): void
    {
        try {
            $this->checkout->createPendingOrder(
                [
                    CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000),
                    CommerceFactory::purchaseItem(1, price: $price),
                ],
                CommerceFactory::customer(),
            );
            static::fail('Expected PurchaseItemMismatchException');
        } catch (PurchaseItemMismatchException $e) {
            static::assertSame([$message, 1, 0], [
                $e->getMessage(),
                $e->getItemVariantId(),
                $this->rowCount('commerce_order'),
            ]);
        }
    }

    #[Test]
    public function aSiteItemsOwnTitleIsWhatTheCartTitleIsCheckedAgainst(): void
    {
        $this->updateBehindTheManager("UPDATE item SET title = NULL, medium = 'Oil on linen' WHERE item_id = 1");

        $this->expectException(PurchaseItemMismatchException::class);
        $this->expectExceptionMessage('The item of variant 1 is titled "Oil on linen", not "Headland, Dawn"');

        $this->checkoutWith(new ItemRepository($this->em, SiteItemEntity::class))->createPendingOrder(
            [CommerceFactory::purchaseItem(1)],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function aTitleThatDiffersFromTheItemsIsRefused(): void
    {
        try {
            $this->checkout->createPendingOrder(
                [CommerceFactory::purchaseItem(1, 'Headland at Dawn')],
                CommerceFactory::customer(),
            );
            static::fail('Expected PurchaseItemMismatchException');
        } catch (PurchaseItemMismatchException $e) {
            static::assertSame(
                ['The item of variant 1 is titled "Headland, Dawn", not "Headland at Dawn"', 1, 0],
                [$e->getMessage(), $e->getItemVariantId(), $this->rowCount('commerce_order')],
            );
        }
    }

    #[Test]
    public function aVariantCannotAppearTwiceInOneOrder(): void
    {
        try {
            $this->checkout->createPendingOrder(
                [
                    CommerceFactory::purchaseItem(1),
                    CommerceFactory::purchaseItem(2, 'Swan Bay'),
                    CommerceFactory::purchaseItem(1),
                ],
                CommerceFactory::customer(),
            );
            static::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            static::assertSame(
                ['"Headland, Dawn" appears in the order more than once', 0],
                [$e->getMessage(), $this->rowCount('commerce_order')],
            );
        }
    }

    #[Test]
    public function aVariantDeletedAfterCartingIsReportedUnavailable(): void
    {
        $this->expectException(ItemUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Lost Work');

        $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1), CommerceFactory::purchaseItem(99, 'Lost Work')],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function aVariantWhoseItemIsDeletedCannotBeOrdered(): void
    {
        $this->updateBehindTheManager('DELETE FROM item WHERE item_id = 1');

        $this->expectException(ItemUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Headland, Dawn');

        $this->checkout->createPendingOrder([CommerceFactory::purchaseItem(1)], CommerceFactory::customer());
    }

    #[Test]
    public function aVariantWhoseStockIsNotTrackedCanBeOrderedInAnyQuantity(): void
    {
        $this->updateBehindTheManager('UPDATE item_variant SET stock = NULL WHERE item_variant_id = 2');

        $order = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000, 40)],
            CommerceFactory::customer(),
        );

        static::assertSame(3_920_000, $order->total);
    }

    #[Test]
    public function beginningCheckoutAgainForTheSameOrderIsRefused(): void
    {
        $order = $this->checkedOutOrder();

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Checkout has already begun for order "ORD-2026-0001"');

        $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
    }

    #[Test]
    public function beginningCheckoutForAnOrderThatIsNotPendingIsRefused(): void
    {
        $order = $this->paidOrder();

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Checkout can only begin for a pending order, not a "paid" one');

        $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
    }

    #[Test]
    public function beginningCheckoutRechecksAvailability(): void
    {
        $order = $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 0 WHERE item_variant_id = 1');

        try {
            $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
            static::fail('Expected ItemUnavailableException');
        } catch (ItemUnavailableException $e) {
            static::assertSame(
                [['Headland, Dawn'], [], null],
                [$e->getTitles(), $this->gateway->checkoutRequests, $order->stripeCheckoutSessionId],
            );
        }
    }

    #[Test]
    public function beginningCheckoutSendsTheSnapshotsAndStoresTheSession(): void
    {
        $order = $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
        $this->clock->moveTo('2026-08-20 10:01:00');

        $session = $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
        $request = $this->gateway->checkoutRequests[0];

        static::assertSame(
            [
                'cs_fake_1',
                ['stripe_checkout_session_id' => 'cs_fake_1', 'updated' => '2026-08-20 10:01:00'],
                'https://example.test/thanks',
                'https://example.test/cart',
                'avery@example.test',
                ['order_ref' => 'ORD-2026-0001', 'order_id' => '1'],
                [
                    ['Headland, Dawn',    185_000, 1, 'June Hollis'],
                    ['Swan Bay Nocturne', 98_000,  1, 'Marcus Tran'],
                ],
            ],
            [
                $session->id,
                $this->orderRow(['stripe_checkout_session_id', 'updated']),
                $request->successUrl,
                $request->cancelUrl,
                $request->customerEmail,
                $request->metadata,
                array_map(
                    static fn($line): array => [$line->name, $line->price->amount, $line->quantity, $line->description],
                    $request->lineItems,
                ),
            ],
        );
    }

    #[Test]
    public function checkoutNamesALabelledVariantAndSendsItsQuantity(): void
    {
        $this->addPrintEdition();
        $order = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(3, price: 12_000, quantity: 2, variantLabel: 'A3')],
            CommerceFactory::customer(),
        );

        $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
        $line = $this->gateway->checkoutRequests[0]->lineItems[0];

        static::assertSame(
            ['Headland, Dawn — A3', 12_000, 2],
            [$line->name, $line->price->amount, $line->quantity],
        );
    }

    #[Test]
    public function creatingAnOrderRecordsTheCustomerTotalsAndSnapshots(): void
    {
        $order = $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());

        static::assertSame(
            [
                [
                    'order_id'       => 1,
                    'order_ref'      => 'ORD-2026-0001',
                    'customer_name'  => 'Avery Buyer',
                    'customer_email' => 'avery@example.test',
                    'customer_phone' => '0400 000 000',
                    'status'         => 'pending',
                    'total'          => 283_000,
                    'gst_amount'     => 25_727,
                    'customer_notes' => 'Will collect Saturday',
                    'created'        => '2026-08-20 10:00:00',
                    'updated'        => '2026-08-20 10:00:00',
                ],
                [
                    [1, 1, 1, 'Headland, Dawn',    null, 'June Hollis', 185_000, 1, '2026-08-20 10:00:00'],
                    [1, 2, 2, 'Swan Bay Nocturne', null, 'Marcus Tran', 98_000,  1, '2026-08-20 10:00:00'],
                ],
                'ORD-2026-0001',
            ],
            [
                $this->orderRow([
                    'order_id',
                    'order_ref',
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'status',
                    'total',
                    'gst_amount',
                    'customer_notes',
                    'created',
                    'updated',
                ]),
                [$this->lineRow(1), $this->lineRow(2)],
                $order->orderRef,
            ],
        );
    }

    #[Test]
    public function everyUnavailableItemIsReportedTogether(): void
    {
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 0 WHERE item_variant_id = 2');

        try {
            $this->checkout->createPendingOrder(
                [
                    CommerceFactory::purchaseItem(99, 'Lost Work'),
                    CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000),
                ],
                CommerceFactory::customer(),
            );
            static::fail('Expected ItemUnavailableException');
        } catch (ItemUnavailableException $e) {
            static::assertSame(['Lost Work', 'Swan Bay Nocturne'], $e->getTitles());
        }
    }

    #[Test]
    public function moreUnitsThanRemainCannotBeOrdered(): void
    {
        $this->addPrintEdition();

        $this->expectException(ItemUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Headland, Dawn');

        $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(3, price: 12_000, quantity: 6, variantLabel: 'A3')],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function ordersAreNumberedPerYearFromTheirId(): void
    {
        $this->checkout->createPendingOrder([CommerceFactory::purchaseItem(1)], CommerceFactory::customer());
        $this->clock->moveTo('2027-01-02 08:00:00');

        $second = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000)],
            CommerceFactory::customer(),
        );

        static::assertSame('ORD-2027-0002', $second->orderRef);
    }

    #[Test]
    public function purchaseItemsKeepTheirSnapshotsAfterTheVariantIsDeleted(): void
    {
        $this->addPrintEdition();
        $order = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(3, price: 12_000, quantity: 2, variantLabel: 'A3')],
            CommerceFactory::customer(),
        );
        $this->updateBehindTheManager('UPDATE commerce_order_item SET item_id = NULL, item_variant_id = NULL');

        $this->em->clear();

        $items = $this->checkout->purchaseItemsFor($order);

        static::assertEquals(
            [new PurchaseItem(0, 'Headland, Dawn', Money::fromCents(12_000), 2, 'A3', 'June Hollis')],
            $items,
        );
    }

    #[Test]
    public function severalUnitsOfOneVariantAreOneLine(): void
    {
        $this->addPrintEdition();

        $order = $this->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(3, price: 12_000, quantity: 3, variantLabel: 'A3')],
            CommerceFactory::customer(),
        );

        static::assertSame(
            [36_000, [[1, 1, 3, 'Headland, Dawn', 'A3', 'June Hollis', 12_000, 3, '2026-08-20 10:00:00']]],
            [$order->total, [$this->lineRow(1)]],
        );
    }

    #[Test]
    public function soldOutItemsCannotBeOrdered(): void
    {
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 0 WHERE item_variant_id = 2');

        try {
            $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
            static::fail('Expected ItemUnavailableException');
        } catch (ItemUnavailableException $e) {
            static::assertSame([['Swan Bay Nocturne'], 0], [$e->getTitles(), $this->rowCount('commerce_order')]);
        }
    }

    #[Test]
    public function theOrderAndItsLinesAreWrittenTogetherOrNotAtAll(): void
    {
        $this->updateBehindTheManager('DROP TABLE commerce_order_item');

        try {
            $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
            static::fail('Expected PDOException');
        } catch (PDOException) {
            static::assertSame(0, $this->rowCount('commerce_order'));
        }
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpOrderServices();
    }

    /**
     * Variant 3 of item 1: an A3 print edition of five at 120.00.
     */
    private function addPrintEdition(): void
    {
        $this->em->save(CommerceFactory::variant(1, 12_000, 5, 'A3'));
    }
}
