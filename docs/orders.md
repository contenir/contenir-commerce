# Orders and the lifecycle

`Contenir\Commerce\Order\OrderManager` runs every step of an order. It writes through the contenir-db-model
`EntityManager` and reads the time from the PSR-20 `ClockInterface`.

## Statuses

| From | May move to |
| --- | --- |
| `pending` | `paid`, `cancelled` |
| `paid` | `awaiting_pickup`, `refunded`, `cancelled` |
| `awaiting_pickup` | `collected`, `refunded`, `cancelled` |
| `collected` | `refunded` |
| `refunded`, `cancelled` | nothing (final) |

`OrderStatus::canTransitionTo()`, `allowedTransitions()` and `isFinal()` describe the lifecycle;
`transitionTo()` returns the next status or throws `InvalidTransitionException`. `OrderManager` changes a status
only through `transitionTo()`, so no method can skip a step.

## Steps

| Method | Does | Throws |
| --- | --- | --- |
| `createPendingOrder(list<PurchaseItem>, CustomerDetails)` | Checks every work is available, then writes the order (reference `LR-<year>-<id, 4 digits>`, total, GST) and one line per item, in one transaction | `InvalidArgumentException` (no items, a work twice), `ArtworkUnavailableException` |
| `beginCheckout(order, successUrl, cancelUrl)` | Re-checks availability, creates the checkout session, stores its id | `InvalidTransitionException` (not pending, or checkout already begun), `ArtworkUnavailableException`, `PaymentFailedException` |
| `completeFromCheckoutSession(sessionId)` | Settles the order from its session (below) | `OrderNotFoundException`, `PaymentFailedException` |
| `expireCheckout(sessionId)` | Cancels the order if it is still pending; returns it, or null | |
| `markAwaitingPickup(order)` | `paid` → `awaiting_pickup` | `InvalidTransitionException` |
| `markCollected(order)` | `awaiting_pickup` → `collected`, sets `collectedAt` | `InvalidTransitionException` |
| `refundOrder(order, ?Money)` | Refunds through the gateway (null: in full) and moves to `refunded`; artworks stay sold | `PaymentFailedException` (no payment, provider refused), `InvalidTransitionException` |
| `cancelOrder(order)` | Moves to `cancelled`, sets `cancelledAt` | `InvalidTransitionException` |
| `purchaseItemsFor(order)` | The order's lines as `PurchaseItem`s, in the order they were added | |

Each checkout attempt needs its own pending order. `beginCheckout()` refuses an order whose checkout has already
begun, so that a payment through an older session can always be matched to its order.

## Completing a checkout

`completeFromCheckoutSession()` is safe to call from both the webhook and the thank-you page, any number of times:

| Order status | Session | Result |
| --- | --- | --- |
| `pending` | not paid (open, or complete with funds still to come) | `NotPaid`; nothing changes |
| `pending` | paid, every work still available | `Completed`: the order is `paid`, its works `sold`, in one transaction |
| `pending` | paid, a work sold to someone else first | `RefundedRace`: refunded in full, order `refunded`; `unavailableTitles` lists the lost works |
| `cancelled` | paid (the order was cancelled while the buyer paid) | `RefundedCancelled`: refunded in full, order stays `cancelled` |
| `cancelled` | not paid | `NotPaid` |
| anything else | not consulted | `AlreadyCompleted` |

There are no holds: the first completed payment wins. Refunds issued here carry an idempotency key derived from the
payment intent, so a retry after a failure part-way through refunds at most once.

Availability is always read from the database, even if the entity manager already holds the artwork, so a long-lived
manager cannot act on a stale copy. Two payments for the same work completing at the same instant are not
serialised by a database lock; see the follow-ups in the changelog.

## Results

`CompletionResult` carries the `order`, the `outcome` and, for `RefundedRace`, `unavailableTitles`.
