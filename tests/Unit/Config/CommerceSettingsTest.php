<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Config;

use Contenir\Commerce\Config\CommerceSettings;
use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\TaxRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const NAN;

#[Group('unit')]
final class CommerceSettingsTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int|float, string, string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'an empty prefix'           => [
                '',
                'AUD',
                10,
                'GST',
                'Config "contenir_commerce.order_reference_prefix" must be a non-empty string, got ""',
            ],
            'a two-letter currency'     => [
                'LR',
                'AU',
                10,
                'GST',
                'Config "contenir_commerce.currency" must be a three-letter ISO 4217 currency code, got "AU"',
            ],
            'a four-letter currency'    => [
                'LR',
                'AUDD',
                10,
                'GST',
                'Config "contenir_commerce.currency" must be a three-letter ISO 4217 currency code, got "AUDD"',
            ],
            'a currency with a digit'   => [
                'LR',
                'AU1',
                10,
                'GST',
                'Config "contenir_commerce.currency" must be a three-letter ISO 4217 currency code, got "AU1"',
            ],
            'a currency with a newline' => [
                'LR',
                "AUD\n",
                10,
                'GST',
                "Config \"contenir_commerce.currency\" must be a three-letter ISO 4217 currency code, got \"AUD\n\"",
            ],
            'a negative rate'           => [
                'LR',
                'AUD',
                -1,
                'GST',
                'Config "contenir_commerce.tax_rate" must be a percentage from 0 to 100, got -1',
            ],
            'a rate over 100'           => [
                'LR',
                'AUD',
                100.5,
                'GST',
                'Config "contenir_commerce.tax_rate" must be a percentage from 0 to 100, got 100.5',
            ],
            'a rate that is no number'  => [
                'LR',
                'AUD',
                NAN,
                'GST',
                'Config "contenir_commerce.tax_rate" must be a percentage from 0 to 100, got NAN',
            ],
            'an empty tax label'        => [
                'LR',
                'AUD',
                10,
                '',
                'Config "contenir_commerce.tax_label" must be a non-empty string, got ""',
            ],
        ];
    }

    #[Test]
    public function aGalleryElsewhereConfiguresItsOwn(): void
    {
        $settings = new CommerceSettings('GG', 'nzd', 15, 'GST (NZ)');

        static::assertSame(
            ['GG', 'NZD', true, 'GST (NZ)'],
            [
                $settings->orderReferencePrefix,
                $settings->currency,
                $settings->taxRate->equals(TaxRate::fromPercent(15)),
                $settings->taxLabel,
            ],
        );
    }

    #[DataProvider('invalidProvider')]
    #[Test]
    public function anInvalidSettingIsRejectedNamingItsKey(
        string $prefix,
        string $currency,
        int|float $taxRate,
        string $taxLabel,
        string $message,
    ): void {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        new CommerceSettings($prefix, $currency, $taxRate, $taxLabel);
    }

    #[Test]
    public function anOutOfRangeRateKeepsTheTaxRateErrorAsItsPrevious(): void
    {
        try {
            new CommerceSettings(taxRate: 150);
            static::fail('Expected ConfigurationException');
        } catch (ConfigurationException $e) {
            static::assertSame(
                [InvalidArgumentException::class, 0],
                [$e->getPrevious() === null ? null : $e->getPrevious()::class, $e->getCode()],
            );
        }
    }

    #[Test]
    public function theDefaultsReproduceTheFirstReleaseCandidate(): void
    {
        $settings = new CommerceSettings();

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
