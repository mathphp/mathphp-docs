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

For sampled curves, label numerical approximations and show the variable and
operation beside the formula. See [plots](visuals-plots.md) for presentation.
