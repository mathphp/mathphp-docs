# Units add-on

`mathphp/mathphp-units` is a private, opt-in package for calculations where a
number needs a measurement. It adds a quantity grammar without changing the
scalar-only `mathphp/mathphp` evaluator.

## Install and evaluate

```bash
composer require mathphp/mathphp-units
```

```php
use MathPHP\Units\UnitMath;

$result = UnitMath::evaluate('2m * 6 + 200cm');

$result->format(); // 14 m
$result->toArray(); // value, unit, dimensions, formatted
```

The default catalog includes length (`m`, `cm`, `mm`, `km`, `in`, `ft`, `yd`,
`mi`), mass (`kg`, `g`, `mg`, `lb`, `oz`), time (`s`, `ms`, `min`, `h`, `d`),
temperature (`K`, `C`, `F`), and angle (`rad`, `deg`).

## Dimensional arithmetic

Values are converted to their base unit before addition or subtraction. The
result keeps the left-hand display unit when possible:

```php
UnitMath::evaluate('2m + 200cm')->format(); // 4 m
UnitMath::evaluate('60km / 2h')->format();  // 8.33333333333 m/s
UnitMath::evaluate('3m ^ 2')->format();     // 9 m^2
```

Adding metres to seconds raises `UnitException` with
`units.incompatible_addition`. Other stable codes cover unknown units,
division by zero, invalid exponents, and malformed input. The exception also
includes the source position so an editor can highlight the failing token.

## Custom catalogs and variables

Register domain units without modifying the package defaults:

```php
$catalog = UnitCatalog::default()->register(
    new UnitDefinition('px', 'pixel', ['length' => 1], 0.000264583),
);

$result = UnitMath::evaluate('width + 10px', [
    'width' => UnitMath::evaluate('1m'),
], $catalog);
```

Variables may be scalar numbers or `Quantity` objects. Keep quantities in your
API response by using `toArray()`; it contains both the normalized value and a
human-readable label.

## Explaining and visuals

The private explaining package exposes `UnitExplainer`, which emits the same
quantity results as translated steps (`en` and `da`). The private visuals
package exposes `UnitLabels::quantity()` and `UnitLabels::axis()` for chart
legends and axes. Both integrations are optional; applications can use the
units package alone.

## Distribution

This package is distributed under the MathPHP Commercial Add-on License. Access
is granted to named GitHub accounts while a sponsorship or license is active.
An obtained copy may remain in use after access ends, but repository access and
updates stop when the license ends. Do not redistribute the source or remove
MathPHP branding and notices.
