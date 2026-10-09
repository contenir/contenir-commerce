# Entities and your own columns

Each table has an abstract base class holding its columns and behaviour, and a final default entity carrying
`#[Table]`:

| Table | Abstract base | Default entity | Repository | Config key |
| --- | --- | --- | --- | --- |
| `item` | `AbstractItemEntity` | `ItemEntity` | `ItemRepository` | `item_entity` |
| `item_variant` | `AbstractItemVariantEntity` | `ItemVariantEntity` | `ItemVariantRepository` | `item_variant_entity` |
| `commerce_order` | `AbstractOrderEntity` | `OrderEntity` | `OrderRepository` | `order_entity` |
| `commerce_order_item` | `AbstractOrderItemEntity` | `OrderItemEntity` | `OrderItemRepository` | `order_item_entity` |
| `email_log` | `AbstractEmailLogEntity` | `EmailLogEntity` | `EmailLogRepository` | `email_log_entity` |

All in `Contenir\Commerce\Model\Entity`; the keys are under `contenir_commerce`. The columns of each table are listed
in [items](items.md), [the email log](email-log.md) and, for orders and their lines, the entity tables of
[UPGRADE-2.0.md](../UPGRADE-2.0.md#entities).

## Adding columns

1. Add the column to the table yourself (the package ships no migrations), nullable or with a default so that rows
   written by the package still insert.
2. Extend the abstract base with a final class carrying the same `#[Table]`, and map the column:

   ```php
   use Contenir\Commerce\Model\Entity\AbstractItemEntity;
   use Contenir\Db\Model\Mapping\Column;
   use Contenir\Db\Model\Mapping\Table;

   #[Table('item')]
   final class Artwork extends AbstractItemEntity
   {
       #[Column('artist_resource_id')]
       public ?int $artistResourceId = null;

       #[Column]
       public ?string $medium = null;
   }
   ```

3. Point the repository at it:

   ```php
   'contenir_commerce' => ['item_entity' => App\Entity\Artwork::class],
   ```

`ItemRepository` then hydrates `App\Entity\Artwork` everywhere: in its finders, in the availability checks and in
`newEntity()`. The package's own services only read and write the base columns; your columns are yours to set.
Entities are created with no constructor arguments (`newEntity()`, and contenir-db-model's hydration), so keep any
constructor of your own argument-free.

When the item's title lives elsewhere (a CMS page, say), override `getTitle()` to return it, and new orders check
purchase item titles against that instead of the `title` column.

## Relations

`AbstractOrderEntity::$items` and `AbstractItemEntity::$variants` are declared with the default line and variant
entities. If you configure your own order item (or variant) entity, configure your own order (or item) entity too and
redeclare the relation with your class:

```php
#[Table('commerce_order')]
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
