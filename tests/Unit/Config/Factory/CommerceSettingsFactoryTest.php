<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Config\Factory;

use Contenir\Commerce\Config\Factory\CommerceSettingsFactory;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Money\TaxRate;
use Contenir\Commerce\Tests\TestAsset\Container\ArrayContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CommerceSettingsFactoryTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function mistypedProvider(): array
    {
        return [
            'a prefix that is no string'   => [
                ['order_reference_prefix' => 7],
                'Config "contenir_commerce.order_reference_prefix" must be a string, got int',
            ],
            'a currency that is no string' => [
                ['currency' => ['AUD']],
                'Config "contenir_commerce.currency" must be a string, got array',
            ],
            'a numeric-string rate'        => [
                ['tax_rate' => '10'],
                'Config "contenir_commerce.tax_rate" must be a number, got string',
            ],
            'a boolean rate'               => [
                ['tax_rate' => true],
                'Config "contenir_commerce.tax_rate" must be a number, got bool',
            ],
            'a label that is no string'    => [
                ['tax_label' => false],
                'Config "contenir_commerce.tax_label" must be a string, got bool',
            ],
            'an out-of-range value'        => [
                ['currency' => 'dollars'],
                'Config "contenir_commerce.currency" must be a three-letter ISO 4217 currency code, got "dollars"',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $commerce
     */
    #[DataProvider('mistypedProvider')]
    #[Test]
    public function aMistypedValueIsRejected(array $commerce, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new CommerceSettingsFactory())(new ArrayContainer(['config' => ['contenir_commerce' => $commerce]]));
    }

    #[Test]
    public function aNullValueTakesTheDefault(): void
    {
        $settings = (new CommerceSettingsFactory())(new ArrayContainer([
            'config' => ['contenir_commerce' => [
                'tax_rate' => null,
            ]],
        ]));

        static::assertSame(100_000, $settings->taxRate->partsPerMillion);
    }

    #[Test]
    public function readsEveryKey(): void
    {
        $settings = (new CommerceSettingsFactory())(new ArrayContainer([
            'config' => ['contenir_commerce' => [
                'order_reference_prefix' => 'GG',
                'currency'               => 'gbp',
                'tax_rate'               => 17.5,
                'tax_label'              => 'VAT',
            ]],
        ]));

        static::assertSame(
            ['GG', 'GBP', true, 'VAT'],
            [
                $settings->orderReferencePrefix,
                $settings->currency,
                $settings->taxRate->equals(TaxRate::fromPercent(17.5)),
                $settings->taxLabel,
            ],
        );
    }

    #[Test]
    public function withoutConfigTheDefaultsApply(): void
    {
        $settings = (new CommerceSettingsFactory())(new ArrayContainer([]));

        static::assertSame(
            ['LR', 'AUD', 100_000, 'GST'],
            [
                $settings->orderReferencePrefix,
                $settings->currency,
                $settings->taxRate->partsPerMillion,
                $settings->taxLabel,
            ],
        );
    }
}
