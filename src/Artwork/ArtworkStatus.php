<?php

declare(strict_types=1);

namespace Contenir\Commerce\Artwork;

/**
 * Sold artworks stay publicly visible with a badge rather than disappearing,
 * so this is a display state as much as a stock state.
 *
 * @api
 */
enum ArtworkStatus: string
{
    case Available = 'available';
    case Sold      = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Sold => 'Sold',
        };
    }
}
