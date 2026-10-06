<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Payment;

use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutRequest;
use Contenir\Commerce\Payment\CheckoutSession;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Commerce\Payment\RefundResult;
use Override;
use RuntimeException;

use function count;
use function sprintf;

/**
 * Scriptable in-memory payment gateway for exercising OrderManager flows.
 * Sessions are numbered cs_fake_1, cs_fake_2, ... in creation order.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var list<CheckoutRequest>
     */
    public array $checkoutRequests = [];

    /**
     * @var list<array{paymentIntentId: string, amount: ?int, idempotencyKey: ?string}>
     */
    public array $refunds = [];

    public int $retrievals = 0;

    private bool $failRefunds = false;

    /**
     * @var (callable(): void)|null
     */
    private $beforeNextRetrieval;

    /**
     * @var array<string, CheckoutSession>
     */
    private array $sessions = [];

    /**
     * Run $hook once, at the start of the next session retrieval: the moment
     * a completion has read its order but not yet claimed its works. Lets a
     * test interleave a second completion there.
     *
     * @param callable(): void $hook
     */
    public function beforeNextRetrieval(callable $hook): void
    {
        $this->beforeNextRetrieval = $hook;
    }

    /**
     * Mark a session complete and paid, as Stripe does after a card payment.
     */
    public function completeSession(string $sessionId, ?string $paymentIntentId): void
    {
        $this->settle($sessionId, $paymentIntentId, CheckoutSession::PAYMENT_STATUS_PAID);
    }

    /**
     * Mark a session complete but not yet paid, as Stripe does for a delayed
     * payment method such as BECS Direct Debit.
     */
    public function completeSessionAwaitingFunds(string $sessionId, string $paymentIntentId): void
    {
        $this->settle($sessionId, $paymentIntentId, 'unpaid');
    }

    #[Override]
    public function createCheckoutSession(CheckoutRequest $request): CheckoutSession
    {
        $this->checkoutRequests[] = $request;
        $id                       = sprintf('cs_fake_%d', count($this->checkoutRequests));

        $this->sessions[$id] = new CheckoutSession(
            $id,
            'open',
            sprintf('https://checkout.stripe.test/pay/%s', $id),
            null,
            $request->customerEmail,
            'unpaid',
        );

        return $this->sessions[$id];
    }

    public function failRefunds(): void
    {
        $this->failRefunds = true;
    }

    #[Override]
    public function refund(
        string $paymentIntentId,
        ?Money $amount = null,
        ?string $idempotencyKey = null,
    ): RefundResult {
        if ($this->failRefunds) {
            throw PaymentFailedException::fromProvider('refund Stripe payment', new RuntimeException('declined'));
        }

        $this->refunds[] = [
            'paymentIntentId' => $paymentIntentId,
            'amount'          => $amount?->amount,
            'idempotencyKey'  => $idempotencyKey,
        ];

        return new RefundResult(sprintf('re_fake_%d', count($this->refunds)), 'succeeded');
    }

    #[Override]
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession
    {
        ++$this->retrievals;

        $hook                      = $this->beforeNextRetrieval;
        $this->beforeNextRetrieval = null;
        if (null !== $hook) {
            $hook();
        }

        return (
            $this->sessions[$sessionId] ?? throw PaymentFailedException::fromProvider(
                'retrieve Stripe checkout session',
                new RuntimeException(sprintf('Unknown session "%s"', $sessionId)),
            )
        );
    }

    private function settle(string $sessionId, ?string $paymentIntentId, string $paymentStatus): void
    {
        $this->sessions[$sessionId] = new CheckoutSession(
            $sessionId,
            CheckoutSession::STATUS_COMPLETE,
            null,
            $paymentIntentId,
            $this->sessions[$sessionId]->customerEmail ?? null,
            $paymentStatus,
        );
    }
}
