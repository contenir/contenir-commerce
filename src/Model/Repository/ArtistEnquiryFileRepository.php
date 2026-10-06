<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\AbstractArtistEnquiryFileEntity;
use Contenir\Commerce\Model\Entity\ArtistEnquiryFileEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<AbstractArtistEnquiryFileEntity>
 *
 * @api
 */
final class ArtistEnquiryFileRepository extends Repository
{
    /**
     * @param class-string<AbstractArtistEnquiryFileEntity> $entityClass
     *
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em, string $entityClass = ArtistEnquiryFileEntity::class)
    {
        parent::__construct($em, $entityClass);
    }

    /**
     * The files of one enquiry, in upload order.
     *
     * @return list<AbstractArtistEnquiryFileEntity>
     *
     * @throws DbModelException
     */
    public function findByArtistEnquiryId(int $artistEnquiryId): array
    {
        return $this->findBy(['artistEnquiryId' => $artistEnquiryId], ['artistEnquiryFileId' => 'ASC']);
    }

    /**
     * A new, unsaved entity of the class this repository hydrates.
     */
    public function newEntity(): AbstractArtistEnquiryFileEntity
    {
        return new $this->metadata->className();
    }
}
