<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Order\FulfilmentService;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the FulfilmentService.
 *
 * @api
 */
final class FulfilmentServiceFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): FulfilmentService
    {
        return new FulfilmentService(
            ServiceLocator::get($container, OrderStore::class),
            ServiceLocator::get($container, Refunder::class),
            ServiceLocator::get($container, PaymentGatewayInterface::class),
        );
    }
}
