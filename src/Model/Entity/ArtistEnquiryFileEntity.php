<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use DateTimeImmutable;

/**
 * A file uploaded with an artist enquiry. The path is relative to the
 * site's public directory.
 *
 * @api
 *
 * @mago-expect analysis:missing-constructor Entities are hydrated without their constructor; required columns
 *     and relations stay uninitialised until they are assigned or loaded.
 */
#[Table('artist_enquiry_file')]
final class ArtistEnquiryFileEntity
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
