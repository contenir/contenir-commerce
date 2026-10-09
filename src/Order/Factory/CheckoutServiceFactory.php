<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\ItemInventory;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\PurchaseItemCheck;
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
            ServiceLocator::get($container, ItemInventory::class),
            ServiceLocator::get($container, PurchaseItemCheck::class),
            ServiceLocator::get($container, PaymentGatewayInterface::class),
            ServiceLocator::get($container, CommerceSettings::class),
        );
    }
}
