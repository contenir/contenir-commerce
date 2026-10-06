<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Order;

use Contenir\Commerce\Exception\InvalidTransitionException;
use Contenir\Commerce\Order\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

#[Group('unit')]
final class OrderStatusTest extends TestCase
{
    /**
     * Every allowed edge in the lifecycle.
     *
     * @var list<array{string, string}>
     */
    private const array ALLOWED = [
        ['pending',         'paid'],
        ['pending',         'cancelled'],
        ['paid',            'awaiting_pickup'],
        ['paid',            'refunded'],
        ['paid',            'cancelled'],
        ['awaiting_pickup', 'collected'],
        ['awaiting_pickup', 'refunded'],
        ['awaiting_pickup', 'cancelled'],
        ['collected',       'refunded'],
    ];

    /**
     * @return array<string, array{OrderStatus, list<OrderStatus>}>
     */
    public static function allowedTransitionsProvider(): array
    {
        return [
            'pending'         => [OrderStatus::Pending, [OrderStatus::Paid, OrderStatus::Cancelled]],
            'paid'            => [
                OrderStatus::Paid,
                [OrderStatus::AwaitingPickup, OrderStatus::Refunded, OrderStatus::Cancelled],
            ],
            'awaiting pickup' => [
                OrderStatus::AwaitingPickup,
                [OrderStatus::Collected, OrderStatus::Refunded, OrderStatus::Cancelled],
            ],
            'collected'       => [OrderStatus::Collected, [OrderStatus::Refunded]],
            'refunded'        => [OrderStatus::Refunded, []],
            'cancelled'       => [OrderStatus::Cancelled, []],
        ];
    }

    /**
     * @return array<string, array{OrderStatus, bool}>
     */
    public static function finalityProvider(): array
    {
        return [
            'pending'         => [OrderStatus::Pending, false],
            'paid'            => [OrderStatus::Paid, false],
            'awaiting pickup' => [OrderStatus::AwaitingPickup, false],
            'collected'       => [OrderStatus::Collected, false],
            'refunded'        => [OrderStatus::Refunded, true],
            'cancelled'       => [OrderStatus::Cancelled, true],
        ];
    }

    /**
     * @return array<string, array{OrderStatus, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'pending'         => [OrderStatus::Pending, 'Pending'],
            'paid'            => [OrderStatus::Paid, 'Paid'],
            'awaiting pickup' => [OrderStatus::AwaitingPickup, 'Awaiting pickup'],
            'collected'       => [OrderStatus::Collected, 'Collected'],
            'refunded'        => [OrderStatus::Refunded, 'Refunded'],
            'cancelled'       => [OrderStatus::Cancelled, 'Cancelled'],
        ];
    }

    /**
     * Every pair of statuses, allowed or not.
     *
     * @return array<string, array{OrderStatus, OrderStatus, bool}>
     */
    public static function transitionMatrixProvider(): array
    {
        $cases = [];
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $cases[sprintf('%s to %s', $from->value, $to->value)] = [
                    $from,
                    $to,
                    in_array([$from->value, $to->value], self::ALLOWED, strict: true),
                ];
            }
        }

        return $cases;
    }

    /**
     * @param list<OrderStatus> $allowed
     */
    #[DataProvider('allowedTransitionsProvider')]
    #[Test]
    public function listsTheStatusesItCanMoveTo(OrderStatus $status, array $allowed): void
    {
        static::assertSame($allowed, $status->allowedTransitions());
    }

    #[DataProvider('finalityProvider')]
    #[Test]
    public function onlyRefundedAndCancelledAreFinal(OrderStatus $status, bool $isFinal): void
    {
        static::assertSame($isFinal, $status->isFinal());
    }

    #[DataProvider('labelProvider')]
    #[Test]
    public function providesAHumanReadableLabel(OrderStatus $status, string $label): void
    {
        static::assertSame($label, $status->label());
    }

    #[DataProvider('transitionMatrixProvider')]
    #[Test]
    public function theTransitionMatrixIsEnforcedExhaustively(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        static::assertSame($allowed, $from->canTransitionTo($to));
    }

    #[Test]
    public function transitionToRefusesAMoveTheLifecycleDoesNotAllow(): void
    {
        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Order cannot move from "collected" to "pending"');

        OrderStatus::Collected->transitionTo(OrderStatus::Pending);
    }

    #[Test]
    public function transitionToReturnsTheNextStatus(): void
    {
        static::assertSame(OrderStatus::Paid, OrderStatus::Pending->transitionTo(OrderStatus::Paid));
    }
}
