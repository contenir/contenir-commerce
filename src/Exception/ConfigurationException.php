<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

use function get_debug_type;
use function sprintf;

/**
 * The application configuration, or a container service it names, is not
 * usable.
 *
 * @api
 */
final class ConfigurationException extends SplInvalidArgumentException implements ExceptionInterface
{
    public static function invalidService(string $name, string $expected, mixed $service): self
    {
        return new self(sprintf('Service "%s" must be a %s, got %s', $name, $expected, get_debug_type($service)));
    }

    public static function invalidValue(string $key, string $expected, mixed $value): self
    {
        return new self(sprintf('Config "%s" must be %s, got %s', $key, $expected, get_debug_type($value)));
    }
}
