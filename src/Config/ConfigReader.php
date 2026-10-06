<?php

declare(strict_types=1);

namespace Contenir\Commerce\Config;

use Contenir\Commerce\Exception\ConfigurationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function is_subclass_of;

/**
 * Reads one section of the application config ("contenir_commerce" by
 * default). An absent or null key takes its default; a key that is present
 * with a value of the wrong type is an error, never silently ignored.
 *
 * @internal
 */
final readonly class ConfigReader
{
    public const string SECTION = 'contenir_commerce';

    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(
        private string $section,
        private array $values,
    ) {}

    /**
     * @throws ConfigurationException When the section is present but not an array.
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The config service is untyped; its shape is checked here.
     */
    public static function fromContainer(ContainerInterface $container, string $section = self::SECTION): self
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $values = is_array($config) ? $config[$section] ?? [] : [];

        return is_array($values)
            ? new self($section, $values)
            : throw ConfigurationException::invalidValue(
                $section,
                'an array',
                $values,
            );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $baseClass
     * @param class-string<T> $default
     *
     * @return class-string<T>
     *
     * @throws ConfigurationException When the value does not name an existing subclass of $baseClass.
     */
    public function className(string $key, string $baseClass, string $default): string
    {
        $value = $this->string($key, $default);
        if (is_subclass_of($value, $baseClass)) {
            return $value;
        }

        throw ConfigurationException::invalidEntityClass($this->key($key), $value, $baseClass);
    }

    /**
     * An integer or float; a numeric string is not a number.
     *
     * @throws ConfigurationException When the value is not an integer or float.
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; the type is checked here.
     */
    public function number(string $key, int|float $default): int|float
    {
        $value = $this->values[$key] ?? $default;
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        throw ConfigurationException::invalidValue($this->key($key), 'a number', $value);
    }

    /**
     * @throws ConfigurationException When the value is not a string.
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; the type is checked here.
     */
    public function string(string $key, string $default): string
    {
        $value = $this->values[$key] ?? $default;
        if (is_string($value)) {
            return $value;
        }

        throw ConfigurationException::invalidValue($this->key($key), 'a string', $value);
    }

    private function key(string $key): string
    {
        return "{$this->section}.{$key}";
    }
}
