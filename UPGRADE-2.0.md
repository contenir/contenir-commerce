# Upgrading from 0.2 to 2.0

2.0 moves the package to contenir-db-model 2 and renames it. The 0.2 line stays available from the `0.2.x` branch
and the `v0.*` tags under the old name, `contenir/commerce`.

The database tables and columns are unchanged: no migration is needed.

## Requirements

| | 0.2 | 2.0 |
| --- | --- | --- |
| Package | `contenir/commerce` | `contenir/contenir-commerce` (declares `replace` for the old name) |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |
| contenir/contenir-db-model | ^1.0 (laminas-db) | ^2.0@RC (php-db/phpdb 0.6) |
| stripe/stripe-php | ^17.0 | ^22.0 |
| psr/clock, psr/container | ^1.0, ^1.1 \|\| ^2.0 | unchanged |

## Checklist

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
5. If you construct `OrderManager` yourself, pass the `EntityManager` first. If you implement
   `PaymentGatewayInterface`, add the `$idempotencyKey` parameter to `refund()`.
6. Handle `checkout.session.async_payment_succeeded` like `checkout.session.completed`, and
   `checkout.session.async_payment_failed` like `checkout.session.expired` (see Behaviour changes).
7. Give each checkout attempt its own pending order (`createPendingOrder()` then `beginCheckout()`); do not call
   `beginCheckout()` twice for one order.
8. Check any `match` over `CompletionOutcome` for the new `RefundedCancelled` case.

## Classes

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
| `Model\Entity\*Entity` (extend `AbstractEntity`) | Plain final classes mapped with `#[Table]`, `#[Id]`, `#[Column]`, `#[HasMany]` |
| `Model\Repository\*Repository` (extend `AbstractRepository`) | Final, extend `Contenir\Db\Model\Repository`, constructor `(EntityManager $em)` |
| `Money\Money` | `final readonly`; adds `zero()`; integer GST; `OverflowException` on overflow |
| `Order\CompletionOutcome` | Adds `RefundedCancelled` |
| `Order\CompletionResult`, `Order\CustomerDetails`, `Order\PurchaseItem` | `readonly` classes; same constructors |
| `Order\Factory\OrderManagerFactory` | Also resolves the `EntityManager` |
| `Order\OrderManager` | Constructor `(EntityManager, OrderRepository, OrderItemRepository, ArtworkRepository, PaymentGatewayInterface, ClockInterface)` (0.2 had no `EntityManager`) |
| `Order\OrderStatus` | Unchanged |
| `Payment\CheckoutLineItem` | `readonly`; same constructor |
| `Payment\CheckoutRequest` | `readonly`; adds `MIN_EXPIRY_MINUTES`/`MAX_EXPIRY_MINUTES`; rejects over 1,440 minutes |
| `Payment\CheckoutSession` | `readonly`; adds a sixth constructor argument `?string $paymentStatus`, `isPaid()` and the `STATUS_COMPLETE`/`PAYMENT_STATUS_PAID` constants |
| `Payment\Factory\StripeGatewayFactory` | Takes the clock from `ClockInterface` in the container; rejects a mistyped `stripe` config |
| `Payment\PaymentGatewayInterface` | `refund(string $paymentIntentId, ?Money $amount = null, ?string $idempotencyKey = null)` |
| `Payment\RefundResult` | `readonly` |
| `Payment\StripeGateway` | `readonly`; wraps every stripe-php exception; sends idempotency keys |
| `Payment\UnconfiguredGateway` | New `refund()` signature |
| (none) | `Container\ServiceLocator` (`@internal`) |

### Exceptions

| Thrown when | 0.2 | 2.0 |
| --- | --- | --- |
| Invalid money, quantity, customer, line item, checkout request, empty order | `InvalidArgumentException` (SPL) | `Contenir\Commerce\Exception\InvalidArgumentException` (extends the SPL one) |
| No order for a checkout session | `RuntimeException` | `OrderNotFoundException` (extends `RuntimeException`) |
| Refund of an order without a payment | `RuntimeException` | `PaymentFailedException` (extends `RuntimeException`) |
| A stripe-php error that is not an `ApiErrorException` | the stripe-php exception | `PaymentFailedException` |
| Money overflow | `TypeError` | `OverflowException` (extends the SPL one) |

Existing `catch (\InvalidArgumentException)` and `catch (\RuntimeException)` blocks keep working.

## Configuration

