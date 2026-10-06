# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0-RC2] - Unreleased

Follow-ups to RC1. See "From 2.0.0-RC1 to 2.0.0-RC2" in [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Added

- `Order\CheckoutService` (`createPendingOrder()`, `beginCheckout()`, `purchaseItemsFor()`),
  `Order\CompletionService` (`completeFromCheckoutSession()`, `expireCheckout()`) and `Order\FulfilmentService`
  (`cancelOrder()`, `markAwaitingPickup()`, `markCollected()`, `refundOrder()`), each with a factory and registered in
  the container. Inject one directly when a class needs only its part of the lifecycle.
- `PaymentGatewayInterface::expireCheckoutSession()`, implemented by `StripeGateway` (Stripe's
  `checkout.sessions.expire`) and `UnconfiguredGateway`. `CheckoutSession::isOpen()` and the `STATUS_OPEN` and
  `STATUS_EXPIRED` constants.
- `ArtworkRepository::claim()`: marks a work sold only if it is still available, in one conditional `UPDATE`.
- `Config\CommerceSettings` and `Config\Factory\CommerceSettingsFactory`, from the new `contenir_commerce` config
  key: `order_reference_prefix` (default `LR`), `currency` (`AUD`), `tax_rate` (`10`) and `tax_label` (`GST`).
- `Money\TaxRate`, a rate in integer parts per million; `Money::gstComponent()` takes an optional `TaxRate`.
- `Abstract*Entity` base classes for the six entities, with the shipped entities as their final defaults, and the
  `contenir_commerce` keys `artwork_entity`, `order_entity`, `order_item_entity`, `artist_enquiry_entity`,
  `artist_enquiry_file_entity` and `email_log_entity` naming the class each repository hydrates.
  `AbstractArtworkEntity::getTitle()` is a hook for sites that map a title column. Every repository gains
  `newEntity()`.
- `Model\Repository\Factory\RepositoryFactory`, which builds the repositories with the configured entity classes.
- `Exception\PurchaseItemMismatchException`, and `ConfigurationException::invalidEntityClass()`, `invalidSetting()`
  and `unknownRepository()`.

### Changed

- **Custom payment gateways:** `PaymentGatewayInterface` gains `expireCheckoutSession(string $sessionId):
  CheckoutSession`. Implementations outside this package must add it (see UPGRADE-2.0.md).
- `OrderManager` is a façade over the three services, with the same public methods. Its constructor takes
  `(CheckoutService, CompletionService, FulfilmentService)`. The container builds it unchanged.
- `cancelOrder()` expires the Stripe checkout session of a pending order whose checkout has begun, before cancelling
  it. A session that is already complete or expired is left as it is, and the order is still cancelled; a session
  paid before the cancellation is refunded when it completes (`RefundedCancelled`), as in RC1. When Stripe cannot be
  reached, `PaymentFailedException` propagates and the order stays pending.
- `createPendingOrder()` checks each item's price, and its title when the artwork entity maps one, against the
  artwork as stored, and throws `PurchaseItemMismatchException` on a difference.
- The order reference prefix, Stripe currency, tax rate and tax label come from `CommerceSettings`. The defaults
  reproduce RC1.
- Repositories take the entity class to hydrate as an optional last constructor argument, and return
  `Abstract*Entity` types. `ArtworkRepository` also takes the database adapter and contenir-db-model's
  `TypeRegistry`: `(EntityManager, AdapterInterface, TypeRegistry, string $entityClass = ArtworkEntity::class)`.
  `CompletionResult::$order` and the `OrderManager` methods are typed against `AbstractOrderEntity`.
- The repositories are built by `Model\Repository\Factory\RepositoryFactory` instead of contenir-db-model's
  `Container\RepositoryFactory`.
- `StripeGateway` takes the currency as an optional third constructor argument (`AUD`); `StripeGatewayFactory`
  passes the configured one.
- `OrderManager`'s `too-many-methods`, `cyclomatic-complexity`, `kan-defect` and `excessive-parameter-list` Mago
  expectations are gone.

### Fixed

- Two payments for the same work completing at the same instant could both pass the availability check and both
  sell it. Completion now claims each work with `UPDATE artwork SET status = 'sold' ... WHERE artwork_id = ? AND
  status = 'available'` inside the completion transaction and checks the affected rows, on SQLite, MySQL and
  PostgreSQL alike. When a claim fails the claims already made are rolled back and the payment is refunded in full
  (`RefundedRace`, idempotent).
- When the webhook and the thank-you page completed the same order at once, the second could find the work already
  claimed (by its own order) and refund the winning payment. The race-refund path now re-reads the order and reports
  `AlreadyCompleted` when it has already been settled.
- `createPendingOrder()` trusted the caller's prices. A cart built from stale or tampered data is now refused.
- A pending order cancelled by staff left its checkout session open, so the buyer could still pay for it until the
  session expired (the payment was then refunded). The session is now expired on cancellation.

## [2.0.0-RC1] - 2026-10-06

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

[2.0.0-RC2]: https://github.com/contenir/contenir-commerce/compare/v2.0.0-RC1...main
[2.0.0-RC1]: https://github.com/contenir/contenir-commerce/compare/v0.2.1...v2.0.0-RC1
[0.2.1]: https://github.com/contenir/contenir-commerce/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/contenir/contenir-commerce/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/contenir/contenir-commerce/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/contenir/contenir-commerce/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/contenir/contenir-commerce/releases/tag/v0.1.0
