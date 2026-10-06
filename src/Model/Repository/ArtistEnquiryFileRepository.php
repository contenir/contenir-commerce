<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\ArtistEnquiryFileEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<ArtistEnquiryFileEntity>
 *
 * @api
 */
final class ArtistEnquiryFileRepository extends Repository
{
    /**
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, ArtistEnquiryFileEntity::class);
    }

    /**
     * The files of one enquiry, in upload order.
     *
     * @return list<ArtistEnquiryFileEntity>
     *
     * @throws DbModelException
     */
    public function findByArtistEnquiryId(int $artistEnquiryId): array
    {
        return $this->findBy(['artistEnquiryId' => $artistEnquiryId], ['artistEnquiryFileId' => 'ASC']);
    }
}
