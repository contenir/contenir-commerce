<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use RuntimeException;

use function sprintf;

/**
 * No order matches a Stripe checkout session, or an order has not been
 * saved yet.
 *
 * @api
 */
final class OrderNotFoundException extends RuntimeException implements ExceptionInterface
{
    public static function forCheckoutSession(string $sessionId): self
    {
        return new self(sprintf('No order for checkout session "%s"', $sessionId));
    }

    public static function unsaved(): self
    {
        return new self('The order has no id; save it first');
    }
}
