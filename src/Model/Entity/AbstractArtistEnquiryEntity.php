<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Commerce\Enquiry\EnquiryStatus;
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * A prospective artist's submission, reviewed by staff in the CMS.
 *
 * Extend it with a final class carrying #[Table('artist_enquiry')] (or use
 * ArtistEnquiryEntity) and add the site's own columns there; point the
 * "contenir_commerce.artist_enquiry_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 *
 * @mago-expect lint:too-many-properties One property per column of the artist_enquiry table.
 */
abstract class AbstractArtistEnquiryEntity
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
     * The enquiry's files as the default ArtistEnquiryFileEntity. A site
     * that configures its own enquiry file entity redeclares this property
     * on its enquiry entity with that class in #[HasMany].
     *
     * @var Collection<AbstractArtistEnquiryFileEntity>
     */
    #[HasMany(
        ArtistEnquiryFileEntity::class,
        foreignKey: 'artist_enquiry_id',
        orderBy: ['artist_enquiry_file_id' => 'ASC'],
    )]
    public Collection $files;
}
