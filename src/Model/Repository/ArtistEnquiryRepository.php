<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Enquiry\EnquiryStatus;
use Contenir\Commerce\Model\Entity\AbstractArtistEnquiryEntity;
use Contenir\Commerce\Model\Entity\ArtistEnquiryEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<AbstractArtistEnquiryEntity>
 *
 * @api
 */
final class ArtistEnquiryRepository extends Repository
{
    /**
     * @param class-string<AbstractArtistEnquiryEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em, string $entityClass = ArtistEnquiryEntity::class)
    {
        parent::__construct($em, $entityClass);
    }

    /**
     * Enquiries in one pipeline state, newest first.
     *
     * @return list<AbstractArtistEnquiryEntity>
     *
     * @throws DbModelException
     */
    public function findByStatus(EnquiryStatus $status): array
    {
        return $this->findBy(['status' => $status], ['artistEnquiryId' => 'DESC']);
    }

    /**
     * A new, unsaved entity of the class this repository hydrates.
     */
    public function newEntity(): AbstractArtistEnquiryEntity
    {
        return new $this->metadata->className();
    }
}
