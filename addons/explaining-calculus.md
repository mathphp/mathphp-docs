# Explaining calculus

`CalculusAnalyzer` represents derivative and antiderivative requests with the
expression, variable, operation, and result metadata intact.

```php
$analysis = (new CalculusAnalyzer())->analyze('x^2 + 3*x', 'derivative', 'x');
```

Derivative requests first use the compact teaching rules and then fall back to
an AST-based symbolic differentiator. Products, quotients, nested elementary
functions (`sin`, `cos`, `tan`, `sec`, `csc`, `cot`, `sech`, `csch`, `coth`, `cbrt`, inverse trigonometric, hyperbolic,
inverse-hyperbolic, `exp`, `ln`, `log10`, and `sqrt`), constant powers, and
general powers are handled with product, quotient, chain, and
logarithmic-differentiation rules:

Core's gamma-family identities are included where the derivative remains in
the public registry: `gamma(u)` differentiates to
`gamma(u)·digamma(u)·u′`, `lgamma(u)` to `digamma(u)·u′`, and `digamma(u)` to
`trigamma(u)·u′`. The elementary antiderivative of `digamma(u)` is
`ln(abs(gamma(u)))` on each interval that avoids a gamma pole.
For two-argument beta functions, the differentiator applies
`d logbeta(a,b) = digamma(a)·a′ + digamma(b)·b′ − digamma(a+b)·(a′+b′)`;
`beta(a,b)` multiplies that logarithmic derivative by `beta(a,b)`.
For fixed integer order, Bessel derivatives use the standard recurrences
`Jₙ′(u) = (Jₙ₋₁(u) − Jₙ₊₁(u))/2` and
`Iₙ′(u) = (Iₙ₋₁(u) + Iₙ₊₁(u))/2`; variable-order derivatives remain partial.
Lambert-W derivatives use implicit differentiation of
`Wₖ(u)·exp(Wₖ(u)) = u`: `d/dx Wₖ(u) =
Wₖ(u)·u′/(u·(1 + Wₖ(u)))`. The principal `lambertw(u)` and lower real
`lambertw(u, -1)` branches are supported when the branch selector is constant;
a varying branch selector remains partial. Expressions are still subject to
Core's real-domain and branch-point restrictions.
The Gaussian identities are also symbolic: `erf′(u) =
2·exp(−u²)/sqrt(pi)·u′` and `erfc′(u) = −erf′(u)`. Their elementary
antiderivatives are `u·erf(u) + exp(−u²)/sqrt(pi)` and
`u·erfc(u) − exp(−u²)/sqrt(pi)`, respectively.
The imaginary error function uses `erfi′(u) =
2·exp(u²)/sqrt(pi)·u′`. Its inverse companions use the corresponding
inverse-function identities:
`erfinv′(u) = sqrt(pi)/2·exp(erfinv(u)²)·u′` and
`erfcinv′(u) = −sqrt(pi)/2·exp(erfcinv(u)²)·u′`; these are documented as
valid only on their open real input domains.
For fixed integer order, the second-kind Bessel functions use
`Yₙ′(u) = (Yₙ₋₁(u) − Yₙ₊₁(u))/2` and
`Kₙ′(u) = −(Kₙ₋₁(u) + Kₙ₊₁(u))/2`. Variable-order derivatives remain partial.
For a fixed non-negative integer order, `polygamma(n,u)` follows the family
identity `d/du polygamma(n,u) = polygamma(n+1,u)` and then applies the chain
rule. A variable order is retained as a partial result.
The stable logarithmic/exponential helpers are covered as well:
`log2′(u) = u′/(u·ln(2))`, `log1p′(u) = u′/(1+u)`, and
`expm1′(u) = exp(u)·u′`, with their corresponding affine antiderivatives.
The normalized cardinal functions use their quotient-rule derivatives:
`sinc′(u) = (u·cos(u) − sin(u))/u² · u′` and
`sinhc′(u) = (u·cosh(u) − sinh(u))/u² · u′`. Both formulas have a removable
limit at `u = 0`; the explaining steps call this out so consumers evaluate the
origin by continuity. Affine arguments such as `sinc(2*x + 1)` and
`sinhc(2*x + 1)` include the corresponding chain-rule factor.
For two-argument functions, `atan2(y,x)` uses
`(x·y′ − y·x′)/(x²+y²)` and `hypot(a,b)` uses
`(a·a′ + b·b′)/hypot(a,b)`; both remain undefined at their zero denominator.
Inverse reciprocal trigonometric functions are also covered on their real
domains: `asec′(u) = u′/(|u|sqrt(u²−1))`, `acsc′(u) =
−u′/(|u|sqrt(u²−1))`, and `acot′(u) = −u′/(1+u²)`.
The inverse hyperbolic reciprocals follow their real branches too:
`asech′(u) = −u′/(u·sqrt(1−u²))`, `acsch′(u) =
−u′/(|u|sqrt(1+u²))`, and `acoth′(u) = u′/(1−u²)`.

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
inverse-trigonometric functions, hyperbolic functions, `exp`, and `log10`.
Affine substitutions are supported for forms such as `sin(2*x + 1)`,
`(2*x + 1)^3`, and the reciprocal logarithmic exception:

```php
$analysis = (new CalculusAnalyzer())->integral('sqrt(x) + 2*x');
// status: solved; an arbitrary constant C is appended.
```

Bounded integration by parts also covers polynomial products with affine
`sin`, `cos`, `sinh`, `cosh`, and `exp` factors, such as `x*exp(x)` and
`x^2*cos(2*x)`, up to degree eight. Shifted polynomial factors such as
`(x + 1)^2*sin(x)` are expanded before applying the recurrence. Other product
forms remain explicit `partial` results.

Non-elementary integrals, products outside the recognized monomial-power
substitution patterns, and branch-sensitive complex antiderivatives remain
`partial` rather than being guessed.

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
