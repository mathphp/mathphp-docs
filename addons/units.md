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
$result->toArray(); // value, displayValue, unit, dimensions, formatted
```

The default catalog includes length (`m`, `cm`, `mm`, `km`, `in`, `ft`, `yd`,
`mi`), mass (`kg`, `g`, `mg`, `lb`, `oz`), time (`s`, `ms`, `min`, `h`, `d`),
temperature (`K`, `C`, `F`), angle (`rad`, `deg`), square/cubic lengths,
litres, and common speeds (`mps`, `kmh`, `mph`). Spelled aliases and separated
forms such as `metres`, `liters`, `metres per second`, and `m/s` are accepted.
The short symbols `m` (metre) and `min` (minute) remain distinct.

## Dimensional arithmetic

Values are converted to their base unit before addition or subtraction. The
result keeps the left-hand display unit when possible:

```php
UnitMath::evaluate('2m + 200cm')->format(); // 4 m
UnitMath::evaluate('60km / 2h')->format();  // 8.33333333333 m/s
UnitMath::evaluate('3m ^ 2')->format();     // 9 m^2
UnitMath::evaluate('1L + 500mL')->format();  // 1.5 L
UnitMath::evaluate('25m to km')->format();   // 0.025 km
UnitMath::evaluate('1 mile per hour to km/h')->format(); // 1.609344 kmh
```

Use `to` when the output needs a specific compatible display unit. Conversion
changes the display unit while preserving the normalized `value`:

```php
$distance = UnitMath::evaluate('25m to km');
$distance->value;           // 25.0 (base metres)
$distance->displayValue();  // 0.025 (display kilometres)
$distance->format();        // 0.025 km
```

Adding metres to seconds raises `UnitException` with
`units.incompatible_addition`. Other stable codes cover unknown units,
division by zero, invalid exponents, and malformed input. The exception also
includes the source position so an editor can highlight the failing token.

## Custom catalogs and variables

Register domain units without modifying the package defaults:

```php
use MathPHP\Units\UnitCatalog;
use MathPHP\Units\UnitDefinition;
use MathPHP\Units\UnitMath;

$catalog = UnitCatalog::default()->register(
    new UnitDefinition('px', 'pixel', ['length' => 1], 0.000264583),
);

$result = UnitMath::evaluate('width + 10px', [
    'width' => UnitMath::evaluate('1m'),
], $catalog);
```

Variables may be scalar numbers or `Quantity` objects. Keep quantities in your
API response by using `toArray()`; it contains the normalized base `value`, the
selected unit's `displayValue`, dimensions, and a human-readable `formatted`
label.

## Explaining and visuals

The private explaining package exposes `UnitExplainer`, which emits the same
quantity results as translated steps (`en` and `da`). The private visuals
package exposes `UnitLabels::quantity()` and `UnitLabels::axis()` for chart
legends and axes. Both integrations are optional; applications can use the
units package alone.

## Distribution status

This package is private and uses the MathPHP Commercial Add-on License. The
long-term payment, sponsorship, account-access, and repository-distribution
workflow is intentionally undecided and is not automated by this project.
Until that model is chosen, treat the repository and license text as the
authoritative distribution boundary; do not redistribute the source or remove
MathPHP branding and notices.
