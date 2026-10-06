# Entities and your own columns

Each table has an abstract base class holding its columns and behaviour, and a final default entity carrying
`#[Table]`:

| Table | Abstract base | Default entity | Repository | Config key |
| --- | --- | --- | --- | --- |
| `artwork` | `AbstractArtworkEntity` | `ArtworkEntity` | `ArtworkRepository` | `artwork_entity` |
| `gallery_order` | `AbstractOrderEntity` | `OrderEntity` | `OrderRepository` | `order_entity` |
| `gallery_order_item` | `AbstractOrderItemEntity` | `OrderItemEntity` | `OrderItemRepository` | `order_item_entity` |
| `artist_enquiry` | `AbstractArtistEnquiryEntity` | `ArtistEnquiryEntity` | `ArtistEnquiryRepository` | `artist_enquiry_entity` |
| `artist_enquiry_file` | `AbstractArtistEnquiryFileEntity` | `ArtistEnquiryFileEntity` | `ArtistEnquiryFileRepository` | `artist_enquiry_file_entity` |
| `email_log` | `AbstractEmailLogEntity` | `EmailLogEntity` | `EmailLogRepository` | `email_log_entity` |

All in `Contenir\Commerce\Model\Entity`; the keys are under `contenir_commerce`. The columns of each table are listed
in [artworks](artworks.md), [enquiries](enquiries.md) and, for orders and their lines, the entity tables of
[UPGRADE-2.0.md](../UPGRADE-2.0.md#entities).

## Adding columns

1. Add the column to the table yourself (the package ships no migrations), nullable or with a default so that rows
   written by the package still insert.
2. Extend the abstract base with a final class carrying the same `#[Table]`, and map the column:

   ```php
   use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;
   use Contenir\Db\Model\Mapping\Column;
   use Contenir\Db\Model\Mapping\Table;
   use Override;

   #[Table('artwork')]
   final class Artwork extends AbstractArtworkEntity
   {
       #[Column]
       public ?string $title = null;

       #[Column('frame_colour')]
       public ?string $frameColour = null;

       // Optional: new orders then check each item's title against this.
       #[Override]
       public function getTitle(): ?string
       {
           return $this->title;
       }
   }
   ```

3. Point the repository at it:

   ```php
   'contenir_commerce' => ['artwork_entity' => App\Entity\Artwork::class],
   ```

`ArtworkRepository` then hydrates `App\Entity\Artwork` everywhere: in its finders, in the availability checks and in
`newEntity()`. The package's own services only read and write the base columns; your columns are yours to set.
Entities are created with no constructor arguments (`newEntity()`, and contenir-db-model's hydration), so keep any
constructor of your own argument-free.

## Relations

`AbstractOrderEntity::$items` and `AbstractArtistEnquiryEntity::$files` are declared with the default line and file
entities. If you configure your own order item (or enquiry file) entity, configure your own order (or enquiry) entity
too and redeclare the relation with your class:

```php
#[Table('gallery_order')]
final class Order extends AbstractOrderEntity
{
    /** @var Collection<OrderLine> */
    #[HasMany(OrderLine::class, foreignKey: 'order_id', orderBy: ['order_item_id' => 'ASC'])]
    public Collection $items;
}
```

```php
'contenir_commerce' => [
    'order_entity'      => App\Entity\Order::class,
    'order_item_entity' => App\Entity\OrderLine::class,
],
```

## New entities in code

Use the repository so the configured class is used:

```php
$log            = $container->get(EmailLogRepository::class)->newEntity();
$log->recipient = 'buyer@example.test';
// ...
$entityManager->save($log);
```

Types in the package's API are the abstract bases (`AbstractOrderEntity` in `CompletionResult::$order`, for example),
so check for your own class with `instanceof` where you need its columns.
