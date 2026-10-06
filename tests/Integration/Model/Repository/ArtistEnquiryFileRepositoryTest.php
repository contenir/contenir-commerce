<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Model\Entity\ArtistEnquiryFileEntity;
use Contenir\Commerce\Model\Repository\ArtistEnquiryFileRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('repository')]
final class ArtistEnquiryFileRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private ArtistEnquiryFileRepository $repository;

    #[Test]
    public function findsTheFilesOfOneEnquiryInUploadOrder(): void
    {
        $this->insert('artist_enquiry_file', ['artist_enquiry_id' => 1, 'filename' => 'z.jpg', 'path' => '/z.jpg']);
        $this->insert('artist_enquiry_file', ['artist_enquiry_id' => 2, 'filename' => 'm.jpg', 'path' => '/m.jpg']);
        $this->insert('artist_enquiry_file', ['artist_enquiry_id' => 1, 'filename' => 'a.jpg', 'path' => '/a.jpg']);

        static::assertSame(
            ['z.jpg', 'a.jpg'],
            array_map(
                static fn(ArtistEnquiryFileEntity $file): string => $file->filename,
                $this->repository->findByArtistEnquiryId(1),
            ),
        );
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('artist_enquiry_file', [
            'artist_enquiry_file_id' => 5,
            'artist_enquiry_id'      => 1,
            'filename'               => 'sample.jpg',
            'path'                   => '/uploads/enquiry/1/sample.jpg',
            'mime_type'              => 'image/jpeg',
            'size'                   => 204_800,
            'created'                => '2026-08-01 09:00:00',
        ]);

        $file = $this->repository->find(5);

        static::assertEquals(
            [
                5,
                1,
                'sample.jpg',
                '/uploads/enquiry/1/sample.jpg',
                'image/jpeg',
                204_800,
                new DateTimeImmutable('2026-08-01 09:00:00'),
            ],
            [
                $file?->artistEnquiryFileId,
                $file?->artistEnquiryId,
                $file?->filename,
                $file?->path,
                $file?->mimeType,
                $file?->size,
                $file?->created,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new ArtistEnquiryFileRepository($this->em);
    }
}
