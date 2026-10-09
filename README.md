# contenir/contenir-commerce

[![Continuous Integration](https://github.com/contenir/contenir-commerce/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-commerce/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-commerce/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-commerce)

Formerly `contenir/commerce`; the old package is abandoned in favour of this one.

The commerce domain for [Contenir](https://github.com/contenir) sites, built on
[contenir-db-model 2](https://github.com/contenir/contenir-db-model):

- **orders** and their lifecycle (`CheckoutService`, `CompletionService`, `FulfilmentService`, and the `OrderManager`
  façade over them; `OrderStatus`): pending, paid, awaiting pickup, collected, refunded, cancelled, with every
  transition enforced;
- **items** and their **variants**: anything a site sells (an artwork, a print size, a treatment), each variant with
  its own price and stock. Availability is checked when an order is created and when checkout begins, prices are
  checked against the stored variant, and stock is claimed atomically when payment completes, so the last unit sells
  once;
- tax-inclusive **money** in integer cents (`Money`, `TaxRate`), with exact rounding at any rate;
- the transactional **email log**;
- **Stripe** hosted Checkout, session expiry and refunds behind `PaymentGatewayInterface`, with every Stripe error
  surfaced as `PaymentFailedException`;
- extensible **entities**: abstract bases a site extends with columns of its own, selected by configuration.

It ships a `ConfigProvider` (Mezzio and other PSR-11 containers) and a `Module` (laminas-mvc), and has no MVC or
Mezzio plumbing of its own. Version 2.0 is not compatible with 0.2; see [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Requirements

- PHP 8.3, 8.4 or 8.5
- contenir/contenir-db-model 2.x (on php-db/phpdb 0.6, with a platform package such as `php-db/phpdb-mysql`)
- stripe/stripe-php 22.x
- Any PSR-11 container, for the optional factories

## Install

2.0 is a release candidate (`2.0.0-RC3`): contenir-db-model 2 is itself at RC and builds on php-db/phpdb 0.6, which
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

// Build each item from the variant and item rows on the server. A price that differs from the stored variant's is
// refused with PurchaseItemMismatchException.
$order = $orders->createPendingOrder(
    [new PurchaseItem($variant->itemVariantId, $item->title, $variant->getPrice(), quantity: 2, variantLabel: 'A3')],
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

// In the CMS: cancelling a pending order also expires its Stripe session.
$orders->cancelOrder($order);
```

A class that needs only one part of the lifecycle can inject `CheckoutService`, `CompletionService` or
`FulfilmentService` instead; `OrderManager` delegates to them.

```php
$price = Money::fromCents(185_000);
$price->gstComponent()->amount; // 16818
$price->format();               // "$1,850.00"
```

## Configuration

The Stripe secret key, normally in an untracked local config file, and optional commerce settings:

```php
return [
    'stripe' => [
        'secret_key' => 'sk_live_...',
    ],
    'contenir_commerce' => [
        'order_reference_prefix' => 'ORD',  // ORD-2026-0001
        'currency'               => 'AUD',
        'tax_rate'               => 10,     // percent, included in prices
        'tax_label'              => 'GST',
        'item_entity'            => App\Entity\Artwork::class, // and the other *_entity keys
    ],
];
```

The values shown are the defaults (apart from `item_entity`); invalid ones throw `ConfigurationException`.
Without a Stripe key the container still builds: `PaymentGatewayInterface` resolves to `UnconfiguredGateway`, which
throws `PaymentFailedException` as soon as money would move. See [docs/configuration.md](docs/configuration.md) and,
for columns of your own, [docs/entities.md](docs/entities.md).

## Public API

| Class | Purpose |
| --- | --- |
| `Order\OrderManager` | Create, check out, complete, expire, pick up, collect, refund and cancel orders (a façade) |
| `Order\CheckoutService`, `Order\CompletionService`, `Order\FulfilmentService` | The same steps, split by who takes them |
| `Order\OrderStatus` | The lifecycle and its allowed transitions |
| `Order\CompletionOutcome`, `Order\CompletionResult` | What completing a checkout session did |
| `Order\PurchaseItem`, `Order\CustomerDetails` | Inputs to `createPendingOrder()` |
| `Money\Money`, `Money\TaxRate` | Tax-inclusive amounts in integer cents, and tax rates in parts per million |
| `Config\CommerceSettings` | The reference prefix, currency, tax rate and tax label |
| `Item\ItemStatus` | Whether an item is listed for sale, and its label |
| `Model\Entity\Abstract*Entity`, `Model\Entity\*Entity` | Attribute-mapped rows (item, item variant, order, order item, email log): abstract bases and their final defaults |
| `Model\Repository\*Repository` | Typed finders for each entity; `ItemVariantRepository::claim()` |
| `Payment\PaymentGatewayInterface` | The payment provider: checkout sessions, their expiry, and refunds |
| `Payment\StripeGateway`, `Payment\UnconfiguredGateway` | The shipped gateways |
| `Payment\CheckoutRequest`, `Payment\CheckoutLineItem`, `Payment\CheckoutSession`, `Payment\RefundResult` | Gateway values |
| `Exception\*` | Every exception implements `Exception\ExceptionInterface` |
| `ConfigProvider`, `Module`, `Order\Factory\*`, `Payment\Factory\*`, `Config\Factory\*`, `Model\Repository\Factory\*` | Container wiring |

Every concrete class is `final`. The extension points are `PaymentGatewayInterface` and the `Abstract*Entity`
bases. Time is always read through the
PSR-20 `ClockInterface` (`Clock\SystemClock` by default).

The [docs](docs/) folder covers each area:

- [Orders and the lifecycle](docs/orders.md)
- [Money and tax](docs/money.md)
- [Payments and Stripe](docs/payments.md)
- [Items and variants](docs/items.md)
- [The email log](docs/email-log.md)
- [Configuration and container](docs/configuration.md)
- [Entities and your own columns](docs/entities.md)

## Development

The QA toolchain is [contenir/contenir-qa-tools](https://github.com/contenir/contenir-qa-tools).
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

No test touches the network: `StripeGateway` is tested through stripe-php's pluggable HTTP client, and the order
services through a scriptable fake gateway. The concurrency tests interleave two completions over two connections to
one in-memory SQLite database.

## License

MIT. See [LICENSE.md](LICENSE.md).
