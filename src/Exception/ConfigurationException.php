<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use Throwable;

use function get_debug_type;
use function is_string;
use function sprintf;
use function var_export;

/**
 * The application configuration, or a container service it names, is not
 * usable.
 *
 * @api
 */
final class ConfigurationException extends SplInvalidArgumentException implements ExceptionInterface
{
    public static function invalidEntityClass(string $key, string $className, string $baseClass): self
    {
        return new self(sprintf(
            'Config "%s" must name an existing subclass of %s, got "%s"',
            $key,
            $baseClass,
            $className,
        ));
    }

    public static function invalidService(string $name, string $expected, mixed $service): self
    {
        return new self(sprintf('Service "%s" must be a %s, got %s', $name, $expected, get_debug_type($service)));
    }

    /**
     * A value of the right type that is out of range or malformed.
     */
    public static function invalidSetting(
        string $key,
        string $expected,
        string|int|float $value,
        ?Throwable $previous = null,
    ): self {
        return new self(
            sprintf(
                'Config "%s" must be %s, got %s',
                $key,
                $expected,
                is_string($value) ? "\"{$value}\"" : var_export($value, return: true),
            ),
            0,
            $previous,
        );
    }

    public static function invalidValue(string $key, string $expected, mixed $value): self
    {
        return new self(sprintf('Config "%s" must be %s, got %s', $key, $expected, get_debug_type($value)));
    }

    public static function unknownRepository(string $name): self
    {
        return new self(sprintf('No repository "%s" is built by this factory', $name));
    }
}
