<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidTransitionException;

use function in_array;

/**
 * Order lifecycle. Prices are captured at sale time; an order can still be
 * refunded after collection, for returns and arrangements made outside the
 * shop.
 *
 * @api
 */
enum OrderStatus: string
{
    case Pending        = 'pending';
    case Paid           = 'paid';
    case AwaitingPickup = 'awaiting_pickup';
    case Collected      = 'collected';
    case Refunded       = 'refunded';
    case Cancelled      = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::AwaitingPickup, self::Refunded, self::Cancelled],
            self::AwaitingPickup => [self::Collected, self::Refunded, self::Cancelled],
            self::Collected => [self::Refunded],
            self::Refunded, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), strict: true);
    }

    public function isFinal(): bool
    {
        return [] === $this->allowedTransitions();
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::AwaitingPickup => 'Awaiting pickup',
            self::Collected => 'Collected',
            self::Refunded => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @throws InvalidTransitionException When the lifecycle does not allow the move.
     */
    public function transitionTo(self $next): self
    {
        if (! $this->canTransitionTo($next)) {
            throw InvalidTransitionException::between($this, $next);
        }

        return $next;
    }
}
