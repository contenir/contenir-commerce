<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Enquiry\EnquiryStatus;
use Contenir\Commerce\Model\Entity\ArtistEnquiryEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<ArtistEnquiryEntity>
 *
 * @api
 */
final class ArtistEnquiryRepository extends Repository
{
    /**
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, ArtistEnquiryEntity::class);
    }

    /**
     * Enquiries in one pipeline state, newest first.
     *
     * @return list<ArtistEnquiryEntity>
     *
     * @throws DbModelException
     */
    public function findByStatus(EnquiryStatus $status): array
    {
        return $this->findBy(['status' => $status], ['artistEnquiryId' => 'DESC']);
    }
}
