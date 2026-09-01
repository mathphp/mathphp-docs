# Explaining equations

`EquationAnalyzer` turns an equation into a structured explanation: original
text, known values, unknown symbols, and solving status. It deliberately keeps
partial solutions honest when a closed form is unavailable.

```php
$analysis = (new EquationAnalyzer())->analyze('x^2 + 1 = 5', ['x' => 2]);
$json = $analysis->toArray();
```

Render the model as a prompt, a hint, or an audit record. Pair it with the
[translation layer](explaining-translations.md) for learner-facing copy.

## General single-variable equations

For equations outside the closed-form linear, quadratic, and power patterns,
use `NumericalEquationAnalyzer`. It accepts any single-variable equality whose
two sides are valid Core expressions, including trigonometric, exponential,
logarithmic, polynomial, and mixed expressions:

```php
use MathPHP\Explaining\NumericalEquationAnalyzer;

$analysis = (new NumericalEquationAnalyzer())->analyze(
    'sin(x) = x / 2',
    'x',
    -8,
    8,
    samples: 512,
);

// $analysis->solutions['roots'] contains certified interval estimates.
```

The analyzer samples a finite interval, records undefined/non-finite points,
and uses bisection for sign-changing brackets. `solved` means every discovered
bracket converged without a domain gap; `partial` means roots may have been
missed or the interval contained undefined samples; `unsupported` means the
input could not be evaluated. Numerical sampling never claims a global proof
of completeness, especially for oscillatory functions or tangent roots.

Pass `EvaluationOptions` when an equation uses an explicitly registered Core
function; both sides are then evaluated through the same function registry and
resource limits as the rest of your application.

## Normalized polynomial equations

`PolynomialEquationAnalyzer` collects coefficients from the Core AST before
solving. This means equivalent algebraic forms do not need a special regular
expression:

```php
use MathPHP\Explaining\PolynomialEquationAnalyzer;

$analysis = (new PolynomialEquationAnalyzer())->analyze(
    '(x + 1) * (x - 2) = 0',
);

// roots: 2 and -1; coefficients are included in the serialized model.
```

Linear and quadratic polynomials are solved directly. For degree three and
above, the analyzer derives a Cauchy root bound and uses sampled bisection for
real roots; complex roots and proof-level completeness require a dedicated
complex/symbolic solver.

## Inequalities and larger systems

Use `InequalityAnalyzer` for bounded real relations. It returns interval
endpoints, open/closed flags, sampled points, and a `complete: false` marker so
clients do not present a finite sample as a global proof:

```php
$analysis = (new InequalityAnalyzer())->analyze('x^2 < 4', 'x', -3, 3);
// intervals: approximately (-2, 2)
```

`LinearSystemAnalyzer` generalizes the two-equation helper to arbitrary affine
systems. It uses Gaussian elimination and distinguishes a unique solution,
inconsistency, and free-variable families:

```php
$analysis = (new LinearSystemAnalyzer())->analyze(
    'x + y + z = 6; 2*x - y + z = 3; x + 2*y - z = 3',
);
// solutions['values'] contains the unique x, y, and z values.
```
