<?php

declare(strict_types=1);

namespace Contenir\Commerce\Config\Factory;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Config\ConfigReader;
use Contenir\Commerce\Exception\ConfigurationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the CommerceSettings from "contenir_commerce": the
 * "order_reference_prefix", "currency", "tax_rate" and "tax_label" keys,
 * each optional.
 *
 * @api
 */
final class CommerceSettingsFactory
{
    /**
     * @throws ConfigurationException When a value has the wrong type, or is empty, malformed or out of range.
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CommerceSettings
    {
        $config = ConfigReader::fromContainer($container);

        return new CommerceSettings(
            $config->string('order_reference_prefix', CommerceSettings::DEFAULT_ORDER_REFERENCE_PREFIX),
            $config->string('currency', CommerceSettings::DEFAULT_CURRENCY),
            $config->number('tax_rate', CommerceSettings::DEFAULT_TAX_RATE),
            $config->string('tax_label', CommerceSettings::DEFAULT_TAX_LABEL),
        );
    }
}
