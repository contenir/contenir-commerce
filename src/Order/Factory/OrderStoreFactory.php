<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Db\Model\EntityManager;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the OrderStore the order services persist through.
 *
 * @internal
 */
final class OrderStoreFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): OrderStore
    {
        return new OrderStore(
            ServiceLocator::get($container, EntityManager::class),
            ServiceLocator::get($container, OrderRepository::class),
            ServiceLocator::get($container, OrderItemRepository::class),
            ServiceLocator::get($container, ClockInterface::class),
        );
    }
}
