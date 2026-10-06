<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use DateTimeImmutable;

/**
 * A file uploaded with an artist enquiry. The path is relative to the
 * site's public directory.
 *
 * Extend it with a final class carrying #[Table('artist_enquiry_file')] (or use
 * ArtistEnquiryFileEntity) and add the site's own columns there; point the
 * "contenir_commerce.artist_enquiry_file_entity" config key at that class.
 *
 * @api
 *
 * @consistent-constructor Entities are created with no constructor arguments.
 */
abstract class AbstractArtistEnquiryFileEntity
{
    #[Id(generated: true)]
    #[Column('artist_enquiry_file_id')]
    public ?int $artistEnquiryFileId = null;

    #[Column('artist_enquiry_id')]
    public int $artistEnquiryId;

    #[Column]
    public string $filename;

    #[Column]
    public string $path;

    #[Column('mime_type')]
    public ?string $mimeType = null;

    #[Column]
    public ?int $size = null;

    #[Column]
    public ?DateTimeImmutable $created = null;
}
