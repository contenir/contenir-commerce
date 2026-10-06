<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Enquiry\EnquiryStatus;
use Contenir\Commerce\Model\Entity\ArtistEnquiryEntity;
use Contenir\Commerce\Model\Entity\ArtistEnquiryFileEntity;
use Contenir\Commerce\Model\Repository\ArtistEnquiryRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('repository')]
final class ArtistEnquiryRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private ArtistEnquiryRepository $repository;

    #[Test]
    public function aNewEnquirySavesWithItsDefaultStatus(): void
    {
        $enquiry        = new ArtistEnquiryEntity();
        $enquiry->name  = 'June Hollis';
        $enquiry->email = 'june@example.test';
        $this->em->save($enquiry);

        static::assertSame('new', $this->column(
            'artist_enquiry',
            'status',
            'artist_enquiry_id',
            (int) $enquiry->artistEnquiryId,
        ));
    }

    #[Test]
    public function findsEnquiriesInOneStateNewestFirst(): void
    {
        $this->insert('artist_enquiry', ['name' => 'A', 'email' => 'a@example.test', 'status' => 'shortlisted']);
        $this->insert('artist_enquiry', ['name' => 'B', 'email' => 'b@example.test', 'status' => 'new']);
        $this->insert('artist_enquiry', ['name' => 'C', 'email' => 'c@example.test', 'status' => 'shortlisted']);

        static::assertSame(
            ['C', 'A'],
            array_map(
                static fn(ArtistEnquiryEntity $enquiry): string => $enquiry->name,
                $this->repository->findByStatus(EnquiryStatus::Shortlisted),
            ),
        );
    }

    #[Test]
    public function itsFilesLoadInUploadOrder(): void
    {
        $this->insert('artist_enquiry', ['artist_enquiry_id' => 1, 'name' => 'A', 'email' => 'a@example.test']);
        $this->insert('artist_enquiry_file', ['artist_enquiry_id' => 1, 'filename' => 'z.jpg', 'path' => '/z.jpg']);
        $this->insert('artist_enquiry_file', ['artist_enquiry_id' => 1, 'filename' => 'a.jpg', 'path' => '/a.jpg']);

        static::assertSame(
            ['z.jpg', 'a.jpg'],
            array_map(
                static fn(ArtistEnquiryFileEntity $file): string => $file->filename,
                $this->repository->find(1)?->files->toArray() ?? [],
            ),
        );
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('artist_enquiry', [
            'artist_enquiry_id' => 3,
            'name'              => 'June Hollis',
            'email'             => 'june@example.test',
            'telephone'         => '0400 111 222',
            'website'           => 'https://june.example.test',
            'instagram'         => '@junehollis',
            'bio'               => 'Painter',
            'statement'         => 'Coastal light',
            'medium'            => 'Oil',
            'preferred_timing'  => 'Spring 2027',
            'how_heard'         => 'A friend',
            'status'            => 'under_review',
            'staff_notes'       => 'Strong folio',
            'created'           => '2026-08-01 09:00:00',
            'updated'           => '2026-08-02 09:00:00',
        ]);

        $enquiry = $this->repository->find(3);

        static::assertEquals(
            [
                3,
                'June Hollis',
                'june@example.test',
                '0400 111 222',
                'https://june.example.test',
                '@junehollis',
                'Painter',
                'Coastal light',
                'Oil',
                'Spring 2027',
                'A friend',
                EnquiryStatus::UnderReview,
                'Strong folio',
                new DateTimeImmutable('2026-08-01 09:00:00'),
                new DateTimeImmutable('2026-08-02 09:00:00'),
            ],
            [
                $enquiry?->artistEnquiryId,
                $enquiry?->name,
                $enquiry?->email,
                $enquiry?->telephone,
                $enquiry?->website,
                $enquiry?->instagram,
                $enquiry?->bio,
                $enquiry?->statement,
                $enquiry?->medium,
                $enquiry?->preferredTiming,
                $enquiry?->howHeard,
                $enquiry?->status,
                $enquiry?->staffNotes,
                $enquiry?->created,
                $enquiry?->updated,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new ArtistEnquiryRepository($this->em);
    }
}
