# Upgrading from 0.2 to 2.0

2.0 moves the package to contenir-db-model 2 and renames it. The 0.2 line stays available from the `0.2.x` branch
and the `v0.*` tags under the old name, `contenir/commerce`.

From 0.2 to 2.0.0-RC2 the database tables and columns are unchanged. 2.0.0-RC3 replaces the gallery's artworks with
items and variants, and renames the order tables: see below.

## From 2.0.0-RC2 to 2.0.0-RC3

RC3 makes the package sell anything, not only artworks. An **item** is what is sold; its **variants** are the
versions a buyer chooses, each with its own price and stock (see [docs/items.md](docs/items.md)). Gallery-specific
classes and columns leave the package; a gallery keeps them in its own entities.

### Classes

| RC2 | RC3 |
| --- | --- |
| `Model\Entity\AbstractArtworkEntity`, `ArtworkEntity` (`artwork`) | `AbstractItemEntity`, `ItemEntity` (`item`) and `AbstractItemVariantEntity`, `ItemVariantEntity` (`item_variant`) |
| `Model\Repository\ArtworkRepository` | `ItemRepository` and `ItemVariantRepository` |
| `ArtworkRepository::claim($artworkId, $at)` | `ItemVariantRepository::claim($itemVariantId, $quantity, $at)` |
| `findAvailableOngoing()`, `findByArtistResourceId()`, `findByExhibitionResourceId()`, `findByResourceIds()` | Removed: gallery finders belong to the site |
| `Artwork\ArtworkStatus` (`available`, `sold`) | `Item\ItemStatus` (`listed`, `unlisted`); sold is a variant's stock of 0 |
| `Artwork\ItemType` | Removed |
| `Order\ArtworkReservation`, `ArtworkReservationFactory` (internal) | `Order\ItemInventory`, `ItemInventoryFactory` (internal) |
| `Exception\ArtworkUnavailableException` | `Exception\ItemUnavailableException` |
| `PurchaseItemMismatchException::getArtworkId()` | `getItemVariantId()`; new `forLabel()` |
| `Model\Entity\*ArtistEnquiry*`, `ArtistEnquiry*Repository`, `Enquiry\EnquiryStatus` | Removed: enquiries are a site's own form submissions |
| `EmailLogEntity::$artistEnquiryId`, `EmailLogRepository::findByArtistEnquiryId()` | Removed |
| `CommerceSettings::DEFAULT_ORDER_REFERENCE_PREFIX` `LR` | `ORD`: set `order_reference_prefix` to keep your own |

### Purchase items and order lines

```php
// RC2
new PurchaseItem($artworkId, $title, $price, $artistName);

// RC3: a quantity of one variant
new PurchaseItem($variantId, $title, $unitPrice, quantity: 1, variantLabel: null, description: $artistName);
```

`PurchaseItem::$artworkId`, `$price` and `$artistName` are now `$itemVariantId`, `$unitPrice` and `$description`;
`getTotal()` multiplies by the quantity. New orders check the title against the item's `getTitle()`, which now
returns the `title` column (null skips the check, as before), and a variant label against the variant's `label`.

`AbstractOrderItemEntity` has `$itemId`, `$itemVariantId`, `$title`, `$variantLabel`, `$description`, `$unitPrice`
and `$quantity`; `getPrice()` is now `getUnitPrice()`, and `getTotal()` is new.

### Configuration

`artwork_entity` is replaced by `item_entity` and `item_variant_entity`; `artist_enquiry_entity` and
`artist_enquiry_file_entity` are gone.

### Database

Create `item` and `item_variant`, rename the order tables and reshape the order lines. An outline for MySQL, which
gives each artwork one item and one unlabelled variant with the artwork's id, so that existing ids stay valid:

