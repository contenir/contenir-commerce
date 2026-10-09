# Configuration and container

## Services

`Contenir\Commerce\ConfigProvider` registers, under `dependencies` (Mezzio and laminas-config-aggregator):

| Service | Built by |
| --- | --- |
| `ItemRepository`, `ItemVariantRepository`, `OrderRepository`, `OrderItemRepository`, `EmailLogRepository` | `Model\Repository\Factory\RepositoryFactory`, over the shared `EntityManager`, with the configured entity classes |
| `OrderManager` | `Order\Factory\OrderManagerFactory`: the façade over the three services below |
| `CheckoutService`, `CompletionService`, `FulfilmentService` | `Order\Factory\CheckoutServiceFactory`, `CompletionServiceFactory`, `FulfilmentServiceFactory` |
| `OrderStore`, `ItemInventory`, `Refunder` (internal) | `Order\Factory\OrderStoreFactory`, `ItemInventoryFactory`, `RefunderFactory` |
| `PurchaseItemCheck` (internal) | invokable |
| `CommerceSettings` | `Config\Factory\CommerceSettingsFactory`, from the `contenir_commerce` key |
| `PaymentGatewayInterface` | `Payment\Factory\StripeGatewayFactory`: `StripeGateway`, or `UnconfiguredGateway` without a key |
| `SystemClock` (alias `Psr\Clock\ClockInterface`) | invokable |

`Contenir\Commerce\Module::getConfig()` returns the same services under `service_manager` for laminas-mvc.

The `EntityManager` comes from contenir-db-model's `ConfigProvider` or module, configured under
`contenir_db_model` (the adapter service, the metadata cache). `ItemVariantRepository` also takes that adapter service
(`contenir_db_model.adapter`, `PhpDb\Adapter\AdapterInterface` by default) and contenir-db-model's `TypeRegistry`
service, so that its atomic claim runs on the EntityManager's connection, inside its transactions. Entities are not
container services.

Override any service in your own `dependencies` (or `service_manager`). To use your application's clock, alias
`Psr\Clock\ClockInterface` to it; every time the package records comes from that service.

### Injecting one order service

A class that needs only part of the lifecycle can depend on that service instead of `OrderManager`:

```php
use Contenir\Commerce\Order\CompletionService;

final class StripeWebhookHandler
{
    public function __construct(private CompletionService $completion) {}
}

// factory
new StripeWebhookHandler($container->get(CompletionService::class));
```

`OrderManager` and the services share the same internal helpers, so mixing them in one request is safe.

## Keys

### `stripe`

| Key | Default | Used by |
| --- | --- | --- |
| `stripe.secret_key` | none | `StripeGatewayFactory`; a string. Absent, null or empty gives `UnconfiguredGateway` |

A `stripe` value that is not an array, or a secret key that is not a string, throws
`Exception\ConfigurationException` naming the key. Keep the secret key out of version control, in an untracked
`config/autoload/*.local.php`.

### `contenir_commerce`

Every key is optional.

```php
return [
    'contenir_commerce' => [
        'order_reference_prefix' => 'GG',   // GG-2026-0001
        'currency'               => 'NZD',
        'tax_rate'               => 15,     // percent, included in every price
        'tax_label'              => 'GST',
        'item_entity'            => App\Entity\Artwork::class,
    ],
];
```

| Key | Default | Valid values |
| --- | --- | --- |
| `order_reference_prefix` | `ORD` | A non-empty string. References read `<prefix>-<year>-<order id, at least 4 digits>` |
| `currency` | `AUD` | Three letters, either case (an ISO 4217 code). Stored upper case; sent to Stripe lower case |
| `tax_rate` | `10` | An int or float from 0 to 100, the percentage of tax included in prices. A numeric string is refused |
| `tax_label` | `GST` | A non-empty string, for templates: `CommerceSettings::$taxLabel` |
| `item_entity` | `ItemEntity` | A class extending `AbstractItemEntity` |
| `item_variant_entity` | `ItemVariantEntity` | A class extending `AbstractItemVariantEntity` |
| `order_entity` | `OrderEntity` | A class extending `AbstractOrderEntity` |
| `order_item_entity` | `OrderItemEntity` | A class extending `AbstractOrderItemEntity` |
| `email_log_entity` | `EmailLogEntity` | A class extending `AbstractEmailLogEntity` |

A null value takes the default. A value of the wrong type, an empty prefix or label, a currency that is not three
letters, a rate outside 0 to 100 (or `NAN`, `INF`), or a class that does not exist or does not extend its base throws
`Exception\ConfigurationException` naming the key, when the service is first built.

`CommerceSettings` holds the validated values: `orderReferencePrefix`, `currency`, `taxRate` (a `Money\TaxRate`) and
`taxLabel`. Constructing it in code validates the same way. The package is single-currency: `Money` does not carry a
currency, and `Money::format()` always prints `$`.

The configured rate sets the tax recorded on each new order (`gstAmount`; the column keeps its name). Orders already
stored keep the tax they were created with.

## Database

The package reads and writes five tables: `item`, `item_variant`, `commerce_order`, `commerce_order_item` and
`email_log`; it ships no migrations. Indexes on `item_variant.item_id`, `commerce_order.stripe_checkout_session_id`
and `commerce_order_item.order_id` keep the lookups the order services make fast; the claim updates `item_variant`
by its primary key. Sites upgrading from RC2 and its gallery tables follow
[UPGRADE-2.0.md](../UPGRADE-2.0.md#from-200-rc2-to-200-rc3).

To add columns of your own, see [entities](entities.md).
