# The email log

`Model\Entity\EmailLogEntity` maps `email_log`: one row per transactional email, linked to the order it was about,
if any. Like every entity, its columns live in an abstract base (`AbstractEmailLogEntity`) that a site can extend
with columns of its own, such as a link to some other record the email was about; see [entities](entities.md).

| Property | Column | Type |
| --- | --- | --- |
| `emailLogId` | `email_log_id` | `?int`, generated |
| `orderId` | `order_id` | `?int` |
| `recipient`, `subject` | same | `string`, required |
| `messageClass` | `message_class` | `?string` |
| `status` | `status` | `string`, the site's own vocabulary (`sent`, `failed`) |
| `error` | `error` | `?string` |
| `created` | `created` | `?DateTimeImmutable` |

`EmailLogRepository::findByOrderId(int)` returns an order's entries newest first.
