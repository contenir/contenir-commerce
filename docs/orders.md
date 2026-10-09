# Orders and the lifecycle

Three services in `Contenir\Commerce\Order` run the steps of an order:

| Service | Steps | Used by |
| --- | --- | --- |
| `CheckoutService` | `createPendingOrder()`, `beginCheckout()`, `purchaseItemsFor()` | The cart |
| `CompletionService` | `completeFromCheckoutSession()`, `expireCheckout()` | The Stripe webhook and the thank-you page |
| `FulfilmentService` | `markAwaitingPickup()`, `markCollected()`, `refundOrder()`, `cancelOrder()` | Staff, in the CMS |

`OrderManager` offers every step in one place and delegates each to its service; it is what RC1 sites call, and keeps
working unchanged. Inject a single service where a class needs only its part (see
[configuration](configuration.md#injecting-one-order-service)). They write through the contenir-db-model
`EntityManager` and read the time from the PSR-20 `ClockInterface`.

## Statuses

| From | May move to |
| --- | --- |
| `pending` | `paid`, `cancelled` |
| `paid` | `awaiting_pickup`, `refunded`, `cancelled` |
| `awaiting_pickup` | `collected`, `refunded`, `cancelled` |
| `collected` | `refunded` |
| `refunded`, `cancelled` | nothing (final) |

`OrderStatus::canTransitionTo()`, `allowedTransitions()` and `isFinal()` describe the lifecycle;
`transitionTo()` returns the next status or throws `InvalidTransitionException`. The services change a status
only through `transitionTo()`, so no method can skip a step.

## Steps

| Method | Does | Throws |
| --- | --- | --- |
| `createPendingOrder(list<PurchaseItem>, CustomerDetails)` | Checks every item is listed with its quantity in stock and matches its stored variant, then writes the order (reference `<prefix>-<year>-<id, 4 digits>`, total, tax) and one line per item, in one transaction | `InvalidArgumentException` (no items, a variant twice), `ItemUnavailableException`, `PurchaseItemMismatchException` |
| `beginCheckout(order, successUrl, cancelUrl)` | Re-checks availability, creates the checkout session, stores its id | `InvalidTransitionException` (not pending, or checkout already begun), `ItemUnavailableException`, `PaymentFailedException` |
| `completeFromCheckoutSession(sessionId)` | Settles the order from its session (below) | `OrderNotFoundException`, `PaymentFailedException` |
| `expireCheckout(sessionId)` | Cancels the order if it is still pending; returns it, or null. Stripe is not called: the session has already expired | |
| `markAwaitingPickup(order)` | `paid` → `awaiting_pickup` | `InvalidTransitionException` |
| `markCollected(order)` | `awaiting_pickup` → `collected`, sets `collectedAt` | `InvalidTransitionException` |
| `refundOrder(order, ?Money)` | Refunds through the gateway (null: in full) and moves to `refunded`; stock is not returned | `PaymentFailedException` (no payment, provider refused), `InvalidTransitionException` |
| `cancelOrder(order)` | Expires the checkout session of a pending order (below), then moves to `cancelled` and sets `cancelledAt` | `InvalidTransitionException`, `PaymentFailedException` |
| `purchaseItemsFor(order)` | The order's lines as `PurchaseItem`s, in the order they were added | |

Each checkout attempt needs its own pending order. `beginCheckout()` refuses an order whose checkout has already
begun, so that a payment through an older session can always be matched to its order.

## Prices are checked, not trusted

A `PurchaseItem` is a quantity of one variant: `new PurchaseItem($variantId, $title, $unitPrice, $quantity,
$variantLabel, $description)`, with a quantity of at least 1. The description is shown under the title at checkout
(an artist's name, say). `createPendingOrder()` reads each variant and its item as stored and compares the
`PurchaseItem`:

- its **unit price** must equal the variant's `price`;
- its **title** must equal the item's (`AbstractItemEntity::getTitle()`), unless that is null;
- its **variant label** must equal the variant's `label`, unless that is null.

A difference throws `PurchaseItemMismatchException` (an `UnexpectedValueException`), whose message names the
variant and both values, and `getItemVariantId()` the variant; nothing is written. Availability is checked first, so
a cart with a sold-out, unlisted or deleted item reports `ItemUnavailableException`, listing every unavailable title.
Build items from the variant and item rows on the server, and rebuild a cart that holds prices across a price
change. A variant may appear in an order once, with its quantity.

Each line records the item and variant ids and snapshots of the title, label, description, unit price and quantity,
so the order stays accurate after the item changes or is deleted (the ids become null). The order total is the sum of
unit price times quantity. The reference prefix and the tax rate recorded on the order (`gstAmount`) come from
[configuration](configuration.md); the defaults are `ORD` and 10% GST.

## Completing a checkout

`completeFromCheckoutSession()` is safe to call from both the webhook and the thank-you page, any number of times,
including at the same time:

| Order status | Session | Result |
| --- | --- | --- |
| `pending` | not paid (open, or complete with funds still to come) | `NotPaid`; nothing changes |
| `pending` | paid, every item claimed | `Completed`: the order is `paid` and its units taken from stock, in one transaction |
| `pending` | paid, the last units of an item sold to someone else first | `RefundedRace`: refunded in full, order `refunded`; `unavailableTitles` lists the lost items |
| `cancelled` | paid (the order was cancelled while the buyer paid) | `RefundedCancelled`: refunded in full, order stays `cancelled` |
| `cancelled` | not paid | `NotPaid` |
| anything else | not consulted | `AlreadyCompleted` |

There are no holds: the first completed payment wins. Refunds issued here carry an idempotency key derived from the
payment intent, so a retry after a failure part-way through refunds at most once.

### The atomic claim

Each line's quantity is claimed from its variant's stock with one conditional update, inside the completion
transaction:

```sql
UPDATE item_variant SET stock = stock - ?, updated = ? WHERE item_variant_id = ? AND stock >= ?
```

The affected-row count says whether this payment got the units (`ItemVariantRepository::claim()`). Two payments for
the last unit completing at the same instant cannot both get it: the database applies the two updates one after the
other, and the second matches no row. An edition with units left serves both. This needs no `SELECT ... FOR UPDATE`,
does not depend on the isolation level, and behaves the same on SQLite, MySQL and PostgreSQL. Variants whose stock is
not tracked are not claimed; a variant deleted since checkout cannot be.

If any line of the order cannot be claimed, the transaction is rolled back, returning the units already claimed for
it, and the payment takes the race-refund path. That path re-reads the order first: if another completion of the
same order (the webhook and the thank-you page together) settled it in the meantime, the claim was lost to the
order itself, and the outcome is `AlreadyCompleted` with nothing refunded.

After a successful completion, variants the `EntityManager` already holds are re-read, so they show their new stock.

## Cancelling

`cancelOrder()` checks the transition first. For a `pending` order whose checkout has begun, it then asks the gateway
to expire the checkout session, so the buyer can no longer pay through it:

| Session at Stripe | Result |
| --- | --- |
| open | Expired; the order is cancelled |
| already expired | The order is cancelled |
| complete, paid or with funds still to come | The order is cancelled; when the session completes (webhook, thank-you page), the payment is refunded in full: `RefundedCancelled` |
| Stripe cannot be reached | `PaymentFailedException`; the order stays `pending`, so cancelling can be retried |

Orders that are already paid (or later) are cancelled without calling Stripe. `expireCheckout()`, for the
`checkout.session.expired` webhook, never calls Stripe.

## Results

`CompletionResult` carries the `order` (an `AbstractOrderEntity`), the `outcome` and, for `RefundedRace`,
`unavailableTitles`.
