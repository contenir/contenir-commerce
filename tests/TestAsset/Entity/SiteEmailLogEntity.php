<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractEmailLogEntity;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's own email log entity, with no extra columns.
 */
#[Table('email_log')]
final class SiteEmailLogEntity extends AbstractEmailLogEntity {}
