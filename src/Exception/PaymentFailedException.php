<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * The payment provider could not complete an operation, or no payment
 * exists to act on. Every Stripe error surfaces as this exception.
 *
 * @api
 */
final class PaymentFailedException extends RuntimeException implements ExceptionInterface
{
    public static function fromProvider(string $operation, Throwable $previous): self
    {
        return new self(sprintf('Unable to %s', $operation), 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('Stripe is not configured: set stripe.secret_key in local configuration');
    }

    public static function nothingToRefund(string $orderRef): self
    {
        return new self(sprintf('Order "%s" has no Stripe payment to refund', $orderRef));
    }
}
