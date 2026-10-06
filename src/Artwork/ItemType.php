<?php

declare(strict_types=1);

namespace Contenir\Commerce\Artwork;

/**
 * Original artworks and curated retail products (tote bags, cards) share the
 * same table and cart; retail rows simply have no artist or exhibition.
 *
 * @api
 */
enum ItemType: string
{
    case Artwork = 'artwork';
    case Retail  = 'retail';

    public function label(): string
    {
        return match ($this) {
            self::Artwork => 'Artwork',
            self::Retail => 'Retail product',
        };
    }
}
