# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0-RC1] - Unreleased

The port to contenir-db-model 2, under the new package name. See [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Changed

- Renamed from `contenir/commerce` to `contenir/contenir-commerce`. The package declares `replace` for the old name;
  require `contenir/contenir-commerce` instead. The namespace stays `Contenir\Commerce`.
- Requires PHP 8.3, 8.4 or 8.5, contenir/contenir-db-model 2 (php-db/phpdb 0.6) and stripe/stripe-php 22 (was 17;
  the pinned Stripe API version moves from a `basil` version, `2025-03-31` to `2025-08-27`, to `2026-09-30.endive`).
- Entities are attribute-mapped final classes with typed camelCase properties; statuses are enums, timestamps
  `DateTimeImmutable`, to-many relations `Collection`s. The tables and columns are unchanged: no migration.
- Repositories are final, extend `Contenir\Db\Model\Repository` and take the `EntityManager`. `create()`,
  `findOne()`, `save()` and `getTable()` are gone: build entities with `new`, find with `find()`/`findBy()`/
  `findOneBy()` or the typed finders, and save with `EntityManager::save()`.
- `OrderManager` takes the `EntityManager` as its first argument. `createPendingOrder()` and completion each write in
  one transaction.
- `ConfigProvider::__invoke()` returns `dependencies` (was `service_manager`); `Module::getConfig()` returns the same
  services under `service_manager`. `getDependencyConfig()` is now `getDependencies()`. Entities are no longer
  registered as services; repositories use contenir-db-model's `Container\RepositoryFactory`.
- `PaymentGatewayInterface::refund()` takes an optional `$idempotencyKey`, sent to Stripe as `Idempotency-Key`.
- `CheckoutSession` carries `paymentStatus`; `isPaid()` is true only when the session is complete and paid.
- `beginCheckout()` re-checks availability and refuses an order that is not pending or has already begun checkout.
- `CheckoutRequest` rejects an expiry over 1,440 minutes (Stripe's maximum) as well as under 30.
- `Money` arithmetic is integer only: GST is computed with `intdiv()` and `format()` never goes through a float.
  Sums and products beyond the integer range throw `OverflowException`.
- `StripeGatewayFactory` reads the clock from the container's `ClockInterface` instead of constructing its own, and
  rejects a mistyped `stripe` config with `ConfigurationException`.
- Value objects (`Money`, `CheckoutRequest`, `CheckoutSession`, `PurchaseItem`, ...) are `readonly` classes. Every
  exception implements `Exception\ExceptionInterface`; invalid arguments throw the package's
  `InvalidArgumentException` (a subclass of the SPL one), an unknown checkout session `OrderNotFoundException` and a
  refund without a payment `PaymentFailedException` (both still `RuntimeException`s).
- Mago replaces phpcs and PHPStan; PHPUnit unit and integration suites, Infection (MSI 100%) and Codecov in CI.

### Added

- `OrderRepository::findOneByCheckoutSessionId()`, `findOneByOrderRef()` and `findByStatus()`;
  `OrderItemRepository::findByOrderId()`; `ArtistEnquiryRepository::findByStatus()`;
  `ArtistEnquiryFileRepository::findByArtistEnquiryId()`; `EmailLogRepository::findByOrderId()` and
  `findByArtistEnquiryId()`; `ArtworkRepository::findCurrent()`.
- `ArtworkEntity::isAvailable()` and `getPrice()`, `OrderEntity::getId()`, `getTotal()` and `getGstAmount()`,
  `OrderItemEntity::getPrice()`, `Money::zero()`.
- `CompletionOutcome::RefundedCancelled`.
- `Exception\ExceptionInterface`, `InvalidArgumentException`, `OverflowException`, `OrderNotFoundException` and
  `ConfigurationException`.

### Fixes

- A checkout session that was complete but not yet paid was fulfilled. With a delayed payment method (BECS Direct
  Debit, for example) the order was marked paid and the works sold before the funds arrived. Completion now requires
  Stripe's `payment_status` to be `paid` and reports `NotPaid` until then; call it again on
  `checkout.session.async_payment_succeeded`.
- Beginning checkout twice for one order overwrote the first session's id, so a payment through the first session
  matched no order: the money was taken and the order never completed. Checkout could also begin for an order that
  was already paid or cancelled. Both are now refused with `InvalidTransitionException`.
- A payment for an order cancelled while the buyer was paying was kept and reported as `AlreadyCompleted`. It is now
  refunded in full (`RefundedCancelled`) and the order stays cancelled.
- Completion saved the paid order and then each sold work separately, so a failure part-way left a paid order whose
  works were still for sale. It is now one transaction. `createPendingOrder()` likewise could leave an order without
  its lines.
- A webhook retry after a race refund succeeded but before the order was saved refunded again; Stripe refused the
  second refund and the order stayed pending. Race refunds now carry an idempotency key and run inside the
  completion transaction.
- A race with no payment intent on the session marked the order refunded without refunding anything. It now throws
  `PaymentFailedException`.
- stripe-php errors other than `ApiErrorException` (its `InvalidArgumentException` for a blank session id, for
  example) escaped `StripeGateway` unwrapped. Every stripe-php exception now becomes `PaymentFailedException`.
- The same work could appear twice in one order and be charged twice. `createPendingOrder()` now rejects it.
- `ArtworkUnavailableException::getTitles()` threw an `Error` on an exception built with `new`. The constructor is
  now private; use `forTitles()`.

### Removed

- The magic-property entities of contenir-db-model 1 (`getArrayCopy()`, array constructors, `$columns`).
- Entity registrations in the container.
- phpcs, PHPStan and their configuration.

## [0.2.1] - 2026-08-20

### Changed

- Fail lazily when Stripe is unconfigured: `UnconfiguredGateway` stands in when there is no secret key.

## [0.2.0] - 2026-08-20

### Added

- `OrderManager`: shared order lifecycle orchestration for the public site and the CMS.

## [0.1.2] - 2026-08-20

### Added

- Artwork finders for gallery listings.

## [0.1.1] - 2026-08-20

### Added

- A laminas-mvc `Module` entry point.

## [0.1.0] - 2026-08-20

### Added

- The commerce domain: money, the order lifecycle, Stripe checkout and the entities.

[2.0.0-RC1]: https://github.com/contenir/contenir-commerce/compare/v0.2.1...main
[0.2.1]: https://github.com/contenir/contenir-commerce/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/contenir/contenir-commerce/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/contenir/contenir-commerce/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/contenir/contenir-commerce/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/contenir/contenir-commerce/releases/tag/v0.1.0
