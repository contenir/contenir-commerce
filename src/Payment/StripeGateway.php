<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Override;
use Psr\Clock\ClockInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ExceptionInterface as StripeException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

use function is_string;
use function strtolower;

/**
 * Stripe hosted Checkout in the site's currency (AUD by default). Every
 * error stripe-php raises (API, network, authentication, invalid argument)
 * is rethrown as PaymentFailedException with the Stripe exception as its
 * previous.
 *
 * @api
 */
final readonly class StripeGateway implements PaymentGatewayInterface
{
    /**
     * @param string $currency the ISO 4217 code, normally CommerceSettings::$currency; sent in lower case
     */
    public function __construct(
        private StripeClient $client,
        private ClockInterface $clock,
        private string $currency = CommerceSettings::DEFAULT_CURRENCY,
    ) {}

    /**
     * @throws PaymentFailedException
     */
    #[Override]
    public function createCheckoutSession(CheckoutRequest $request): CheckoutSession
    {
        $lineItems = [];
        foreach ($request->lineItems as $item) {
            $lineItems[] = $this->toStripeLineItem($item);
        }

        $params = [
            'mode'        => 'payment',
            'line_items'  => $lineItems,
            'success_url' => $request->successUrl,
            'cancel_url'  => $request->cancelUrl,
            'expires_at'  => $this->clock->now()->getTimestamp() + ($request->expiresAfterMinutes * 60),
        ];

        if (null !== $request->customerEmail) {
            $params['customer_email'] = $request->customerEmail;
        }

        if ([] !== $request->metadata) {
            $params['metadata'] = $request->metadata;
        }

        try {
            $session = $this->client->checkout->sessions->create($params);
        } catch (StripeException $e) {
            throw PaymentFailedException::fromProvider('create Stripe checkout session', $e);
        }

        return $this->toCheckoutSession($session);
    }

    /**
     * Stripe refuses to expire a session that is not open with an invalid
     * request error. The session is then retrieved: one that is complete or
     * expired is returned as it is, and one still open (the refusal had
     * another cause) fails.
     *
     * @throws PaymentFailedException
     */
    #[Override]
    public function expireCheckoutSession(string $sessionId): CheckoutSession
    {
        try {
            $session = $this->client->checkout->sessions->expire($sessionId);
        } catch (InvalidRequestException $e) {
            $current = $this->retrieveCheckoutSession($sessionId);

            return $current->isOpen()
                ? throw PaymentFailedException::fromProvider('expire Stripe checkout session', $e)
                : $current;
        } catch (StripeException $e) {
            throw PaymentFailedException::fromProvider('expire Stripe checkout session', $e);
        }

        return $this->toCheckoutSession($session);
    }

    /**
     * @throws PaymentFailedException
     */
    #[Override]
    public function refund(
        string $paymentIntentId,
        ?Money $amount = null,
        ?string $idempotencyKey = null,
    ): RefundResult {
        $params = ['payment_intent' => $paymentIntentId];
        if (null !== $amount) {
            $params['amount'] = $amount->amount;
        }

        $options = null === $idempotencyKey ? [] : ['idempotency_key' => $idempotencyKey];

        try {
            $refund = $this->client->refunds->create($params, $options);
        } catch (StripeException $e) {
            throw PaymentFailedException::fromProvider('refund Stripe payment', $e);
        }

        return new RefundResult($refund->id, (string) $refund->status);
    }

    /**
     * @throws PaymentFailedException
     */
    #[Override]
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($sessionId);
        } catch (StripeException $e) {
            throw PaymentFailedException::fromProvider('retrieve Stripe checkout session', $e);
        }

        return $this->toCheckoutSession($session);
    }

    private function toCheckoutSession(Session $session): CheckoutSession
    {
        $paymentIntent = $session->payment_intent;

        return new CheckoutSession(
            $session->id,
            (string) $session->status,
            $session->url,
            null === $paymentIntent || is_string($paymentIntent) ? $paymentIntent : $paymentIntent->id,
            $session->customer_email,
            $session->payment_status,
        );
    }

    /**
     * @return array{quantity: int, price_data: array{currency: string, unit_amount: int, product_data: array{name: string, description?: string}}}
     */
    private function toStripeLineItem(CheckoutLineItem $item): array
    {
        $productData = ['name' => $item->name];
        if (null !== $item->description) {
            $productData['description'] = $item->description;
        }

        return [
            'quantity'   => $item->quantity,
            'price_data' => [
                'currency'     => strtolower($this->currency),
                'unit_amount'  => $item->price->amount,
                'product_data' => $productData,
            ],
        ];
    }
}