| 0.2 | 2.0 |
| --- | --- |
| `stripe.secret_key` | Unchanged. Must be a string; a non-array `stripe` or a non-string key throws `ConfigurationException` |
| `service_manager` from `ConfigProvider` | `dependencies` from `ConfigProvider`; `service_manager` from `Module` |
| `service_manager.factories` for the six entities (`InvokableFactory`) | Removed: entities are not services |
| `service_manager.factories` for the repositories (`Contenir\Db\Model\Repository\Factory\RepositoryFactory`) | `Contenir\Db\Model\Container\RepositoryFactory` |
| `ClockInterface` alias to `SystemClock` (`InvokableFactory`) | Same alias; `SystemClock` is an invokable |
| `model.adapter` (contenir-db-model 1) | `contenir_db_model.adapter` (contenir-db-model 2) |

## Repository methods

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

## OrderManager methods

| 0.2 | 2.0 |
| --- | --- |
| `createPendingOrder(list<PurchaseItem>, CustomerDetails)` | Same; one transaction; rejects a work listed twice |
| `beginCheckout(OrderEntity, string, string)` | Same; refuses an order that is not pending or already has a session; re-checks availability |
| `completeFromCheckoutSession(string)` | Same; fulfils only paid sessions; refunds payments for cancelled orders |
| `expireCheckout(string)` | Unchanged |
| `markAwaitingPickup(OrderEntity)`, `markCollected(OrderEntity)`, `cancelOrder(OrderEntity)` | Unchanged |
| `refundOrder(OrderEntity, ?Money)` | Unchanged; the no-payment error is `PaymentFailedException` |
| `purchaseItemsFor(OrderEntity)` | Unchanged |

## Entities

Properties are typed, camelCase and mapped to the same snake_case columns. Unlisted columns keep their name
(`price` → `$price`). `created`, `updated` and every `*_at` column are `?DateTimeImmutable` (0.2: `?string`).

### `ArtworkEntity` (`artwork`)

| 0.2 | 2.0 |
| --- | --- |
| `artwork_id` | `artworkId` (`?int`) |
| `resource_id`, `artist_resource_id`, `exhibition_resource_id` | `resourceId`, `artistResourceId`, `exhibitionResourceId` (`?int`) |
| `item_type` (`string`) | `itemType` (`ItemType`) |
| `price` | `price` (`int`), also `getPrice(): Money` |
| `status` (`string`) | `status` (`ArtworkStatus`), also `isAvailable()` |
| `medium`, `dimensions`, `year` | same names (`?string`) |
| `edition_details`, `external_sale_url` | `editionDetails`, `externalSaleUrl` |

### `OrderEntity` (`gallery_order`)

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

### `OrderItemEntity` (`gallery_order_item`)

| 0.2 | 2.0 |
| --- | --- |
| `order_item_id`, `order_id`, `artwork_id` | `orderItemId`, `orderId` (`int`), `artworkId` (`?int`) |
| `title`, `price` | same names; also `getPrice(): Money` |
| `artist_name` | `artistName` |

### `ArtistEnquiryEntity` (`artist_enquiry`)

| 0.2 | 2.0 |
| --- | --- |
| `artist_enquiry_id` | `artistEnquiryId` |
| `name`, `email`, `telephone`, `website`, `instagram`, `bio`, `statement`, `medium` | same names |
| `preferred_timing`, `how_heard`, `staff_notes` | `preferredTiming`, `howHeard`, `staffNotes` |
| `status` (`string`) | `status` (`EnquiryStatus`) |
| `files` (array) | `files` (`Collection<ArtistEnquiryFileEntity>`, in upload order) |

### `ArtistEnquiryFileEntity` (`artist_enquiry_file`)

| 0.2 | 2.0 |
| --- | --- |
| `artist_enquiry_file_id`, `artist_enquiry_id` | `artistEnquiryFileId`, `artistEnquiryId` |
| `filename`, `path`, `size` | same names |
| `mime_type` | `mimeType` |

### `EmailLogEntity` (`email_log`)

| 0.2 | 2.0 |
| --- | --- |
| `email_log_id`, `order_id`, `artist_enquiry_id` | `emailLogId`, `orderId`, `artistEnquiryId` |
| `recipient`, `subject`, `status`, `error` | same names |
| `message_class` | `messageClass` |

### Before and after

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

## Behaviour changes

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
- **Identity map.** contenir-db-model 2 returns one object per row per `EntityManager`. Availability checks use
  `ArtworkRepository::findCurrent()`, which re-reads the row; in long-running workers, `clear()` the manager between
  jobs as contenir-db-model recommends.
- **Stripe API version.** stripe-php 22 pins `2026-09-30.endive` (17.x pinned a `basil` version). The fields this
  package uses are unchanged, but review Stripe's changelog for any other Stripe calls your site makes.
