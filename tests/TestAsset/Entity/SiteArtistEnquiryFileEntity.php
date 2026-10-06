<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractArtistEnquiryFileEntity;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's own artist enquiry file entity, with no extra columns.
 */
#[Table('artist_enquiry_file')]
final class SiteArtistEnquiryFileEntity extends AbstractArtistEnquiryFileEntity {}
