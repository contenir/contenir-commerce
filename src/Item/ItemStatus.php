<?php

declare(strict_types=1);

namespace Contenir\Commerce\Item;

/**
 * Whether an item is offered for sale. A listed item's variants can be
 * bought while they have stock; an unlisted item cannot be bought at all.
 *
 * @api
 */
enum ItemStatus: string
{
    case Listed   = 'listed';
    case Unlisted = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::Listed => 'Listed',
            self::Unlisted => 'Unlisted',
        };
    }
}
