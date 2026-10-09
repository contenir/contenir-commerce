# Payments and Stripe

Payments go through `Contenir\Commerce\Payment\PaymentGatewayInterface`:

```php
interface PaymentGatewayInterface
{
    public function createCheckoutSession(CheckoutRequest $request): CheckoutSession;
    public function expireCheckoutSession(string $sessionId): CheckoutSession;
    public function refund(string $paymentIntentId, ?Money $amount = null, ?string $idempotencyKey = null): RefundResult;
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession;
}
```

Every method throws `Exception\PaymentFailedException` when the provider cannot complete it. Implement the
interface for another provider, or for a test double. `expireCheckoutSession()` was added in 2.0.0-RC2: custom
implementations must add it.

### Expiring a session

`expireCheckoutSession()` expires an open session so it can no longer be paid, and returns it (status `expired`). A
session that is already complete or expired cannot be expired; it is returned as it is, **without an error**, and the
caller reads its status: `isComplete()`, `isPaid()`, `isOpen()`. Any other failure throws `PaymentFailedException`.
`FulfilmentService::cancelOrder()` calls it for pending orders; see [orders](orders.md#cancelling).

## StripeGateway

`StripeGateway` uses Stripe hosted Checkout in the configured currency (`contenir_commerce.currency`, AUD by
default), with stripe-php 22 (API version `2026-09-30.endive`).

- Each `CheckoutLineItem` becomes a `price_data` line: `currency` (the ISO code in lower case, `aud` by default),
  `unit_amount` in cents, the quantity, the name, and the description. `CheckoutService` names a labelled variant
  "Title — Label" and passes the purchase item's description.
- The session expires `expiresAfterMinutes` after the injected clock's now. `CheckoutRequest` accepts 30 to 1,440
  minutes, Stripe's range; the default is 30.
- `customer_email` and `metadata` are sent when given. `CheckoutService` sends `order_ref` and `order_id`.
- Every stripe-php exception (API, network, authentication, rate limit, invalid argument) is rethrown as
  `PaymentFailedException`, with the Stripe exception as its previous.
- A refund with an idempotency key sends it as Stripe's `Idempotency-Key` header, so repeating it refunds once.
- `expireCheckoutSession()` calls `checkout.sessions.expire`. When Stripe refuses with an invalid request error (it
  only expires open sessions), the gateway retrieves the session: complete or expired, it is returned; still open,
  the refusal had another cause and `PaymentFailedException` is thrown.

## Paid, not just complete

A Checkout session is `complete` when the buyer finishes the form. With delayed payment methods (BECS Direct Debit,
for example) the funds arrive later: the session is complete but its `payment_status` is still `unpaid`.

`CheckoutSession::isComplete()` reports the first, `isPaid()` both. Completion fulfils only on `isPaid()`, and
reports `NotPaid` until then. Handle these webhook events:

| Event | Call |
| --- | --- |
| `checkout.session.completed` | `completeFromCheckoutSession($session->id)` |
| `checkout.session.async_payment_succeeded` | `completeFromCheckoutSession($session->id)` |
| `checkout.session.async_payment_failed` | `expireCheckout($session->id)` |
| `checkout.session.expired` | `expireCheckout($session->id)` |

Verify the webhook signature first (`Stripe\Webhook::constructEvent()`); the package does not receive webhooks
itself.

## Without a secret key

`Payment\Factory\StripeGatewayFactory` returns `UnconfiguredGateway` when `stripe.secret_key` is absent, null or
empty. The container and everything depending on the gateway still build; each gateway method (expiring a session
included) throws
`PaymentFailedException` ("Stripe is not configured"), the same exception a Stripe outage produces, so sites need one
error path.

## Testing

The package's own tests never reach the network. `StripeGateway` is tested against stripe-php's real request and
response handling, with `Stripe\ApiRequestor::setHttpClient()` pointed at an in-memory client; the order services
use a scriptable `PaymentGatewayInterface` fake. Sites can do the same.
