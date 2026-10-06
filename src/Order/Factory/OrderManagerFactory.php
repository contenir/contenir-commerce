<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Order\OrderManager;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\EntityManager;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class OrderManagerFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): OrderManager
    {
        return new OrderManager(
            ServiceLocator::get($container, EntityManager::class),
            ServiceLocator::get($container, OrderRepository::class),
            ServiceLocator::get($container, OrderItemRepository::class),
            ServiceLocator::get($container, ArtworkRepository::class),
            ServiceLocator::get($container, PaymentGatewayInterface::class),
            ServiceLocator::get($container, ClockInterface::class),
        );
    }
}
