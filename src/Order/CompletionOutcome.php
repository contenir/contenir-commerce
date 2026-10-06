<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

/**
 * What OrderManager::completeFromCheckoutSession() did.
 *
 * @api
 */
enum CompletionOutcome: string
{
    /**
     * The payment was confirmed: the order is paid and its works are sold.
     */
    case Completed = 'completed';

    /**
     * The order had already been completed (or refunded, collected, ...);
     * nothing changed. Webhook retries and thank-you page revisits land here.
     */
    case AlreadyCompleted = 'already_completed';

    /**
     * The checkout session is not paid yet: still open, or completed with a
     * delayed payment method whose funds have not arrived.
     */
    case NotPaid = 'not_paid';

    /**
     * Another buyer completed payment for one of the works first; this
     * payment was refunded in full and the order closed (no holds policy:
     * first completed payment wins).
     */
    case RefundedRace = 'refunded_race';

    /**
     * The order was cancelled before the payment arrived; the payment was
     * refunded in full and the order stays cancelled.
     */
    case RefundedCancelled = 'refunded_cancelled';
}
