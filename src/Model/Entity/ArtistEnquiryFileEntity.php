<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default artist enquiry file entity, mapped to the "artist_enquiry_file" table.
 *
 * @api
 */
#[Table('artist_enquiry_file')]
final class ArtistEnquiryFileEntity extends AbstractArtistEnquiryFileEntity {}
