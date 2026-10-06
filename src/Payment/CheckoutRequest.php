<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

use Contenir\Commerce\Exception\InvalidArgumentException;

use function sprintf;

/**
 * @api
 */
final readonly class CheckoutRequest
{
    /**
     * Stripe checkout sessions expire between 30 minutes and 24 hours after
     * they are created.
     */
    public const int MIN_EXPIRY_MINUTES = 30;

    public const int MAX_EXPIRY_MINUTES = 1440;

    /**
     * @param list<CheckoutLineItem> $lineItems
     * @param array<string, string>  $metadata
     *
     * @mago-expect lint:excessive-parameter-list One parameter per Stripe checkout setting.
     * @throws InvalidArgumentException When there are no line items or the expiry is out of Stripe's range.
     */
    public function __construct(
        public array $lineItems,
        public string $successUrl,
        public string $cancelUrl,
        public ?string $customerEmail = null,
        public array $metadata = [],
        public int $expiresAfterMinutes = self::MIN_EXPIRY_MINUTES,
    ) {
        if ([] === $lineItems) {
            throw new InvalidArgumentException('Checkout requires at least one line item');
        }

        if ($expiresAfterMinutes < self::MIN_EXPIRY_MINUTES || $expiresAfterMinutes > self::MAX_EXPIRY_MINUTES) {
            throw new InvalidArgumentException(sprintf(
                'Stripe checkout sessions must expire %d to %d minutes after creation, got %d',
                self::MIN_EXPIRY_MINUTES,
                self::MAX_EXPIRY_MINUTES,
                $expiresAfterMinutes,
            ));
        }
    }
}
