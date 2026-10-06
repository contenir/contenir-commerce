# Money and GST

`Contenir\Commerce\Money\Money` is a GST-inclusive AUD amount held as integer cents. It is immutable, never
negative, and never passes through a float.

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
| `gstComponent()` | The GST included: one eleventh, rounded to the nearest cent |
| `equals(Money)`, `isZero()` | Comparisons |
| `format()` | `$1,850.00`: thousands separators, two decimals |

## GST rounding

Prices are GST-inclusive at 10%, so an amount is eleven parts, one of them GST. The GST is
`intdiv(amount, 11)`, plus one cent when the remainder is 6 or more. One eleventh of a whole number of cents is
never exactly half a cent, so there is no tie to break and the result is exact for every amount up to
`PHP_INT_MAX`.

| Amount | GST |
| --- | --- |
| $0.05 | $0.00 |
| $0.06 | $0.01 |
| $1,850.00 | $168.18 |
| $2,830.00 | $257.27 |

An order's GST is computed once, on its total, not summed per line.

## Errors

| Exception | When |
| --- | --- |
| `Exception\InvalidArgumentException` | A negative amount, a subtraction below zero, a negative quantity |
| `Exception\OverflowException` | A sum or product beyond the integer range of cents |

Both extend the SPL exception of the same name and implement `Exception\ExceptionInterface`.

Entities store amounts as `int` cents (`ArtworkEntity::$price`, `OrderEntity::$total`, `OrderEntity::$gstAmount`,
`OrderItemEntity::$price`); `getPrice()`, `getTotal()` and `getGstAmount()` return them as `Money`.
