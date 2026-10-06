<?php

declare(strict_types=1);

namespace Contenir\Commerce\Container;

use Contenir\Commerce\Exception\ConfigurationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Fetches a container service and checks its type.
 *
 * @internal
 */
final class ServiceLocator
{
    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws ConfigurationException When the service is not a $type.
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; the type is checked here.
     */
    public static function get(ContainerInterface $container, string $type): object
    {
        $service = $container->get($type);

        return $service instanceof $type
            ? $service
            : throw ConfigurationException::invalidService(
                $type,
                $type,
                $service,
            );
    }
}
