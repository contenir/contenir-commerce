<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default artist enquiry entity, mapped to the "artist_enquiry" table.
 *
 * @api
 */
#[Table('artist_enquiry')]
final class ArtistEnquiryEntity extends AbstractArtistEnquiryEntity {}
