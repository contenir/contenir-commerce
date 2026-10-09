# Items and variants

An **item** is something the site sells: an artwork, a print, a treatment, a tote bag. Its **variants** are the
versions a buyer can actually choose, each with its own price and stock: an edition's print sizes, a treatment's
lengths. An item sold in one form only has a single variant, usually without a label. Prices, stock and order lines
always belong to a variant, so the order services handle one shape whatever is being sold.

| | Item | Variants | Stock |
| --- | --- | --- | --- |
| An original artwork | "Headland, Dawn" | one, unlabelled | 1 |
| A limited print | "Headland, Dawn (print)" | "A3", "A2" | 25 each |
| A service | "Massage" | "60 minutes", "90 minutes" | untracked (`null`) |

The columns and behaviour live in `AbstractItemEntity` and `AbstractItemVariantEntity`; a site with columns of its
own (an artist, an exhibition, a CMS page id) extends them (see [entities](entities.md)).

## Items

`Model\Entity\ItemEntity` maps the `item` table.

| Property | Column | Type |
| --- | --- | --- |
| `itemId` | `item_id` | `?int`, generated |
| `title` | `title` | `?string` |
| `description` | `description` | `?string` |
| `status` | `status` | `ItemStatus` (`listed`, `unlisted`), default `listed` |
| `created`, `updated` | same | `?DateTimeImmutable` |
| `variants` | relation | `Collection<ItemVariantEntity>`, by `sequence`, then id |

`isListed()` is true while the status is `listed`; an unlisted item cannot be ordered. `getTitle()` returns the
title column, and new orders check each purchase item's title against it; override it when the title lives
elsewhere, such as on a CMS page, or leave the column null to skip the check.

## Variants

`Model\Entity\ItemVariantEntity` maps the `item_variant` table.

| Property | Column | Type |
| --- | --- | --- |
| `itemVariantId` | `item_variant_id` | `?int`, generated |
| `itemId` | `item_id` | `int` |
| `label` | `label` | `?string`, such as "A3"; null for an item's only variant |
| `sku` | `sku` | `?string` |
| `price` | `price` | `int` cents, tax-inclusive, per unit |
| `stock` | `stock` | `?int` units left to sell; null when not tracked |
| `sequence` | `sequence` | `int`, display order, default 0 |
| `created`, `updated` | same | `?DateTimeImmutable` |

`getPrice()` returns the price as `Money`. `hasStock(int $quantity = 1)` is true while at least that many units
remain, and always when stock is not tracked; `isStockTracked()` says which. A sold-out original is a variant with
stock 0: it stays visible, so a site can show it with a "sold" badge.

## Repositories

```php
$items    = $container->get(ItemRepository::class);
$variants = $container->get(ItemVariantRepository::class);

$items->find(12);                             // by id, or null
$items->findListed();                         // listed items, oldest first
$items->findCurrent(12);                      // re-read from the database even if already loaded; null once deleted
$items->newEntity();                          // a new item of the configured class

$variants->findByItemId(12);                  // an item's variants, by sequence
$variants->findCurrent(31);                   // re-read, as above
$variants->claim(31, 2, $clock->now());       // take 2 units if 2 remain: true for the caller that gets them
$variants->newEntity();
$variants->findBy(['itemId' => 12, 'stock' => 0]);
```

`claim()` is the atomic stock decrement completion uses: one conditional
`UPDATE item_variant SET stock = stock - ? ... WHERE item_variant_id = ? AND stock >= ?`, true when it changed the
row. The `UPDATE` reads the current row whatever the transaction's isolation level, so two buyers can never both take
the last unit. Run it inside the `EntityManager`'s transaction; it does not update an entity already loaded
(`findCurrent()` re-reads it). A variant whose stock is not tracked cannot be claimed (there is nothing to take), and
the order services do not try. Criteria and ordering use property names; enum cases or their values both work. Save
changes with the `EntityManager`:

```php
$variant->stock = 1;   // putting a refunded unit back on sale is a CMS decision
$em->save($variant);
```
