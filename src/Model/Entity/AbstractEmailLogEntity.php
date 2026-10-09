<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * A record of one transactional email, linked to the order it was about,
 * if any. The status is the site's own vocabulary, for
 * example "sent" or "failed".
 *
 * Extend it with a final class carrying #[Table('email_log')] (or use
 * EmailLogEntity) and add the site's own columns there; point the
 * "contenir_commerce.email_log_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 */
abstract class AbstractEmailLogEntity
{
    #[Id(generated: true)]
    #[Column('email_log_id')]
    public ?int $emailLogId = null;

    #[Column('order_id')]
    public ?int $orderId = null;

    #[Column]
    public string $recipient;

    #[Column]
    public string $subject;

    #[Column('message_class')]
    public ?string $messageClass = null;

    #[Column]
    public string $status;

    #[Column]
    public ?string $error = null;

    #[Column]
    public ?DateTimeImmutable $created = null;
}
