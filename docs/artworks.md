# Artworks

`Model\Entity\ArtworkEntity` maps the `artwork` table: original artworks and retail products (tote bags, cards)
share it. The resource ids link a row to the Contenir resource pages for the work, its artist and its exhibition.

| Property | Column | Type |
| --- | --- | --- |
| `artworkId` | `artwork_id` | `?int`, generated |
| `resourceId` | `resource_id` | `?int` |
| `artistResourceId` | `artist_resource_id` | `?int` |
| `exhibitionResourceId` | `exhibition_resource_id` | `?int` |
| `itemType` | `item_type` | `ItemType` (`artwork`, `retail`), default `artwork` |
| `price` | `price` | `int` cents, GST-inclusive |
| `status` | `status` | `ArtworkStatus` (`available`, `sold`), default `available` |
| `medium`, `dimensions`, `year` | same | `?string` |
| `editionDetails` | `edition_details` | `?string` |
| `externalSaleUrl` | `external_sale_url` | `?string` |
| `created`, `updated` | same | `?DateTimeImmutable` |

`isAvailable()` is true while the status is `available`; `getPrice()` returns the price as `Money`. Sold works stay
visible (with a badge): `ArtworkStatus` is a display state as much as a stock state. `label()` on both enums gives
the display text.

## ArtworkRepository

```php
$artworks = $container->get(ArtworkRepository::class);

$artworks->find(12);                          // by id, or null
$artworks->findByResourceIds([101, 103]);     // keyed by resource id; [] runs no query
$artworks->findByExhibitionResourceId(51);    // keyed by resource id, sold works included
$artworks->findByArtistResourceId(11);        // keyed by resource id
$artworks->findAvailableOngoing();            // available originals in no exhibition, keyed by resource id
$artworks->findCurrent(12);                   // re-read from the database even if already loaded
$artworks->findBy(['status' => ArtworkStatus::Available], ['price' => 'DESC']);
```

The keyed finders leave out rows with no resource id. Criteria and ordering use property names; enum cases or their
values both work. Save changes with the `EntityManager`:

```php
$artwork->status = ArtworkStatus::Available;   // returning a work to sale is a CMS decision
$em->save($artwork);
```
