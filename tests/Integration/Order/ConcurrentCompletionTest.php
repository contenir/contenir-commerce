<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Order;

use Contenir\Commerce\Order\CompletionOutcome;
use Contenir\Commerce\Order\CompletionResult;
use Contenir\Commerce\Tests\TestAsset\Clock\MovableClock;
use Contenir\Commerce\Tests\TestAsset\Database\Schema;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use Contenir\Commerce\Tests\TestAsset\Order\CommerceProcess;
use Contenir\Commerce\Tests\TestAsset\Payment\FakePaymentGateway;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function is_array;
use function random_bytes;
use function sprintf;

/**
 * Two completions interleaved across two connections to one SQLite
 * database, each with its own EntityManager, as two PHP processes (the
 * webhook and the thank-you page, or two buyers' webhooks) would run them.
 * The second completion has already read its order, and holds the variant
 * with stock left, when the first runs to the end; only then does it claim.
 *
 * The database is an in-memory, shared-cache SQLite database with a name
 * unique to each test, so nothing survives between tests.
 */
#[Group('integration')]
final class ConcurrentCompletionTest extends TestCase
{
    private CommerceProcess $first;

    private FakePaymentGateway $gateway;

    private PDO $holder;

    private CommerceProcess $second;

    #[Test]
    public function aClaimLostPartWayReturnsTheStockAlreadyClaimed(): void
    {
        $both = $this->second->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1), CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000)],
            CommerceFactory::customer(),
        );
        $this->second->checkout->beginCheckout($both, 'https://example.test/thanks', 'https://example.test/cart');
        $winner = $this->first->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(2, 'Swan Bay Nocturne', 98_000)],
            CommerceFactory::customer(),
        );
        $this->first->checkout->beginCheckout($winner, 'https://example.test/thanks', 'https://example.test/cart');
        $this->gateway->completeSession('cs_fake_1', 'pi_both');
        $this->gateway->completeSession('cs_fake_2', 'pi_winner');
        $this->gateway->beforeNextRetrieval(function (): void {
            $this->first->completion->completeFromCheckoutSession('cs_fake_2');
        });

        $lost = $this->second->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [
                CompletionOutcome::RefundedRace,
                ['Swan Bay Nocturne'],
                [1, null],
                [0, '2026-08-20 10:00:00'],
            ],
            [
                $lost->outcome,
                $lost->unavailableTitles,
                $this->variantRow(1),
                $this->variantRow(2),
            ],
        );
    }

    #[Test]
    public function aSecondCompletionOfTheSameOrderDoesNotRefundTheOrderThatWon(): void
    {
        $order = $this->first->checkout->createPendingOrder([CommerceFactory::purchaseItem(
            1,
        )], CommerceFactory::customer());
        $this->first->checkout->beginCheckout($order, 'https://example.test/thanks', 'https://example.test/cart');
        $this->gateway->completeSession('cs_fake_1', 'pi_once');
        $webhook = null;
        $this->gateway->beforeNextRetrieval(function () use (&$webhook): void {
            $webhook = $this->first->completion->completeFromCheckoutSession('cs_fake_1');
        });

        $thankYouPage = $this->second->completion->completeFromCheckoutSession('cs_fake_1');

        static::assertSame(
            [
                CompletionOutcome::Completed,
                CompletionOutcome::AlreadyCompleted,
                [],
                'paid',
                [0, '2026-08-20 10:00:00'],
            ],
            [
                $webhook instanceof CompletionResult ? $webhook->outcome : null,
                $thankYouPage->outcome,
                $this->gateway->refunds,
                $this->orderStatus(1),
                $this->variantRow(1),
            ],
        );
    }

    #[Test]
    public function twoBuyersPayingForAnEditionAtOnceBothGetAUnitWhileStockLasts(): void
    {
        $this->holder->exec('UPDATE item_variant SET stock = 2 WHERE item_variant_id = 1');
        $firstOrder = $this->first->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1)],
            CommerceFactory::customer(),
        );
        $this->first->checkout->beginCheckout($firstOrder, 'https://example.test/thanks', 'https://example.test/cart');
        $secondOrder = $this->second->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1)],
            CommerceFactory::customer(),
        );
        $this->second->checkout->beginCheckout(
            $secondOrder,
            'https://example.test/thanks',
            'https://example.test/cart',
        );
        $this->gateway->completeSession('cs_fake_1', 'pi_first');
        $this->gateway->completeSession('cs_fake_2', 'pi_second');
        $first = null;
        $this->gateway->beforeNextRetrieval(function () use (&$first): void {
            $first = $this->first->completion->completeFromCheckoutSession('cs_fake_1');
        });

        $second = $this->second->completion->completeFromCheckoutSession('cs_fake_2');

        static::assertSame(
            [CompletionOutcome::Completed, CompletionOutcome::Completed, [], [0, '2026-08-20 10:00:00']],
            [
                $first instanceof CompletionResult ? $first->outcome : null,
                $second->outcome,
                $this->gateway->refunds,
                $this->variantRow(1),
            ],
        );
    }

    #[Test]
    public function twoBuyersPayingForTheLastUnitAtOnceSellItOnceAndRefundTheSecond(): void
    {
        $firstOrder = $this->first->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1)],
            CommerceFactory::customer(),
        );
        $this->first->checkout->beginCheckout($firstOrder, 'https://example.test/thanks', 'https://example.test/cart');
        $secondOrder = $this->second->checkout->createPendingOrder(
            [CommerceFactory::purchaseItem(1)],
            CommerceFactory::customer(),
        );
        $this->second->checkout->beginCheckout(
            $secondOrder,
            'https://example.test/thanks',
            'https://example.test/cart',
        );
        $heldBySecond = $this->second->variants->find(1);
        $this->gateway->completeSession('cs_fake_1', 'pi_first');
        $this->gateway->completeSession('cs_fake_2', 'pi_second');
        $winner = null;
        $this->gateway->beforeNextRetrieval(function () use (&$winner): void {
            $winner = $this->first->completion->completeFromCheckoutSession('cs_fake_1');
        });

        $loser = $this->second->completion->completeFromCheckoutSession('cs_fake_2');

        static::assertSame(
            [
                1,
                CompletionOutcome::Completed,
                CompletionOutcome::RefundedRace,
                ['Headland, Dawn'],
                [[
                    'paymentIntentId' => 'pi_second',
                    'amount'          => null,
                    'idempotencyKey'  => 'contenir-commerce-race-refund-pi_second',
                ]],
                ['paid', 'refunded'],
                [0, '2026-08-20 10:00:00'],
            ],
            [
                $heldBySecond?->stock,
                $winner instanceof CompletionResult ? $winner->outcome : null,
                $loser->outcome,
                $loser->unavailableTitles,
                $this->gateway->refunds,
                [$this->orderStatus(1), $this->orderStatus(2)],
                $this->variantRow(1),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $dsn          = sprintf('sqlite:file:commerce-%s?mode=memory&cache=shared', bin2hex(random_bytes(8)));
        $this->holder = new PDO($dsn);
        $this->holder->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (Schema::create() as $statement) {
            $this->holder->exec($statement);
        }

        $this->holder->exec(
            "INSERT INTO item (title, status) VALUES ('Headland, Dawn', 'listed'), ('Swan Bay Nocturne', 'listed')",
        );
        $this->holder->exec('INSERT INTO item_variant (item_id, price, stock) VALUES (1, 185000, 1), (2, 98000, 1)');

        $clock         = new MovableClock('2026-08-20 10:00:00');
        $this->gateway = new FakePaymentGateway();
        $this->first   = new CommerceProcess($dsn, $this->gateway, $clock);
        $this->second  = new CommerceProcess($dsn, $this->gateway, $clock);
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($this->first, $this->second, $this->holder);
    }

    private function orderStatus(int $orderId): mixed
    {
        $statement = $this->holder->prepare('SELECT status FROM commerce_order WHERE order_id = :id');
        $statement->execute(['id' => $orderId]);

        return $statement->fetchColumn();
    }

    /**
     * @return list<mixed>
     */
    private function variantRow(int $itemVariantId): array
    {
        $statement = $this->holder->prepare('SELECT stock, updated FROM item_variant WHERE item_variant_id = :id');
        $statement->execute(['id' => $itemVariantId]);

        $row = $statement->fetch(PDO::FETCH_NUM);

        return is_array($row) ? $row : [];
    }
}
