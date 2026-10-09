<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Model\Entity\AbstractOrderEntity;
use Contenir\Commerce\Model\Entity\AbstractOrderItemEntity;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Throwable;

use function array_map;

/**
 * Persistence and time for the order services: the EntityManager, the
 * order and line repositories, and the clock every recorded time comes
 * from.
 *
 * @internal
 */
final readonly class OrderStore
{
    public function __construct(
        private EntityManager $em,
        private OrderRepository $orders,
        private OrderItemRepository $orderItems,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws DbModelException
     */
    public function findByCheckoutSession(string $sessionId): ?AbstractOrderEntity
    {
        return $this->orders->findOneByCheckoutSessionId($sessionId);
    }

    public function newLine(): AbstractOrderItemEntity
    {
        return $this->orderItems->newEntity();
    }

    public function newOrder(): AbstractOrderEntity
    {
        return $this->orders->newEntity();
    }

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    /**
     * The order's lines as purchase items, in the order they were added. A
     * line whose variant has since been deleted has variant id 0.
     *
     * @return list<PurchaseItem>
     *
     * @throws OrderNotFoundException When the order has not been saved.
     * @throws InvalidArgumentException When a stored price is negative.
     * @throws DbModelException
     */
    public function purchaseItemsFor(AbstractOrderEntity $order): array
    {
        return array_map(
            static fn(AbstractOrderItemEntity $line): PurchaseItem => new PurchaseItem(
                $line->itemVariantId ?? 0,
                $line->title,
                $line->getUnitPrice(),
                $line->quantity,
                $line->variantLabel,
                $line->description,
            ),
            $this->orderItems->findByOrderId($order->getId()),
        );
    }

    /**
     * Overwrites the entity with its stored values, discarding unsaved
     * changes.
     *
     * @throws DbModelException
     */
    public function refresh(object $entity): void
    {
        $this->em->refresh($entity);
    }

    /**
     * @throws DbModelException
     */
    public function save(object $entity): void
    {
        $this->em->save($entity);
    }

    /**
     * @template R
     *
     * @param callable(): R $work
     *
     * @return R
     *
     * @throws Throwable Whatever $work throws, after rolling back.
     */
    public function transactional(callable $work): mixed
    {
        return $this->em->transactional($work);
    }
}
