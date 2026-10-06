<?php

declare(strict_types=1);

namespace Contenir\Commerce\Model\Repository;

use Contenir\Commerce\Model\Entity\EmailLogEntity;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<EmailLogEntity>
 *
 * @api
 */
final class EmailLogRepository extends Repository
{
    /**
     * @throws DbModelException When the entity mapping is invalid.
     */
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, EmailLogEntity::class);
    }

    /**
     * The emails sent about one artist enquiry, newest first.
     *
     * @return list<EmailLogEntity>
     *
     * @throws DbModelException
     */
    public function findByArtistEnquiryId(int $artistEnquiryId): array
    {
        return $this->findBy(['artistEnquiryId' => $artistEnquiryId], ['emailLogId' => 'DESC']);
    }

    /**
     * The emails sent about one order, newest first.
     *
     * @return list<EmailLogEntity>
     *
     * @throws DbModelException
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findBy(['orderId' => $orderId], ['emailLogId' => 'DESC']);
    }
}