```sql
CREATE TABLE item (
  item_id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title varchar(255) DEFAULT NULL,
  description text,
  status enum('listed','unlisted') NOT NULL DEFAULT 'listed',
  created datetime DEFAULT CURRENT_TIMESTAMP,
  updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE item_variant (
  item_variant_id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id int unsigned NOT NULL,
  label varchar(255) DEFAULT NULL,
  sku varchar(255) DEFAULT NULL,
  price int unsigned NOT NULL DEFAULT 0,
  stock int unsigned DEFAULT NULL,
  sequence int NOT NULL DEFAULT 0,
  created datetime DEFAULT CURRENT_TIMESTAMP,
  updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY item_id (item_id),
  FOREIGN KEY (item_id) REFERENCES item (item_id) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO item (item_id, status, created, updated)
  SELECT artwork_id, 'listed', created, updated FROM artwork;
INSERT INTO item_variant (item_variant_id, item_id, price, stock, created, updated)
  SELECT artwork_id, artwork_id, price, IF(status = 'sold', 0, 1), created, updated FROM artwork;

-- Drop the order lines' foreign key onto artwork first (its name is in SHOW CREATE TABLE).
RENAME TABLE gallery_order TO commerce_order, gallery_order_item TO commerce_order_item;
ALTER TABLE commerce_order_item
  CHANGE artwork_id item_id int unsigned DEFAULT NULL,
  ADD item_variant_id int unsigned DEFAULT NULL AFTER item_id,
  ADD variant_label varchar(255) DEFAULT NULL AFTER title,
  CHANGE artist_name description varchar(255) DEFAULT NULL,
  CHANGE price unit_price int unsigned NOT NULL DEFAULT 0,
  ADD quantity int unsigned NOT NULL DEFAULT 1 AFTER unit_price;
UPDATE commerce_order_item SET item_variant_id = item_id;
```

Move any artwork columns the site still needs (an artist, an exhibition, a medium) onto `item` and map them on a site
item entity (see [docs/entities.md](docs/entities.md)), then drop `artwork`. `email_log.artist_enquiry_id` and the
enquiry tables can stay for the site's own use; the package no longer maps them.

## From 2.0.0-RC1 to 2.0.0-RC2

Sites that get `OrderManager`, the repositories and the gateway from the container need no code change: the
defaults reproduce RC1 (reference prefix `LR`, AUD, 10% GST, the same entities and tables). The changes below matter
if you construct these classes yourself, implement `PaymentGatewayInterface`, or rely on the exact types.

### Custom payment gateways

`PaymentGatewayInterface` has a fourth method. Add it to every implementation of your own, including test doubles:

```php
public function expireCheckoutSession(string $sessionId): CheckoutSession
{
    // Expire the session at the provider so it can no longer be paid, and return it.
    // A session that is already complete or expired: return it as it is, without an error.
    // Anything else that fails: throw PaymentFailedException.
}
```

`FulfilmentService::cancelOrder()` (and `OrderManager::cancelOrder()`) calls it for a pending order whose checkout has
begun. A gateway that cannot expire sessions may return the session from `retrieveCheckoutSession()`: the order is
still cancelled, and a payment through it is refunded when it completes.

### Order services

`OrderManager` keeps every public method and now delegates to `CheckoutService`, `CompletionService` and
`FulfilmentService`. If you construct it yourself:

```php
// RC1
new OrderManager($em, $orders, $orderItems, $artworks, $gateway, $clock);

// RC2: take the services from the container ...
$container->get(OrderManager::class);
$container->get(CompletionService::class); // or inject just the part you need

// ... or build them; OrderStore, ArtworkReservation, PurchaseItemCheck and Refunder are internal helpers.
$store       = new OrderStore($em, $orders, $orderItems, $clock);
$reservation = new ArtworkReservation($artworks);
$refunder    = new Refunder($gateway);
new OrderManager(
    new CheckoutService($store, $reservation, new PurchaseItemCheck(), $gateway, new CommerceSettings()),
    new CompletionService($store, $reservation, $refunder, $gateway),
    new FulfilmentService($store, $refunder, $gateway),
);
```

