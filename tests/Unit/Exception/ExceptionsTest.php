<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Exception;

use Contenir\Commerce\Exception\ArtworkUnavailableException;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Exception\ExceptionInterface;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Exception\OverflowException;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Exception\PurchaseItemMismatchException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\OrderStatus;
use Contenir\Commerce\Order\PurchaseItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[Group('unit')]
final class ExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{Throwable, string}>
     */
    public static function messageProvider(): array
    {
        return [
            'unavailable titles'     => [
                ArtworkUnavailableException::forTitles(['Rip Tide', 'Moonah Study']),
                'No longer available: Rip Tide, Moonah Study',
            ],
            'invalid service'        => [
                ConfigurationException::invalidService('svc', 'Foo', 42),
                'Service "svc" must be a Foo, got int',
            ],
            'invalid entity class'   => [
                ConfigurationException::invalidEntityClass('contenir_commerce.order_entity', 'Foo', 'Bar'),
                'Config "contenir_commerce.order_entity" must name an existing subclass of Bar, got "Foo"',
            ],
            'unknown repository'     => [
                ConfigurationException::unknownRepository('Foo'),
                'No repository "Foo" is built by this factory',
            ],
            'invalid value'          => [
                ConfigurationException::invalidValue('stripe.secret_key', 'a string', null),
                'Config "stripe.secret_key" must be a string, got null',
            ],
            'transition'             => [
                InvalidTransitionException::between(OrderStatus::Collected, OrderStatus::Pending),
                'Order cannot move from "collected" to "pending"',
            ],
            'checkout already begun' => [
                InvalidTransitionException::checkoutAlreadyStarted('LR-2026-0001'),
                'Checkout has already begun for order "LR-2026-0001"; create a new pending order to check out again',
            ],
            'checkout not pending'   => [
                InvalidTransitionException::checkoutNotPending(OrderStatus::Paid),
                'Checkout can only begin for a pending order, not a "paid" one',
            ],
            'order for session'      => [
                OrderNotFoundException::forCheckoutSession('cs_1'),
                'No order for checkout session "cs_1"',
            ],
            'unsaved order'          => [
                OrderNotFoundException::unsaved(),
                'The order has no id; save it first',
            ],
            'overflow'               => [
                OverflowException::forOperation('addition'),
                'Money addition overflows the integer range of cents',
            ],
            'provider failure'       => [
                PaymentFailedException::fromProvider('refund Stripe payment', new RuntimeException('x')),
                'Unable to refund Stripe payment',
            ],
            'nothing to refund'      => [
                PaymentFailedException::nothingToRefund('LR-2026-0001'),
                'Order "LR-2026-0001" has no Stripe payment to refund',
            ],
            'not configured'         => [
                PaymentFailedException::notConfigured(),
                'Stripe is not configured: set stripe.secret_key in local configuration',
            ],
            'mispriced item'         => [
                PurchaseItemMismatchException::forPrice(
                    new PurchaseItem(7, 'Rip Tide', Money::fromCents(1_000)),
                    Money::fromCents(185_000),
                ),
                '"Rip Tide" (artwork 7) is priced $1,850.00, not $10.00',
            ],
            'mistitled item'         => [
                PurchaseItemMismatchException::forTitle(
                    new PurchaseItem(7, 'Rip-tide', Money::fromCents(1_000)),
                    'Rip Tide',
                ),
                'Artwork 7 is titled "Rip Tide", not "Rip-tide"',
            ],
            'invalid argument'       => [
                new InvalidArgumentException('bad'),
                'bad',
            ],
        ];
    }

    #[Test]
    public function aMismatchedItemExceptionNamesTheArtwork(): void
    {
        static::assertSame(
            [7, 7],
            [
                PurchaseItemMismatchException::forPrice(
                    new PurchaseItem(7, 'Rip Tide', Money::fromCents(1_000)),
                    Money::fromCents(2_000),
                )->getArtworkId(),
                PurchaseItemMismatchException::forTitle(
                    new PurchaseItem(7, 'Rip Tide', Money::fromCents(1_000)),
                    'Rip-tide',
                )->getArtworkId(),
            ],
        );
    }

    #[Test]
    public function anUnavailableArtworkExceptionCarriesTheTitles(): void
    {
        static::assertSame(
            ['Rip Tide', 'Moonah Study'],
            ArtworkUnavailableException::forTitles(['Rip Tide', 'Moonah Study'])->getTitles(),
        );
    }

    #[Test]
    public function aProviderFailureKeepsTheProviderErrorAsItsPrevious(): void
    {
        $previous = new RuntimeException('card_declined');

        $exception = PaymentFailedException::fromProvider('refund Stripe payment', $previous);

        static::assertSame([$previous, 0], [$exception->getPrevious(), $exception->getCode()]);
    }

    #[DataProvider('messageProvider')]
    #[Test]
    public function everyExceptionIsMarkedAndExplainsItself(Throwable $exception, string $message): void
    {
        static::assertSame(
            [true, $message],
            [$exception instanceof ExceptionInterface, $exception->getMessage()],
        );
    }
}
