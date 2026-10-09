<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default item variant entity, mapped to the "item_variant" table.
 *
 * @api
 */
#[Table('item_variant')]
final class ItemVariantEntity extends AbstractItemVariantEntity {}
