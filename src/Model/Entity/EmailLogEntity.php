<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default email log entity, mapped to the "email_log" table.
 *
 * @api
 */
#[Table('email_log')]
final class EmailLogEntity extends AbstractEmailLogEntity {}