### Repositories

The repositories are built by `Contenir\Commerce\Model\Repository\Factory\RepositoryFactory`. If you registered
them yourself with contenir-db-model's `Container\RepositoryFactory`, switch to the package's factory (or use the
`ConfigProvider`/`Module` registrations): `ArtworkRepository` now needs the database adapter for its atomic claim.

```php
// RC1
new ArtworkRepository($em);

// RC2: the adapter the EntityManager runs on, and its TypeRegistry
new ArtworkRepository($em, $adapter, $container->get(TypeRegistry::class));
```

Each repository takes the entity class as an optional last argument, and its finders are typed against the abstract
base (`?AbstractOrderEntity`, `list<AbstractArtworkEntity>`, ...). So are `CompletionResult::$order` and the
`OrderManager` methods. The objects are still the default `OrderEntity`, `ArtworkEntity`, ... unless you configure
your own, so only static analysis notices; widen your own type declarations to the abstract bases.

### Orders

- `createPendingOrder()` compares each `PurchaseItem`'s price with the artwork's stored price and throws
  `PurchaseItemMismatchException` when they differ. Build items from the artwork row (as the README always advised);
  a cart that holds prices across a price change must be rebuilt. Catch the exception to tell the buyer the price
  has changed.
- `cancelOrder()` on a pending order whose checkout has begun calls Stripe to expire the session, and can now throw
  `PaymentFailedException` (the order then stays pending; try again).
- Two payments for one work completing at once: the second is now always refunded (`RefundedRace`), where RC1 could
  sell the work twice.

### Money

`gstComponent()` is unchanged without an argument. Pass a `TaxRate` for another rate:
`$total->gstComponent(TaxRate::fromPercent(15))`, or the configured `CommerceSettings::$taxRate`.

### Configuration

New, all optional, under `contenir_commerce`:

| Key | Default | Meaning |
| --- | --- | --- |
| `order_reference_prefix` | `LR` | Order references read `<prefix>-<year>-<id, 4 digits>` |
| `currency` | `AUD` | The ISO 4217 code Stripe charges in (sent in lower case) |
| `tax_rate` | `10` | The percentage of tax included in prices, 0 to 100 |
| `tax_label` | `GST` | The tax's display name, for templates (`CommerceSettings::$taxLabel`) |
| `artwork_entity`, `order_entity`, `order_item_entity`, `artist_enquiry_entity`, `artist_enquiry_file_entity`, `email_log_entity` | the shipped entities | The class each repository hydrates; must extend the matching `Abstract*Entity` |

See [docs/configuration.md](docs/configuration.md) and [docs/entities.md](docs/entities.md).

## From 0.2 to 2.0

### Requirements

| | 0.2 | 2.0 |
| --- | --- | --- |
| Package | `contenir/commerce` | `contenir/contenir-commerce` (declares `replace` for the old name) |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |
| contenir/contenir-db-model | ^1.0 (laminas-db) | ^2.0@RC (php-db/phpdb 0.6) |
| stripe/stripe-php | ^17.0 | ^22.0 |
| psr/clock, psr/container | ^1.0, ^1.1 \|\| ^2.0 | unchanged |

### Checklist

