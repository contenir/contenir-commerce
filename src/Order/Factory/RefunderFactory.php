<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the Refunder completion refunds through.
 *
 * @internal
 */
final class RefunderFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Refunder
    {
        return new Refunder(
            ServiceLocator::get($container, PaymentGatewayInterface::class),
        );
    }
}
