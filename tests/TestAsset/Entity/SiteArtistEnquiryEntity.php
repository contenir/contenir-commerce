<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Entity;

use Contenir\Commerce\Model\Entity\AbstractArtistEnquiryEntity;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's own artist enquiry entity, with no extra columns.
 */
#[Table('artist_enquiry')]
final class SiteArtistEnquiryEntity extends AbstractArtistEnquiryEntity {}
