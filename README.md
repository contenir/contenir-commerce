# contenir/contenir-commerce

[![Continuous Integration](https://github.com/contenir/contenir-commerce/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-commerce/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-commerce/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-commerce)

Formerly `contenir/commerce`; the old package is abandoned in favour of this one.

The commerce domain for [Contenir](https://github.com/contenir) gallery sites, built on
[contenir-db-model 2](https://github.com/contenir/contenir-db-model):

- **orders** and their lifecycle (`OrderManager`, `OrderStatus`): pending, paid, awaiting pickup, collected,
  refunded, cancelled, with every transition enforced;
- **artworks** and retail products, with availability checked when an order is created, when checkout begins and
  when payment completes;
- GST-inclusive **money** in integer cents (`Money`), with exact GST rounding;
- **artist enquiries** and their uploaded files, and the transactional **email log**;
- **Stripe** hosted Checkout and refunds behind `PaymentGatewayInterface`, with every Stripe error surfaced as
  `PaymentFailedException`.

It ships a `ConfigProvider` (Mezzio and other PSR-11 containers) and a `Module` (laminas-mvc), and has no MVC or
Mezzio plumbing of its own. Version 2.0 is not compatible with 0.2; see [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Requirements

- PHP 8.3, 8.4 or 8.5
- contenir/contenir-db-model 2.x (on php-db/phpdb 0.6, with a platform package such as `php-db/phpdb-mysql`)
- stripe/stripe-php 22.x
- Any PSR-11 container, for the optional factories

## Install

2.0 is a release candidate (`2.0.0-RC1`): contenir-db-model 2 is itself at RC and builds on php-db/phpdb 0.6, which
has no stable release yet. Composer only honours stability flags in the root package, so a site needs these in its
own `composer.json`:

```json
{
    "require": {
        "contenir/contenir-commerce": "^2.0@RC",
        "contenir/contenir-db-model": "^2.0@RC",
        "php-db/phpdb": "0.6.x-dev@dev"
    }
}
```

Alternatively set `"minimum-stability": "dev"` with `"prefer-stable": true` in the site's `composer.json` and
require `contenir/contenir-commerce` normally.

With [laminas-component-installer](https://docs.laminas.dev/laminas-component-installer/) the
`Contenir\Commerce\ConfigProvider` (Mezzio) or the `Contenir\Commerce` module (laminas-mvc) is added to your
configuration automatically. contenir-db-model's own `ConfigProvider` or module supplies the `EntityManager`.

## Quick start

```php
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Order\CustomerDetails;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Order\PurchaseItem;

$orders = $container->get(OrderManager::class);

// Build each item from the artwork row on the server, never from the request.
$order = $orders->createPendingOrder(
    [new PurchaseItem($artwork->artworkId, 'Headland, Dawn', $artwork->getPrice(), 'June Hollis')],
    new CustomerDetails('Avery Buyer', 'avery@example.test'),
);

$session = $orders->beginCheckout(
    $order,
    'https://www.example.com/thank-you?session_id={CHECKOUT_SESSION_ID}',
    'https://www.example.com/cart',
);
// Redirect the buyer to $session->url.

// Later, from the Stripe webhook and the thank-you page (safe to repeat):
$result = $orders->completeFromCheckoutSession($sessionId);
$result->outcome; // CompletionOutcome::Completed, AlreadyCompleted, NotPaid, RefundedRace or RefundedCancelled
```

```php
$price = Money::fromCents(185_000);
$price->gstComponent()->amount; // 16818
$price->format();               // "$1,850.00"
```

## Configuration

The only setting is the Stripe secret key, normally in an untracked local config file:

```php
return [
    'stripe' => [
        'secret_key' => 'sk_live_...',
    ],
];
```

Without a key the container still builds: `PaymentGatewayInterface` resolves to `UnconfiguredGateway`, which throws
`PaymentFailedException` as soon as money would move. See [docs/configuration.md](docs/configuration.md).

## Public API

| Class | Purpose |
| --- | --- |
| `Order\OrderManager` | Create, check out, complete, expire, pick up, collect, refund and cancel orders |
| `Order\OrderStatus` | The lifecycle and its allowed transitions |
| `Order\CompletionOutcome`, `Order\CompletionResult` | What completing a checkout session did |
| `Order\PurchaseItem`, `Order\CustomerDetails` | Inputs to `createPendingOrder()` |
| `Money\Money` | GST-inclusive AUD in integer cents |
| `Artwork\ArtworkStatus`, `Artwork\ItemType`, `Enquiry\EnquiryStatus` | Stored states and their labels |
| `Model\Entity\*Entity` | Attribute-mapped rows: artwork, order, order item, artist enquiry, enquiry file, email log |
| `Model\Repository\*Repository` | Typed finders for each entity |
| `Payment\PaymentGatewayInterface` | The payment provider: checkout sessions and refunds |
| `Payment\StripeGateway`, `Payment\UnconfiguredGateway` | The shipped gateways |
| `Payment\CheckoutRequest`, `Payment\CheckoutLineItem`, `Payment\CheckoutSession`, `Payment\RefundResult` | Gateway values |
| `Exception\*` | Every exception implements `Exception\ExceptionInterface` |
| `ConfigProvider`, `Module`, `Order\Factory\*`, `Payment\Factory\*` | Container wiring |

Every concrete class is `final`; `PaymentGatewayInterface` is the extension point. Time is always read through the
PSR-20 `ClockInterface` (`Clock\SystemClock` by default).

The [docs](docs/) folder covers each area:

- [Orders and the lifecycle](docs/orders.md)
- [Money and GST](docs/money.md)
- [Payments and Stripe](docs/payments.md)
- [Artworks](docs/artworks.md)
- [Artist enquiries and the email log](docs/enquiries.md)
- [Configuration and container](docs/configuration.md)

## Development

The QA toolchain is [php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: no I/O; Stripe through an offline HTTP client
composer test-integration  # integration suite: in-memory SQLite, real EntityManager and ServiceManager
composer test-coverage     # both suites, clover.xml for Codecov
composer mutation-test     # Infection over both suites (needs Xdebug or PCOV)
```

No test touches the network: `StripeGateway` is tested through stripe-php's pluggable HTTP client, and
`OrderManager` through a scriptable fake gateway.

## License

MIT. See [LICENSE.md](LICENSE.md).
