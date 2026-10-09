<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order\Factory;

use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Order\ItemInventory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final class ItemInventoryFactory
{
    /**
     * @throws ConfigurationException When a repository service is missing or the wrong type.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ItemInventory
    {
        return new ItemInventory(
            ServiceLocator::get($container, ItemRepository::class),
            ServiceLocator::get($container, ItemVariantRepository::class),
        );
    }
}
