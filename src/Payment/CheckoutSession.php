<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

/**
 * A provider checkout session. A session can be complete without being
 * paid: delayed payment methods (BECS Direct Debit, for example) complete
 * the session first and settle later, so fulfil on isPaid(), not isComplete().
 *
 * @api
 */
final readonly class CheckoutSession
{
    public const string STATUS_COMPLETE = 'complete';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_OPEN = 'open';

    public const string PAYMENT_STATUS_PAID = 'paid';

    /**
     * @mago-expect lint:excessive-parameter-list One parameter per Stripe session field the package reads.
     */
    public function __construct(
        public string $id,
        public string $status,
        public ?string $url = null,
        public ?string $paymentIntentId = null,
        public ?string $customerEmail = null,
        public ?string $paymentStatus = null,
    ) {}

    /**
     * The customer finished the checkout form. Funds may not have arrived.
     */
    public function isComplete(): bool
    {
        return self::STATUS_COMPLETE === $this->status;
    }

    /**
     * The customer can still pay through the session.
     */
    public function isOpen(): bool
    {
        return self::STATUS_OPEN === $this->status;
    }

    /**
     * The session is complete and its payment has been received.
     */
    public function isPaid(): bool
    {
        return $this->isComplete() && self::PAYMENT_STATUS_PAID === $this->paymentStatus;
    }
}
