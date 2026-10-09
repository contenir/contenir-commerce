<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\ItemInventory;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the CompletionService.
 *
 * @api
 */
final class CompletionServiceFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CompletionService
    {
        return new CompletionService(
            ServiceLocator::get($container, OrderStore::class),
            ServiceLocator::get($container, ItemInventory::class),
            ServiceLocator::get($container, Refunder::class),
            ServiceLocator::get($container, PaymentGatewayInterface::class),
        );
    }
}
