<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use DateTimeImmutable;

/**
 * A record of one transactional email, linked to the order or artist
 * enquiry it was about. The status is the site's own vocabulary, for
 * example "sent" or "failed".
 *
 * @api
 *
 * @mago-expect analysis:missing-constructor Entities are hydrated without their constructor; required columns
 *     and relations stay uninitialised until they are assigned or loaded.
 */
#[Table('email_log')]
final class EmailLogEntity
{
    #[Id(generated: true)]
    #[Column('email_log_id')]
    public ?int $emailLogId = null;

    #[Column('order_id')]
    public ?int $orderId = null;

    #[Column('artist_enquiry_id')]
    public ?int $artistEnquiryId = null;

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
