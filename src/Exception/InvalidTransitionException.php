<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use Contenir\Commerce\Order\OrderStatus;
use DomainException;

use function sprintf;

/**
 * An order was asked to do something its status does not allow.
 *
 * @api
 */
final class InvalidTransitionException extends DomainException implements ExceptionInterface
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self(sprintf('Order cannot move from "%s" to "%s"', $from->value, $to->value));
    }

    public static function checkoutAlreadyStarted(string $orderRef): self
    {
        return new self(sprintf(
            'Checkout has already begun for order "%s"; create a new pending order to check out again',
            $orderRef,
        ));
    }

    public static function checkoutNotPending(OrderStatus $status): self
    {
        return new self(sprintf('Checkout can only begin for a pending order, not a "%s" one', $status->value));
    }
}
