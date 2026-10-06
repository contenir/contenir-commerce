<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Artwork;

use Contenir\Commerce\Artwork\ItemType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ItemTypeTest extends TestCase
{
    /**
     * @return array<string, array{string, ItemType, string}>
     */
    public static function typeProvider(): array
    {
        return [
            'artwork' => ['artwork', ItemType::Artwork, 'Artwork'],
            'retail'  => ['retail', ItemType::Retail, 'Retail product'],
        ];
    }

    #[DataProvider('typeProvider')]
    #[Test]
    public function mapsTheStoredValueToALabel(string $value, ItemType $type, string $label): void
    {
        static::assertSame([$type, $label], [ItemType::from($value), $type->label()]);
    }
}
