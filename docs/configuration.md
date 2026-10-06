# Configuration and container

## Services

`Contenir\Commerce\ConfigProvider` registers, under `dependencies` (Mezzio and laminas-config-aggregator):

| Service | Built by |
| --- | --- |
| `ArtworkRepository`, `OrderRepository`, `OrderItemRepository`, `ArtistEnquiryRepository`, `ArtistEnquiryFileRepository`, `EmailLogRepository` | contenir-db-model's `Container\RepositoryFactory`, over the shared `EntityManager` |
| `OrderManager` | `Order\Factory\OrderManagerFactory` |
| `PaymentGatewayInterface` | `Payment\Factory\StripeGatewayFactory`: `StripeGateway`, or `UnconfiguredGateway` without a key |
| `SystemClock` (alias `Psr\Clock\ClockInterface`) | invokable |

`Contenir\Commerce\Module::getConfig()` returns the same services under `service_manager` for laminas-mvc.

The `EntityManager` comes from contenir-db-model's `ConfigProvider` or module, configured under
`contenir_db_model` (the adapter service, the metadata cache). Entities are not container services.

Override any service in your own `dependencies` (or `service_manager`). To use your application's clock, alias
`Psr\Clock\ClockInterface` to it; every time the package records comes from that service.

## Keys

| Key | Default | Used by |
| --- | --- | --- |
| `stripe.secret_key` | none | `StripeGatewayFactory`; a string. Absent, null or empty gives `UnconfiguredGateway` |

A `stripe` value that is not an array, or a secret key that is not a string, throws
`Exception\ConfigurationException` naming the key. A service of the wrong type (for example a `ClockInterface` alias
to something else) throws `ConfigurationException` naming the service.

Keep the secret key out of version control, in an untracked `config/autoload/*.local.php`.

## Database

2.0 reads and writes the same tables and columns as 0.2: `artwork`, `gallery_order`, `gallery_order_item`,
`artist_enquiry`, `artist_enquiry_file` and `email_log`. No migration is needed. Indexes on
`gallery_order.stripe_checkout_session_id`, `gallery_order_item.order_id` and `artist_enquiry_file.artist_enquiry_id`
keep the lookups `OrderManager` makes fast.
