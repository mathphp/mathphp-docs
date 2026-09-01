# Explaining calculus

`CalculusAnalyzer` represents derivative and antiderivative requests with the
expression, variable, operation, and result metadata intact.

```php
$analysis = (new CalculusAnalyzer())->analyze('x^2 + 3*x', 'derivative', 'x');
```

Derivative requests first use the compact teaching rules and then fall back to
an AST-based symbolic differentiator. Products, quotients, nested elementary
functions (`sin`, `cos`, `tan`, `sec`, `csc`, `cot`, `cbrt`, inverse trigonometric, hyperbolic,
inverse-hyperbolic, `exp`, `ln`, `log10`, and `sqrt`), constant powers, and
general powers are handled with product, quotient, chain, and
logarithmic-differentiation rules:

```php
$analysis = (new CalculusAnalyzer())->derivative('x^2 * sin(x^2)');
// status: solved; product and chain-rule steps are included.
```

Operations without a generally valid derivative (for example factorial,
floor, or rounding at discontinuities) remain `partial` or `unsupported`.
The analyzer returns a symbolic expression; it does not claim a domain-wide
proof for branch-sensitive complex functions.

Antiderivatives also use an AST fallback for elementary forms such as grouped
powers, square roots, constant multiples, reciprocals, trigonometric and
inverse-trigonometric functions, hyperbolic functions, `exp`, and `log10`:

```php
$analysis = (new CalculusAnalyzer())->integral('sqrt(x) + 2*x');
// status: solved; an arbitrary constant C is appended.
```

Non-elementary integrals, products requiring substitution, and branch-sensitive
complex antiderivatives remain `partial` rather than being guessed.

Variable arguments use Core's portable aliases. An expression containing `α`
can be analyzed by passing `α` as the variable; both are normalized to the
ASCII identifier `alpha` internally.

## Complex-valued expressions

Use `ComplexExpressionEvaluator` when the expression itself is complex. The
real evaluator is intentionally unchanged:

```php
use MathPHP\Explaining\ComplexExpressionEvaluator;

$value = (new ComplexExpressionEvaluator())->evaluate('exp(i * pi) + sqrt(-1)');
// $value is a ComplexNumber with explicit real and imaginary components.
```

The evaluator supports Core arithmetic, the imaginary unit `i`, principal
branches for `sqrt`, `exp`, `ln`, `sin`, `cos`, `asin`, `acos`, `atan`,
`sinh`, `cosh`, `tanh`, `asinh`, `acosh`, `atanh`, and two-argument `log`.
Modulo, factorial of non-real values, and unsupported custom functions are
rejected explicitly rather than coerced to real numbers.

For a complex equality, `ComplexEquationAnalyzer` applies bounded Newton
iteration from a caller-supplied complex starting value:

```php
use MathPHP\Explaining\ComplexEquationAnalyzer;
use MathPHP\Explaining\ComplexNumber;

$analysis = (new ComplexEquationAnalyzer())->analyze(
    'z^2 + 1 = 0',
    initial: new ComplexNumber(0.6, 0.6),
);
// solution['root'] contains one nearby root and its residual history.
```

This is a local numerical method. Different starting values can converge to
different roots, and singular or non-convergent runs are reported as `partial`.

For sampled curves, label numerical approximations and show the variable and
operation beside the formula. See [plots](visuals-plots.md) for presentation.
