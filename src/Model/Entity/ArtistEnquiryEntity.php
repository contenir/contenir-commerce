<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Enquiry\EnquiryStatus;
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use DateTimeImmutable;

/**
 * A prospective artist's submission, reviewed by staff in the CMS.
 *
 * @api
 *
 * @mago-expect analysis:missing-constructor Entities are hydrated without their constructor; required columns
 *     and relations stay uninitialised until they are assigned or loaded.
 *
 * @mago-expect lint:too-many-properties One property per column of the artist_enquiry table.
 */
#[Table('artist_enquiry')]
final class ArtistEnquiryEntity
{
    #[Id(generated: true)]
    #[Column('artist_enquiry_id')]
    public ?int $artistEnquiryId = null;

    #[Column]
    public string $name;

    #[Column]
    public string $email;

    #[Column]
    public ?string $telephone = null;

    #[Column]
    public ?string $website = null;

    #[Column]
    public ?string $instagram = null;

    #[Column]
    public ?string $bio = null;

    #[Column]
    public ?string $statement = null;

    #[Column]
    public ?string $medium = null;

    #[Column('preferred_timing')]
    public ?string $preferredTiming = null;

    #[Column('how_heard')]
    public ?string $howHeard = null;

    #[Column]
    public EnquiryStatus $status = EnquiryStatus::NewEnquiry;

    #[Column('staff_notes')]
    public ?string $staffNotes = null;

    #[Column]
    public ?DateTimeImmutable $created = null;

    #[Column]
    public ?DateTimeImmutable $updated = null;

    /**
     * @var Collection<ArtistEnquiryFileEntity>
     */
    #[HasMany(
        ArtistEnquiryFileEntity::class,
        foreignKey: 'artist_enquiry_id',
        orderBy: ['artist_enquiry_file_id' => 'ASC'],
    )]
    public Collection $files;
}
