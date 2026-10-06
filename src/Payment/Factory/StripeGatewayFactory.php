<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment\Factory;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Container\ServiceLocator;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Commerce\Payment\StripeGateway;
use Contenir\Commerce\Payment\UnconfiguredGateway;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Stripe\StripeClient;

use function is_array;
use function is_string;

/**
 * Builds the Stripe gateway from "stripe.secret_key", in the currency of
 * the CommerceSettings service, or the
 * UnconfiguredGateway when no key is set, so a site without Stripe
 * credentials still boots and fails only when money would move.
 *
 * @api
 */
final class StripeGatewayFactory
{
    /**
     * @throws ConfigurationException When the stripe config is not an array or the key is not a string.
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The config service is untyped; its shape is checked here.
     */
    public function __invoke(ContainerInterface $container): PaymentGatewayInterface
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $stripe = is_array($config) ? $config['stripe'] ?? [] : [];
        if (! is_array($stripe)) {
            throw ConfigurationException::invalidValue('stripe', 'an array', $stripe);
        }

        $secretKey = $stripe['secret_key'] ?? '';
        if (! is_string($secretKey)) {
            throw ConfigurationException::invalidValue('stripe.secret_key', 'a string', $secretKey);
        }

        if ('' === $secretKey) {
            return new UnconfiguredGateway();
        }

        return new StripeGateway(
            new StripeClient($secretKey),
            ServiceLocator::get($container, ClockInterface::class),
            ServiceLocator::get($container, CommerceSettings::class)->currency,
        );
    }
}
