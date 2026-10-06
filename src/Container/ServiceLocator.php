<?php

declare(strict_types=1);

namespace Contenir\Commerce\Container;

use Contenir\Commerce\Exception\ConfigurationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Fetches a container service, by its type or by a configured name, and
 * checks its type.
 *
 * @internal
 */
final class ServiceLocator
{
    /**
     * @template T of object
     *
     * @param class-string<T> $type
     * @param string|null     $name the service name, when it is not the type
     *
     * @return T
     *
     * @throws ConfigurationException When the service is not a $type.
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; the type is checked here.
     */
    public static function get(ContainerInterface $container, string $type, ?string $name = null): object
    {
        $name    ??= $type;
        $service = $container->get($name);

        return $service instanceof $type
            ? $service
            : throw ConfigurationException::invalidService(
                $name,
                $type,
                $service,
            );
    }
}
