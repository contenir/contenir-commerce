<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\FulfilmentService;
use Contenir\Commerce\Order\OrderManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the OrderManager facade over the three order services.
 *
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
            ServiceLocator::get($container, CheckoutService::class),
            ServiceLocator::get($container, CompletionService::class),
            ServiceLocator::get($container, FulfilmentService::class),
        );
    }
}
