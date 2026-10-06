<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\PurchaseItemMismatchException;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\PurchaseItem;
use Contenir\Commerce\Tests\TestAsset\Entity\SiteArtworkEntity;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\Trait\OrderServicesTrait;
use Contenir\Db\Model\Type\TypeRegistry;
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
     * @return array<string, array{int, string}>
     */
    public static function mispricedProvider(): array
    {
        return [
            'a cent cheaper' => [184_999, '"Headland, Dawn" (artwork 1) is priced $1,850.00, not $1,849.99'],
            'dearer'         => [200_000, '"Headland, Dawn" (artwork 1) is priced $1,850.00, not $2,000.00'],
            'free'           => [0, '"Headland, Dawn" (artwork 1) is priced $1,850.00, not $0.00'],
        ];
    }

    #[Test]
    public function aMatchingTitleAndPriceAreAccepted(): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET title = 'Headland, Dawn' WHERE artwork_id = 1");

        $order = $this->titledCheckout()->createPendingOrder([CommerceFactory::item(1)], CommerceFactory::customer());

        static::assertSame(185_000, $order->total);
    }

    #[Test]
    public function anArtworkWithoutATitleOfItsOwnAcceptsTheCartTitle(): void
    {
        $order = $this->titledCheckout()->createPendingOrder(
            [CommerceFactory::item(1, 'Headland at Dawn')],
            CommerceFactory::customer(),
        );

        static::assertSame('LR-2026-0001', $order->orderRef);
    }

    #[Test]
    public function anEmptyOrderIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An order requires at least one item');

        $this->checkout->createPendingOrder([], CommerceFactory::customer());
    }

    #[Test]
    public function anUnavailableWorkIsReportedBeforeAMispricedOne(): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 2");

        $this->expectException(ArtworkUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Swan Bay Nocturne');

        $this->checkout->createPendingOrder(
            [CommerceFactory::item(1, price: 1), CommerceFactory::item(2, 'Swan Bay Nocturne', 98_000)],
            CommerceFactory::customer(),
        );
    }

    #[DataProvider('mispricedProvider')]
    #[Test]
    public function aPriceThatDiffersFromTheArtworksIsRefused(int $price, string $message): void
    {
        try {
            $this->checkout->createPendingOrder(
                [CommerceFactory::item(2, 'Swan Bay Nocturne', 98_000), CommerceFactory::item(1, price: $price)],
                CommerceFactory::customer(),
            );
            static::fail('Expected PurchaseItemMismatchException');
        } catch (PurchaseItemMismatchException $e) {
            static::assertSame([$message, 1, 0], [
                $e->getMessage(),
                $e->getArtworkId(),
                $this->rowCount('gallery_order'),
            ]);
        }
    }

    #[Test]
    public function aTitleIsCheckedWhenTheArtworkMapsOne(): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET title = 'Headland, Dawn' WHERE artwork_id = 1");

        try {
            $this->titledCheckout()->createPendingOrder(
                [CommerceFactory::item(1, 'Headland at Dawn')],
                CommerceFactory::customer(),
            );
            static::fail('Expected PurchaseItemMismatchException');
        } catch (PurchaseItemMismatchException $e) {
            static::assertSame(
                ['Artwork 1 is titled "Headland, Dawn", not "Headland at Dawn"', 1, 0],
                [$e->getMessage(), $e->getArtworkId(), $this->rowCount('gallery_order')],
            );
        }
    }

    #[Test]
    public function aWorkCannotAppearTwiceInOneOrder(): void
    {
        try {
            $this->checkout->createPendingOrder(
                [CommerceFactory::item(1), CommerceFactory::item(2, 'Swan Bay'), CommerceFactory::item(1)],
                CommerceFactory::customer(),
            );
            static::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            static::assertSame(
                ['"Headland, Dawn" appears in the order more than once', 0],
                [$e->getMessage(), $this->rowCount('gallery_order')],
            );
        }
    }

    #[Test]
    public function aWorkDeletedAfterCartingIsReportedUnavailable(): void
    {
        $this->expectException(ArtworkUnavailableException::class);
        $this->expectExceptionMessage('No longer available: Lost Work');

        $this->checkout->createPendingOrder(
            [CommerceFactory::item(1), CommerceFactory::item(99, 'Lost Work')],
            CommerceFactory::customer(),
        );
    }

    #[Test]
    public function beginningCheckoutAgainForTheSameOrderIsRefused(): void
    {
        $order = $this->checkedOutOrder();

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Checkout has already begun for order "LR-2026-0001"');

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
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 1");

        try {
            $this->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
            static::fail('Expected ArtworkUnavailableException');
        } catch (ArtworkUnavailableException $e) {
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
                ['order_ref' => 'LR-2026-0001', 'order_id' => '1'],
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
    public function creatingAnOrderRecordsTheCustomerTotalsAndSnapshots(): void
    {
        $order = $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());

        static::assertSame(
            [
                [
                    'order_id'       => 1,
                    'order_ref'      => 'LR-2026-0001',
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
                    [1, 1, 'Headland, Dawn',    'June Hollis', 185_000, '2026-08-20 10:00:00'],
                    [1, 2, 'Swan Bay Nocturne', 'Marcus Tran', 98_000,  '2026-08-20 10:00:00'],
                ],
                'LR-2026-0001',
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
    public function everyUnavailableWorkIsReportedTogether(): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 2");

        try {
            $this->checkout->createPendingOrder(
                [CommerceFactory::item(99, 'Lost Work'), CommerceFactory::item(2, 'Swan Bay Nocturne', 98_000)],
                CommerceFactory::customer(),
            );
            static::fail('Expected ArtworkUnavailableException');
        } catch (ArtworkUnavailableException $e) {
            static::assertSame(['Lost Work', 'Swan Bay Nocturne'], $e->getTitles());
        }
    }

    #[Test]
    public function ordersAreNumberedPerYearFromTheirId(): void
    {
        $this->checkout->createPendingOrder([CommerceFactory::item(1)], CommerceFactory::customer());
        $this->clock->moveTo('2027-01-02 08:00:00');

        $second = $this->checkout->createPendingOrder(
            [CommerceFactory::item(2, 'Swan Bay', 98_000)],
            CommerceFactory::customer(),
        );

        static::assertSame('LR-2027-0002', $second->orderRef);
    }

    #[Test]
    public function purchaseItemsKeepTheirSnapshotsAfterTheArtworkIsDeleted(): void
    {
        $order = $this->checkout->createPendingOrder(
            [new PurchaseItem(1, 'Tote bag', Money::fromCents(185_000))],
            CommerceFactory::customer(),
        );
        $this->updateBehindTheManager('UPDATE gallery_order_item SET artwork_id = NULL');

        $this->em->clear();

        $items = $this->checkout->purchaseItemsFor($order);

        static::assertEquals([new PurchaseItem(0, 'Tote bag', Money::fromCents(185_000))], $items);
    }

    #[Test]
    public function soldWorksCannotBeOrdered(): void
    {
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 2");

        try {
            $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
            static::fail('Expected ArtworkUnavailableException');
        } catch (ArtworkUnavailableException $e) {
            static::assertSame([['Swan Bay Nocturne'], 0], [$e->getTitles(), $this->rowCount('gallery_order')]);
        }
    }

    #[Test]
    public function theOrderAndItsLinesAreWrittenTogetherOrNotAtAll(): void
    {
        $this->updateBehindTheManager('DROP TABLE gallery_order_item');

        try {
            $this->checkout->createPendingOrder($this->items(), CommerceFactory::customer());
            static::fail('Expected PDOException');
        } catch (PDOException) {
            static::assertSame(0, $this->rowCount('gallery_order'));
        }
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpOrderServices();
    }

    /**
     * Checkout over a site artwork entity that maps the "title" column.
     */
    private function titledCheckout(): CheckoutService
    {
        return $this->checkoutWith(new ArtworkRepository(
            $this->em,
            $this->adapter,
            TypeRegistry::withDefaults(),
            SiteArtworkEntity::class,
        ));
    }
}
