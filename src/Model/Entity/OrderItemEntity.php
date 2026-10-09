<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default order item entity, mapped to the "commerce_order_item" table.
 *
 * @api
 */
#[Table('commerce_order_item')]
final class OrderItemEntity extends AbstractOrderItemEntity {}
