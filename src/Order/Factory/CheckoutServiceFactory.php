<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Order\ArtworkReservation;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the CheckoutService.
 *
 * @api
 */
final class CheckoutServiceFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CheckoutService
    {
        return new CheckoutService(
            ServiceLocator::get($container, OrderStore::class),
            ServiceLocator::get($container, ArtworkReservation::class),
            ServiceLocator::get($container, PaymentGatewayInterface::class),
        );
    }
}
