<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Artwork;

use Contenir\Commerce\Artwork\ArtworkStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ArtworkStatusTest extends TestCase
{
    /**
     * @return array<string, array{string, ArtworkStatus, string}>
     */
    public static function statusProvider(): array
    {
        return [
            'available' => ['available', ArtworkStatus::Available, 'Available'],
            'sold'      => ['sold', ArtworkStatus::Sold, 'Sold'],
        ];
    }

    #[DataProvider('statusProvider')]
    #[Test]
    public function mapsTheStoredValueToALabel(string $value, ArtworkStatus $status, string $label): void
    {
        static::assertSame([$status, $label], [ArtworkStatus::from($value), $status->label()]);
    }
}
