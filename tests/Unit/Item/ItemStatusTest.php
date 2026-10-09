<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Item;

use Contenir\Commerce\Item\ItemStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ItemStatusTest extends TestCase
{
    /**
     * @return array<string, array{string, ItemStatus, string}>
     */
    public static function statusProvider(): array
    {
        return [
            'listed'   => ['listed', ItemStatus::Listed, 'Listed'],
            'unlisted' => ['unlisted', ItemStatus::Unlisted, 'Unlisted'],
        ];
    }

    #[DataProvider('statusProvider')]
    #[Test]
    public function mapsTheStoredValueToALabel(string $value, ItemStatus $status, string $label): void
    {
        static::assertSame([$status, $label], [ItemStatus::from($value), $status->label()]);
    }
}
