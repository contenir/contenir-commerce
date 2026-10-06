<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Artwork\ItemType;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Type\TypeRegistry;
use DateTimeImmutable;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\DriverInterface;
use PhpDb\Adapter\Driver\StatementInterface;
use PhpDb\Adapter\Profiler\Profiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;

#[Group('integration')]
#[Group('repository')]
final class ArtworkRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private ArtworkRepository $repository;

    /**
     * @return array<string, array{int}>
     */
    public static function unclaimableProvider(): array
    {
        return [
            'already sold'   => [2],
            'never existed'  => [999],
            'deleted (id 0)' => [0],
        ];
    }

    #[Test]
    public function aClaimLeavesTheManagedEntityForFindCurrentToReread(): void
    {
        $held = $this->repository->find(1);

        $this->repository->claim(1, new DateTimeImmutable('2026-08-20 10:20:00'));

        static::assertSame(
            [ArtworkStatus::Available, ArtworkStatus::Sold],
            [$held?->status, $this->repository->findCurrent(1)?->status],
        );
    }

    #[Test]
    public function aClaimSellsAnAvailableWorkExactlyOnce(): void
    {
        $at = new DateTimeImmutable('2026-08-20 10:20:00');

        static::assertSame(
            [true, false, ['status' => 'sold', 'updated' => '2026-08-20 10:20:00']],
            [
                $this->repository->claim(1, $at),
                $this->repository->claim(1, $at),
                $this->statusAndUpdated(1),
            ],
        );
    }

    #[Test]
    public function aClaimTheDriverReturnsNoResultForIsAnError(): void
    {
        $statement = static::createStub(StatementInterface::class);
        $statement->method('execute')->willReturn(null);
        $driver = static::createStub(DriverInterface::class);
        $driver->method('createStatement')->willReturn($statement);
        $adapter = static::createStub(AdapterInterface::class);
        $adapter->method('getDriver')->willReturn($driver);
        $adapter->method('getPlatform')->willReturn($this->adapter->getPlatform());

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('The database driver returned no result for a write statement');

        (new ArtworkRepository($this->em, $adapter, TypeRegistry::withDefaults()))->claim(
            1,
            new DateTimeImmutable('2026-08-20 10:20:00'),
        );
    }

    #[Test]
    public function aClaimTouchesOnlyItsOwnWork(): void
    {
        $this->repository->claim(1, new DateTimeImmutable('2026-08-20 10:20:00'));

        static::assertSame(['status' => 'available', 'updated' => null], $this->statusAndUpdated(3));
    }

    #[Test]
    public function anEmptyResourceIdListRunsNoQuery(): void
    {
        $profiler = new Profiler();
        $this->adapter->setProfiler($profiler);

        static::assertSame([[], []], [$this->repository->findByResourceIds([]), $profiler->getProfiles()]);
    }

    #[Test]
    public function availableOngoingWorksExcludeExhibitedSoldRetailAndUnlinkedRows(): void
    {
        static::assertSame([103], array_keys($this->repository->findAvailableOngoing()));
    }

    #[DataProvider('unclaimableProvider')]
    #[Test]
    public function aWorkThatIsNotAvailableCannotBeClaimed(int $artworkId): void
    {
        static::assertFalse($this->repository->claim($artworkId, new DateTimeImmutable('2026-08-20 10:20:00')));
    }

    #[Test]
    public function findCurrentRereadsAnArtworkTheManagerAlreadyHolds(): void
    {
        $held = $this->repository->find(1);
        $this->updateBehindTheManager("UPDATE artwork SET status = 'sold' WHERE artwork_id = 1");

        $current = $this->repository->findCurrent(1);

        static::assertSame([$held, ArtworkStatus::Sold], [$current, $current?->status]);
    }

    #[Test]
    public function findCurrentReturnsNullForAMissingArtwork(): void
    {
        static::assertNull($this->repository->findCurrent(999));
    }

    #[Test]
    public function findsAnArtistsWorksKeyedByResourceId(): void
    {
        static::assertSame([103, 104], array_keys($this->repository->findByArtistResourceId(12)));
    }

    #[Test]
    public function findsAnExhibitionsWorksIncludingSoldOnes(): void
    {
        $works = $this->repository->findByExhibitionResourceId(51);

        static::assertSame(
            [
                [101, 102],
                [101 => ArtworkStatus::Available, 102 => ArtworkStatus::Sold],
            ],
            [array_keys($works), array_map(static fn(ArtworkEntity $work): ArtworkStatus => $work->status, $works)],
        );
    }

    #[Test]
    public function findsArtworksByResourceIdIgnoringUnknownIds(): void
    {
        static::assertSame([101, 103], array_keys($this->repository->findByResourceIds([101, 103, 999])));
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('artwork', [
            'artwork_id'             => 50,
            'resource_id'            => 500,
            'artist_resource_id'     => 13,
            'exhibition_resource_id' => 52,
            'item_type'              => 'retail',
            'price'                  => 3_500,
            'status'                 => 'sold',
            'medium'                 => 'Screen print',
            'dimensions'             => '40 x 30 cm',
            'year'                   => '2026',
            'edition_details'        => '1 of 50',
            'external_sale_url'      => 'https://shop.example.test/tote',
            'created'                => '2026-08-01 09:00:00',
            'updated'                => '2026-08-02 10:30:00',
        ]);

        $artwork = $this->repository->find(50);

        static::assertEquals(
            [
                50,
                500,
                13,
                52,
                ItemType::Retail,
                3_500,
                ArtworkStatus::Sold,
                'Screen print',
                '40 x 30 cm',
                '2026',
                '1 of 50',
                'https://shop.example.test/tote',
                new DateTimeImmutable('2026-08-01 09:00:00'),
                new DateTimeImmutable('2026-08-02 10:30:00'),
            ],
            [
                $artwork?->artworkId,
                $artwork?->resourceId,
                $artwork?->artistResourceId,
                $artwork?->exhibitionResourceId,
                $artwork?->itemType,
                $artwork?->price,
                $artwork?->status,
                $artwork?->medium,
                $artwork?->dimensions,
                $artwork?->year,
                $artwork?->editionDetails,
                $artwork?->externalSaleUrl,
                $artwork?->created,
                $artwork?->updated,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new ArtworkRepository($this->em, $this->adapter, TypeRegistry::withDefaults());

        $fixtures = [
            ['resource_id' => 101, 'artist_resource_id' => 11, 'exhibition_resource_id' => 51, 'status' => 'available'],
            ['resource_id' => 102, 'artist_resource_id' => 11, 'exhibition_resource_id' => 51, 'status' => 'sold'],
            [
                'resource_id'            => null,
                'artist_resource_id'     => 12,
                'exhibition_resource_id' => null,
                'status'                 => 'available',
            ],
            [
                'resource_id'            => 103,
                'artist_resource_id'     => 12,
                'exhibition_resource_id' => null,
                'status'                 => 'available',
            ],
            ['resource_id' => 104, 'artist_resource_id' => 12, 'exhibition_resource_id' => null, 'status' => 'sold'],
            [
                'resource_id'            => 105,
                'artist_resource_id'     => null,
                'exhibition_resource_id' => null,
                'status'                 => 'available',
                'item_type'              => 'retail',
            ],
        ];

        foreach ($fixtures as $fixture) {
            $this->insert('artwork', [...$fixture, 'price' => 100_000]);
        }
    }

    /**
     * @return array{status: mixed, updated: mixed}
     */
    private function statusAndUpdated(int $artworkId): array
    {
        $row = $this->row('artwork', 'artwork_id', $artworkId);

        return ['status' => $row['status'], 'updated' => $row['updated']];
    }
}
