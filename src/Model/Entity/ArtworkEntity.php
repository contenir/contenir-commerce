<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Entity;

use Contenir\Db\Model\Mapping\Table;

/**
 * The default artwork entity, mapped to the "artwork" table.
 *
 * @api
 */
#[Table('artwork')]
final class ArtworkEntity extends AbstractArtworkEntity {}
