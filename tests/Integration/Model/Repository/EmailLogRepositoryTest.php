<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Model\Entity\EmailLogEntity;
use Contenir\Commerce\Model\Repository\EmailLogRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('repository')]
final class EmailLogRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private EmailLogRepository $repository;

    #[Test]
    public function findsTheEmailsAboutAnOrderNewestFirst(): void
    {
        static::assertSame([3, 1], $this->ids($this->repository->findByOrderId(5)));
    }

    #[Test]
    public function savesANewEntryAndUpdatesItInPlace(): void
    {
        $entry               = new EmailLogEntity();
        $entry->orderId      = 9;
        $entry->recipient    = 'buyer@example.test';
        $entry->subject      = 'Your order';
        $entry->messageClass = 'OrderConfirmation';
        $entry->status       = 'failed';
        $entry->error        = 'SMTP timeout';
        $entry->created      = new DateTimeImmutable('2026-08-20 10:00:00');
        $this->em->save($entry);

        $entry->status = 'sent';
        $entry->error  = null;
        $this->em->save($entry);
        $this->em->clear();

        $found = $this->repository->find((int) $entry->emailLogId);

        static::assertEquals(
            [
                9,
                'buyer@example.test',
                'Your order',
                'OrderConfirmation',
                'sent',
                null,
                $entry->created,
                5,
            ],
            [
                $found?->orderId,
                $found?->recipient,
                $found?->subject,
                $found?->messageClass,
                $found?->status,
                $found?->error,
                $found?->created,
                $this->repository->count(),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new EmailLogRepository($this->em);

        $this->insert('email_log', ['order_id' => 5, 'recipient' => 'a@x.test', 'subject' => 'S', 'status' => 'sent']);
        $this->insert('email_log', ['order_id' => 6, 'recipient' => 'b@x.test', 'subject' => 'S', 'status' => 'sent']);
        $this->insert('email_log', ['order_id' => 5, 'recipient' => 'c@x.test', 'subject' => 'S', 'status' => 'sent']);
        $this->insert('email_log', ['recipient' => 'd@x.test', 'subject' => 'S', 'status' => 'sent']);
    }

    /**
     * @param list<EmailLogEntity> $entries
     *
     * @return list<?int>
     */
    private function ids(array $entries): array
    {
        return array_map(static fn(EmailLogEntity $entry): ?int => $entry->emailLogId, $entries);
    }
}
