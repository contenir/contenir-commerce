<?php

declare(strict_types=1);

namespace Contenir\Commerce;

/**
 * laminas-mvc module: exposes the ConfigProvider services under the
 * "service_manager" key that laminas-mvc reads. Mezzio applications use
 * ConfigProvider directly.
 *
 * @api
 */
final class Module
{
    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return ['service_manager' => (new ConfigProvider())->getDependencies()];
    }
}
