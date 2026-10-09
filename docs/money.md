# Money and tax

`Contenir\Commerce\Money\Money` is a tax-inclusive amount held as integer cents, in the site's one currency (AUD
unless [configured](configuration.md) otherwise; `Money` does not carry it). It is immutable, never negative, and
never passes through a float.

```php
use Contenir\Commerce\Money\Money;

$price = Money::fromCents(185_000);   // or new Money(185_000)
$total = $price->add(Money::fromCents(98_000));

$total->amount;                 // 283000
$total->gstComponent()->amount; // 25727
$total->format();               // "$2,830.00"
```

| Method | Returns |
| --- | --- |
| `fromCents(int)`, `zero()` | A new amount |
| `add(Money)`, `subtract(Money)`, `multiply(int $quantity)` | A new amount |
| `gstComponent(?TaxRate $rate = null)` | The tax included at `$rate` (10% GST when null), rounded half up to the nearest cent |
| `equals(Money)`, `isZero()` | Comparisons |
| `format()` | `$1,850.00`: thousands separators, two decimals |

## Tax rates

`Money\TaxRate` holds a rate as an integer number of parts per million of the pre-tax amount: 10% is 100,000.

```php
use Contenir\Commerce\Money\TaxRate;

TaxRate::gst();                  // 10%, the default
TaxRate::fromPercent(15);        // 150,000 parts per million
TaxRate::fromPercent(12.5);      // 125,000
$rate->partsPerMillion;
$rate->equals(TaxRate::gst());
```

`fromPercent()` accepts an int or float from 0 to 100 and throws `InvalidArgumentException` otherwise (including
`NAN` and `INF`). A percentage is honoured to four decimal places; a finer one is rounded to the nearest part per
million. The configured rate is `CommerceSettings::$taxRate`.

```php
$total->gstComponent();                         // 10% GST
$total->gstComponent($settings->taxRate);       // the configured rate
$total->gstComponent(TaxRate::fromPercent(20)); // 20% VAT
```

## Rounding

Prices include the tax, so with the rate p in parts per million the tax in an amount is

    amount × p / (1,000,000 + p)

rounded **half up** to the nearest cent: a remainder of exactly half a cent rounds up, anything less rounds down. The
calculation is exact integer arithmetic for every amount up to `PHP_INT_MAX`: the amount is split into whole multiples
of 1,000,000 + p and a remainder, so no intermediate product can overflow.

At the default 10% the tax is one eleventh of the amount, the same as RC1: `intdiv(amount, 11)`, plus one cent when
the remainder is 6 or more. One eleventh of a whole number of cents is never exactly half a cent, so there is no tie.
Other rates can tie (20% of a 3-cent price is half a cent), and ties round up.

| Amount | Rate | Tax |
| --- | --- | --- |
| $0.05 | 10% | $0.00 |
| $0.06 | 10% | $0.01 |
| $1,850.00 | 10% | $168.18 |
| $2,830.00 | 10% | $257.27 |
| $1.15 | 15% | $0.15 |
| $0.03 | 20% | $0.01 (a tie, rounded up) |
| $0.03 | 100% | $0.02 (a tie, rounded up) |
| any | 0% | $0.00 |

An order's tax is computed once, on its total, not summed per line, and stored in `gstAmount` (the column keeps its
name whatever the tax is called).

## Errors

| Exception | When |
| --- | --- |
| `Exception\InvalidArgumentException` | A negative amount, a subtraction below zero, a negative quantity, a tax rate outside 0 to 100 |
| `Exception\OverflowException` | A sum or product beyond the integer range of cents |

Both extend the SPL exception of the same name and implement `Exception\ExceptionInterface`.

Entities store amounts as `int` cents (`ItemVariantEntity::$price`, `OrderEntity::$total`, `OrderEntity::$gstAmount`,
`OrderItemEntity::$unitPrice`); `getPrice()`, `getUnitPrice()`, `getTotal()` and `getGstAmount()` return them as
`Money`.
