<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default order entity, mapped to the "commerce_order" table.
 *
 * @api
 */
#[Table('commerce_order')]
final class OrderEntity extends AbstractOrderEntity {}
