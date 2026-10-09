<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default item entity, mapped to the "item" table.
 *
 * @api
 */
#[Table('item')]
final class ItemEntity extends AbstractItemEntity {}
