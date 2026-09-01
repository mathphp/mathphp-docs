# Explaining calculus

`CalculusAnalyzer` represents derivative and antiderivative requests with the
expression, variable, operation, and result metadata intact.

```php
$analysis = (new CalculusAnalyzer())->analyze('x^2 + 3*x', 'derivative', 'x');
```

Derivative requests first use the compact teaching rules and then fall back to
an AST-based symbolic differentiator. Products, quotients, nested `sin`, `cos`,
`exp`, `ln`, and `sqrt` calls, constant powers, and general powers are handled
with product, quotient, chain, and logarithmic-differentiation rules:

```php
$analysis = (new CalculusAnalyzer())->derivative('x^2 * sin(x^2)');
// status: solved; product and chain-rule steps are included.
```

Operations without a generally valid derivative (for example factorial,
floor, or rounding at discontinuities) remain `partial` or `unsupported`.
The analyzer returns a symbolic expression; it does not claim a domain-wide
proof for branch-sensitive complex functions.

Antiderivatives also use an AST fallback for elementary forms such as grouped
powers, square roots, constant multiples, reciprocals, `sin`, `cos`, and
`exp`:

```php
$analysis = (new CalculusAnalyzer())->integral('sqrt(x) + 2*x');
// status: solved; an arbitrary constant C is appended.
```

Non-elementary integrals, products requiring substitution, and branch-sensitive
complex antiderivatives remain `partial` rather than being guessed.

For sampled curves, label numerical approximations and show the variable and
operation beside the formula. See [plots](visuals-plots.md) for presentation.