1. Change the requirement:

   ```diff
   -"contenir/commerce": "^0.2",
   +"contenir/contenir-commerce": "^2.0@RC",
   +"contenir/contenir-db-model": "^2.0@RC",
   +"php-db/phpdb": "0.6.x-dev@dev",
   ```

   (or `"minimum-stability": "dev"` with `"prefer-stable": true`). Upgrade contenir-db-model as its own
   [UPGRADE-2.0.md](https://github.com/contenir/contenir-db-model/blob/main/UPGRADE-2.0.md) describes, including the
   `model` → `contenir_db_model` config key.
2. Replace magic snake_case properties with the typed camelCase ones (tables below), and string statuses with the
   enums.
3. Replace `$repository->create([...])` with `new XxxEntity()` and property assignments, and
   `$repository->save($entity)` with `$entityManager->save($entity)`.
4. Replace `find($where)`/`findOne($where)` with `findBy()`/`findOneBy()` keyed by property name, or the typed
   finders.
5. If you construct `OrderManager` yourself, build it from the three order services (see "Order services" above).
   If you implement `PaymentGatewayInterface`, add the `$idempotencyKey` parameter to `refund()` and the
   `expireCheckoutSession()` method.
6. Handle `checkout.session.async_payment_succeeded` like `checkout.session.completed`, and
   `checkout.session.async_payment_failed` like `checkout.session.expired` (see Behaviour changes).
7. Give each checkout attempt its own pending order (`createPendingOrder()` then `beginCheckout()`); do not call
   `beginCheckout()` twice for one order.
8. Check any `match` over `CompletionOutcome` for the new `RefundedCancelled` case.

### Classes

Every concrete class is `final` in 2.0 (entities and repositories were open in 0.2).

| 0.2 | 2.0 |
| --- | --- |
| `Artwork\ArtworkStatus`, `Artwork\ItemType`, `Enquiry\EnquiryStatus` | Unchanged |
| `Clock\SystemClock` | Unchanged |
| `ConfigProvider` | `__invoke()` returns `['dependencies' => ...]` (was `service_manager`); `getDependencyConfig()` is `getDependencies()` |
| `Module` | Unchanged for laminas-mvc: `getConfig()` returns the services under `service_manager` |
| `Exception\ArtworkUnavailableException` | Private constructor; use `forTitles()`. Implements `ExceptionInterface` |
| `Exception\InvalidTransitionException` | Adds `checkoutAlreadyStarted()` and `checkoutNotPending()`. Implements `ExceptionInterface` |
| `Exception\PaymentFailedException` | Adds `fromProvider()`, `nothingToRefund()` and `notConfigured()`. Implements `ExceptionInterface` |
| (none) | `Exception\ExceptionInterface`, `InvalidArgumentException`, `OverflowException`, `OrderNotFoundException`, `ConfigurationException` |
| `Model\Entity\*Entity` (extend `AbstractEntity`) | Final default classes carrying `#[Table]`, extending `Model\Entity\Abstract*Entity`, which map the columns with `#[Id]`, `#[Column]`, `#[HasMany]` |
| `Model\Repository\*Repository` (extend `AbstractRepository`) | Final, extend `Contenir\Db\Model\Repository`, constructor `(EntityManager $em, string $entityClass = <default>)`; `ArtworkRepository` `(EntityManager, AdapterInterface, TypeRegistry, string $entityClass = ArtworkEntity::class)` |
| `Money\Money` | `final readonly`; adds `zero()`; integer GST, `gstComponent(?TaxRate)`; `OverflowException` on overflow |
| `Order\CompletionOutcome` | Adds `RefundedCancelled` |
| `Order\CompletionResult`, `Order\CustomerDetails`, `Order\PurchaseItem` | `readonly` classes; same constructors |
| `Order\Factory\OrderManagerFactory` | Builds the façade from the three order services |
| `Order\OrderManager` | A façade with the same methods; constructor `(CheckoutService, CompletionService, FulfilmentService)` |
| `Order\OrderStatus` | Unchanged |
| `Payment\CheckoutLineItem` | `readonly`; same constructor |
| `Payment\CheckoutRequest` | `readonly`; adds `MIN_EXPIRY_MINUTES`/`MAX_EXPIRY_MINUTES`; rejects over 1,440 minutes |
| `Payment\CheckoutSession` | `readonly`; adds a sixth constructor argument `?string $paymentStatus`, `isPaid()` and the `STATUS_COMPLETE`/`PAYMENT_STATUS_PAID` constants |
| `Payment\Factory\StripeGatewayFactory` | Takes the clock from `ClockInterface` in the container; rejects a mistyped `stripe` config |
| `Payment\PaymentGatewayInterface` | `refund(string $paymentIntentId, ?Money $amount = null, ?string $idempotencyKey = null)`; adds `expireCheckoutSession(string $sessionId): CheckoutSession` |
| `Payment\RefundResult` | `readonly` |
| `Payment\StripeGateway` | `readonly`; wraps every stripe-php exception; sends idempotency keys; optional third constructor argument, the currency |
| `Payment\UnconfiguredGateway` | New `refund()` signature; adds `expireCheckoutSession()` |
| (none) | `Order\CheckoutService`, `Order\CompletionService`, `Order\FulfilmentService` and their factories; `Config\CommerceSettings` and its factory; `Money\TaxRate`; `Model\Repository\Factory\RepositoryFactory`; `Exception\PurchaseItemMismatchException` |
| (none) | `Container\ServiceLocator`, `Config\ConfigReader`, `Order\OrderStore`, `Order\ArtworkReservation`, `Order\PurchaseItemCheck`, `Order\Refunder` (`@internal`) |

#### Exceptions

| Thrown when | 0.2 | 2.0 |
| --- | --- | --- |
| Invalid money, quantity, customer, line item, checkout request, empty order | `InvalidArgumentException` (SPL) | `Contenir\Commerce\Exception\InvalidArgumentException` (extends the SPL one) |
| No order for a checkout session | `RuntimeException` | `OrderNotFoundException` (extends `RuntimeException`) |
| Refund of an order without a payment | `RuntimeException` | `PaymentFailedException` (extends `RuntimeException`) |
| A stripe-php error that is not an `ApiErrorException` | the stripe-php exception | `PaymentFailedException` |
| Money overflow | `TypeError` | `OverflowException` (extends the SPL one) |

Existing `catch (\InvalidArgumentException)` and `catch (\RuntimeException)` blocks keep working.

### Configuration

| 0.2 | 2.0 |
| --- | --- |
| `stripe.secret_key` | Unchanged. Must be a string; a non-array `stripe` or a non-string key throws `ConfigurationException` |
| `service_manager` from `ConfigProvider` | `dependencies` from `ConfigProvider`; `service_manager` from `Module` |
| `service_manager.factories` for the six entities (`InvokableFactory`) | Removed: entities are not services |
| `service_manager.factories` for the repositories (`Contenir\Db\Model\Repository\Factory\RepositoryFactory`) | `Contenir\Commerce\Model\Repository\Factory\RepositoryFactory` |
| (none) | `contenir_commerce`: the reference prefix, currency, tax and entity classes (see above) |
| `ClockInterface` alias to `SystemClock` (`InvokableFactory`) | Same alias; `SystemClock` is an invokable |
| `model.adapter` (contenir-db-model 1) | `contenir_db_model.adapter` (contenir-db-model 2) |

### Repository methods

Every repository:

| 0.2 | 2.0 |
| --- | --- |
| `create(iterable $data)` | `new XxxEntity()` and assign properties |
| `findOne($where, $order, $select)` | `find($id)`, `findOneBy(array $criteria, array $orderBy)` |
| `find($where, $order, $select)` | `findBy(array $criteria, array $orderBy, ?int $limit, ?int $offset)` |
| `save($entity)` | `EntityManager::save($entity)` |
| `delete($where)` | `EntityManager::delete($entity)` |
| `getTable()` | The entity's `#[Table]` |

Criteria and ordering use **property** names: `['artwork_id' => 1]` becomes `['artworkId' => 1]`, and
`['status' => 'available']` may stay a string or become `ArtworkStatus::Available`.

| Repository | 0.2 | 2.0 |
| --- | --- | --- |
| `ArtworkRepository` | `findByResourceIds(list<int>)` | Unchanged |
| | `findByExhibitionResourceId(int)` | Unchanged |
| | `findByArtistResourceId(int)` | Unchanged |
| | `findAvailableOngoing()` | Unchanged |
| | `findOne(['artwork_id' => $id])` | `find($id)`, or `findCurrent($id)` to bypass the identity map |
| `OrderRepository` | `findOne(['stripe_checkout_session_id' => $id])` | `findOneByCheckoutSessionId($id)` |
| | `findOne(['order_ref' => $ref])` | `findOneByOrderRef($ref)` |
| | `find(['status' => $status])` | `findByStatus(OrderStatus)`, newest first |
| `OrderItemRepository` | `find(['order_id' => $id])` | `findByOrderId($id)`, in the order added; or `$order->items` |
| `ArtistEnquiryRepository` | `find(['status' => $status])` | `findByStatus(EnquiryStatus)`, newest first |
| `ArtistEnquiryFileRepository` | `find(['artist_enquiry_id' => $id])` | `findByArtistEnquiryId($id)`, in upload order; or `$enquiry->files` |
| `EmailLogRepository` | `find(['order_id' => $id])` | `findByOrderId($id)`, newest first |
| | `find(['artist_enquiry_id' => $id])` | `findByArtistEnquiryId($id)`, newest first |

### OrderManager methods

| 0.2 | 2.0 |
| --- | --- |
| `createPendingOrder(list<PurchaseItem>, CustomerDetails)` | Same; one transaction; rejects a work listed twice and an item whose price differs from the artwork's |
| `beginCheckout(OrderEntity, string, string)` | Same; refuses an order that is not pending or already has a session; re-checks availability |
| `completeFromCheckoutSession(string)` | Same; fulfils only paid sessions; refunds payments for cancelled orders |
| `expireCheckout(string)` | Unchanged |
| `markAwaitingPickup(OrderEntity)`, `markCollected(OrderEntity)` | Unchanged |
| `cancelOrder(OrderEntity)` | Same; expires the checkout session of a pending order first |
| `refundOrder(OrderEntity, ?Money)` | Unchanged; the no-payment error is `PaymentFailedException` |
| `purchaseItemsFor(OrderEntity)` | Unchanged |

### Entities

Properties are typed, camelCase and mapped to the same snake_case columns. Unlisted columns keep their name
(`price` → `$price`). `created`, `updated` and every `*_at` column are `?DateTimeImmutable` (0.2: `?string`).

#### `ArtworkEntity` (`artwork`)

| 0.2 | 2.0 |
| --- | --- |
| `artwork_id` | `artworkId` (`?int`) |
| `resource_id`, `artist_resource_id`, `exhibition_resource_id` | `resourceId`, `artistResourceId`, `exhibitionResourceId` (`?int`) |
| `item_type` (`string`) | `itemType` (`ItemType`) |
| `price` | `price` (`int`), also `getPrice(): Money` |
| `status` (`string`) | `status` (`ArtworkStatus`), also `isAvailable()` |
| `medium`, `dimensions`, `year` | same names (`?string`) |
| `edition_details`, `external_sale_url` | `editionDetails`, `externalSaleUrl` |

#### `OrderEntity` (`gallery_order`)

| 0.2 | 2.0 |
| --- | --- |
| `order_id` | `orderId` (`?int`), also `getId(): int` |
| `order_ref` | `orderRef` (`string`) |
| `customer_name`, `customer_email`, `customer_phone` | `customerName`, `customerEmail`, `customerPhone` |
| `status` (`string`) | `status` (`OrderStatus`) |
| `total`, `gst_amount` | `total`, `gstAmount` (`int`), also `getTotal()`/`getGstAmount(): Money` |
| `stripe_checkout_session_id`, `stripe_payment_intent_id` | `stripeCheckoutSessionId`, `stripePaymentIntentId` |
| `customer_notes`, `staff_notes` | `customerNotes`, `staffNotes` |
| `paid_at`, `collected_at`, `refunded_at`, `cancelled_at` | `paidAt`, `collectedAt`, `refundedAt`, `cancelledAt` |
| `items` (array) | `items` (`Collection<OrderItemEntity>`, ordered by id) |

#### `OrderItemEntity` (`gallery_order_item`)

| 0.2 | 2.0 |
| --- | --- |
| `order_item_id`, `order_id`, `artwork_id` | `orderItemId`, `orderId` (`int`), `artworkId` (`?int`) |
| `title`, `price` | same names; also `getPrice(): Money` |
| `artist_name` | `artistName` |

#### `ArtistEnquiryEntity` (`artist_enquiry`)

| 0.2 | 2.0 |
| --- | --- |
| `artist_enquiry_id` | `artistEnquiryId` |
| `name`, `email`, `telephone`, `website`, `instagram`, `bio`, `statement`, `medium` | same names |
| `preferred_timing`, `how_heard`, `staff_notes` | `preferredTiming`, `howHeard`, `staffNotes` |
| `status` (`string`) | `status` (`EnquiryStatus`) |
| `files` (array) | `files` (`Collection<ArtistEnquiryFileEntity>`, in upload order) |

#### `ArtistEnquiryFileEntity` (`artist_enquiry_file`)

| 0.2 | 2.0 |
| --- | --- |
| `artist_enquiry_file_id`, `artist_enquiry_id` | `artistEnquiryFileId`, `artistEnquiryId` |
| `filename`, `path`, `size` | same names |
| `mime_type` | `mimeType` |

#### `EmailLogEntity` (`email_log`)

| 0.2 | 2.0 |
| --- | --- |
| `email_log_id`, `order_id`, `artist_enquiry_id` | `emailLogId`, `orderId`, `artistEnquiryId` |
| `recipient`, `subject`, `status`, `error` | same names |
| `message_class` | `messageClass` |

#### Before and after

```php
// 0.2
$log = $this->emailLog->create([
    'order_id'  => (int) $order->order_id,
    'recipient' => $recipient,
    'subject'   => $subject,
    'status'    => 'sent',
]);
$this->emailLog->save($log);

if ($order->status === 'paid') { /* ... */ }

// 2.0
$log            = new EmailLogEntity();
$log->orderId   = $order->getId();
$log->recipient = $recipient;
$log->subject   = $subject;
$log->status    = 'sent';
$log->created   = $clock->now();
$this->entityManager->save($log);

if ($order->status === OrderStatus::Paid) { /* ... */ }
```

### Behaviour changes

- **Delayed payments.** 0.2 fulfilled a session as soon as it was `complete`. 2.0 waits for Stripe's
  `payment_status` to be `paid`: a BECS Direct Debit payment reports `NotPaid` on `checkout.session.completed` and
  completes on `checkout.session.async_payment_succeeded`.
- **One checkout per order.** `beginCheckout()` throws `InvalidTransitionException` for an order that is not
  pending or already has a checkout session, and re-checks availability (`ArtworkUnavailableException`).
- **Cancelled orders.** A payment that arrives for a cancelled order is refunded in full; the outcome is
  `RefundedCancelled` (0.2: `AlreadyCompleted`, no refund). A cancelled order whose session is not paid reports
  `NotPaid`.
- **Transactions.** Creating an order and completing one each write in a single transaction.
- **Duplicate works.** An order listing the same artwork twice is rejected.
- **Server-side prices.** An item whose price differs from its artwork's is rejected
  (`PurchaseItemMismatchException`).
- **Concurrent payments.** Each work is claimed with a conditional update at completion, so two payments for one
  work completing at once sell it once and refund the other.
- **Identity map.** contenir-db-model 2 returns one object per row per `EntityManager`. Availability checks use
  `ArtworkRepository::findCurrent()`, which re-reads the row; in long-running workers, `clear()` the manager between
  jobs as contenir-db-model recommends.
- **Stripe API version.** stripe-php 22 pins `2026-09-30.endive` (17.x pinned a `basil` version). The fields this
  package uses are unchanged, but review Stripe's changelog for any other Stripe calls your site makes.
