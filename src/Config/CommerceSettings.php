<?php

declare(strict_types=1);

namespace Contenir\Commerce\Config;

use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Money\TaxRate;

use function preg_match;
use function strtoupper;

/**
 * The site's commerce settings, from the "contenir_commerce" config key:
 * the order reference prefix, the currency, and the rate and label of the
 * tax included in every price. The defaults are "ORD", AUD and 10% GST;
 * a site sets its own prefix.
 *
 * Each value is checked here, whether it comes from config or from code,
 * and an invalid one throws ConfigurationException naming its config key.
 *
 * @api
 */
final readonly class CommerceSettings
{
    public const string DEFAULT_ORDER_REFERENCE_PREFIX = 'ORD';

    public const string DEFAULT_CURRENCY = 'AUD';

    public const int DEFAULT_TAX_RATE = 10;

    public const string DEFAULT_TAX_LABEL = 'GST';

    /**
     * The ISO 4217 code, in upper case. Stripe receives it in lower case.
     */
    public string $currency;

    public TaxRate $taxRate;

    /**
     * @param string    $orderReferencePrefix references read "<prefix>-<year>-<id, 4 digits>"
     * @param string    $currency             a three-letter ISO 4217 code, in either case
     * @param int|float $taxRate              the percentage of tax included in prices, 0 to 100
     * @param string    $taxLabel             the tax's name for display, such as "GST" or "VAT"
     *
     * @throws ConfigurationException When a value is empty, malformed or out of range.
     */
    public function __construct(
        public string $orderReferencePrefix = self::DEFAULT_ORDER_REFERENCE_PREFIX,
        string $currency = self::DEFAULT_CURRENCY,
        int|float $taxRate = self::DEFAULT_TAX_RATE,
        public string $taxLabel = self::DEFAULT_TAX_LABEL,
    ) {
        if ('' === $orderReferencePrefix) {
            throw ConfigurationException::invalidSetting(
                ConfigReader::SECTION . '.order_reference_prefix',
                'a non-empty string',
                $orderReferencePrefix,
            );
        }

        if (1 !== preg_match('/^[A-Za-z]{3}$/D', $currency)) {
            throw ConfigurationException::invalidSetting(
                ConfigReader::SECTION . '.currency',
                'a three-letter ISO 4217 currency code',
                $currency,
            );
        }

        if ('' === $taxLabel) {
            throw ConfigurationException::invalidSetting(
                ConfigReader::SECTION . '.tax_label',
                'a non-empty string',
                $taxLabel,
            );
        }

        try {
            $this->taxRate = TaxRate::fromPercent($taxRate);
        } catch (InvalidArgumentException $e) {
            throw ConfigurationException::invalidSetting(
                ConfigReader::SECTION . '.tax_rate',
                'a percentage from 0 to 100',
                $taxRate,
                $e,
            );
        }

        $this->currency = strtoupper($currency);
    }
}
