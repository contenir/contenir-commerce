<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Order\ArtworkReservation;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the ArtworkReservation the order services check availability through.
 *
 * @internal
 */
final class ArtworkReservationFactory
{
    /**
     * @throws ConfigurationException When a service has the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ArtworkReservation
    {
        return new ArtworkReservation(
            ServiceLocator::get($container, ArtworkRepository::class),
        );
    }
}
