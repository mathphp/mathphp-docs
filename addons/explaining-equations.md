# Explaining equations

`EquationAnalyzer` turns an equation into a structured explanation: original
text, known values, unknown symbols, and solving status. It deliberately keeps
partial solutions honest when a closed form is unavailable.

The general `analyze()` entry point recognizes the original linear, quadratic,
and power teaching forms, then delegates normalized univariate polynomial
equalities (including factored and rearranged forms) to the polynomial analyzer.
Use the specialized numerical or system analyzers when the equation has a
bounded domain, multiple variables, or no polynomial closed form.

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
real roots; proof-level completeness still requires a dedicated symbolic
solver.

For degree three and above, `solutions['complexRoots']` also contains
Durand–Kerner approximations with separate `real`, `imaginary`, and
`formatted` fields. These are numerical approximations, not proof objects;
inspect the convergence metadata before presenting them as final values.

Rational equalities are solved exactly when cross-multiplication produces a
linear or quadratic polynomial. The analyzer preserves the original domain:
denominator zeros are returned in `solutions['excludedValues']` and are never
reintroduced as roots after cancellation:

```php
$analysis = (new RationalEquationAnalyzer())->analyze('1 / x = 2');
// roots: [0.5], excludedValues: [0], complete: true

$cancelled = (new RationalEquationAnalyzer())->analyze('(x^2 - 1) / (x - 1) = 0');
// roots: [-1], excludedValues: [1]
```

`EquationAnalyzer::analyze()` dispatches these rational forms automatically.
Higher-degree rational expressions remain available through bounded numerical
solving when a domain is supplied.

## Inequalities and larger systems

Use `InequalityAnalyzer` for bounded real relations. Linear and quadratic
polynomials are certified with an exact sign chart over the supplied domain;
the result has `method: exact-polynomial-sign-chart` and `complete: true`.
Rational, transcendental, and higher-degree expressions use sampled intervals
and remain `partial` when undefined points or finite sampling prevent a proof.
Every result includes interval endpoints, open/closed flags, and critical
points:

```php
$analysis = (new InequalityAnalyzer())->analyze('x^2 < 4', 'x', -3, 3);
// method: exact-polynomial-sign-chart, complete: true, intervals: (-2, 2)

$strict = (new InequalityAnalyzer())->analyze('x^2 > 0', 'x', -2, 2);
// complete: true, intervals: [-2, 0) and (0, 2]

$rational = (new RationalInequalityAnalyzer())->analyze('1 / x > 0', 'x', -1, 1);
// complete: true, intervals: (0, 1], excludedValues: [0]
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

For nonlinear multivariable equations, use `NonlinearSystemAnalyzer` with one
initial estimate per variable. Square systems use Newton's method; systems
with redundant equations use a Gauss–Newton least-squares update; and systems
with fewer equations than unknowns use a minimum-norm update:

```php
use MathPHP\Explaining\NonlinearSystemAnalyzer;

$analysis = (new NonlinearSystemAnalyzer())->analyze(
    'x^2 + y^2 = 5; x - y = 1',
    ['x', 'y'],
    ['x' => 1.5, 'y' => 0.5],
);
// solutions['values'] is approximately ['x' => 2, 'y' => 1].
```

This is a bounded Newton/Gauss–Newton iteration using finite-difference
Jacobians. The
serialized result includes each iterate and residual norm, and returns
`partial` when the Jacobian is singular, an expression leaves its domain, or
the configured iteration limit is reached. A converged starting point finds
one nearby solution; it does not establish that every solution exists or has
been found. Underdetermined systems return one minimum-norm member with
`partial` status because a free-variable or constraint-set representation is
needed for the full family. Partial differential equations, arbitrary complex systems, and
global piecewise/discontinuous proofs remain outside this general-purpose
numeric analyzer and are reported as unsupported or partial.

When a system may have several nearby roots, call `analyzeMany()` with several
initial maps. It deduplicates converged values but keeps failed or partial runs
so callers can show which starting points were inconclusive.

## First-order linear ODEs

`DifferentialEquationAnalyzer` handles constant-coefficient first-order
ordinary differential equations and optional initial conditions:

```php
use MathPHP\Explaining\DifferentialEquationAnalyzer;

$analysis = (new DifferentialEquationAnalyzer())->analyze(
    "y' = 2*y + 3",
    dependent: 'y',
    independent: 'x',
    initialIndependent: 0,
    initialValue: 1,
);
// solution['general'] and solution['particular'] contain the closed forms.
```

Equivalent `y' + p*y = q` and `dy/dx = a*y + b` notation is accepted. Nonlinear,
higher-order, and partial differential equations are deliberately reported as
unsupported until a solver with the required domain and boundary-condition
semantics is added.

For nonlinear first-order initial-value problems, use `NumericalOdeAnalyzer`:

```php
use MathPHP\Explaining\NumericalOdeAnalyzer;

$analysis = (new NumericalOdeAnalyzer())->analyze(
    "y' = sin(x) + y",
    dependent: 'y',
    independent: 'x',
    initialIndependent: 0,
    initialValue: 1,
    targetIndependent: 2,
    steps: 200,
);
// solution['points'] contains the complete RK4 trajectory.
```

The integration is intentionally bounded and numerical. It does not claim a
closed form or global stability; undefined slopes and non-finite states return
`partial` with the successfully integrated prefix.

Coupled equations can be integrated as a first-order system. This is also the
usual representation for higher-order ODEs:

```php
use MathPHP\Explaining\NumericalOdeSystemAnalyzer;

$analysis = (new NumericalOdeSystemAnalyzer())->analyze(
    "x' = v; v' = -x",
    variables: ['x', 'v'],
    independent: 't',
    initial: ['x' => 1, 'v' => 0],
    initialIndependent: 0,
    targetIndependent: pi() / 2,
    steps: 200,
);
// solution['final']['values'] contains x ≈ 0 and v ≈ -1.
```

Every component is evaluated at the same RK4 intermediate state. The result
contains the complete vector trajectory and remains numerical rather than
claiming a symbolic solution.

For exact constant-coefficient second-order equations, use
`SecondOrderOdeAnalyzer`:

```php
use MathPHP\Explaining\SecondOrderOdeAnalyzer;

$analysis = (new SecondOrderOdeAnalyzer())->analyze(
    "y'' + 3*y' + 2*y = 0",
    dependent: 'y',
    independent: 'x',
    initialIndependent: 0,
    initialValue: 1,
    initialDerivative: 0,
);
// Distinct real characteristic roots -1 and -2 are reported with C1/C2.
```

The analyzer also handles repeated and complex-conjugate characteristic roots,
plus constant forcing. More complicated higher-order equations should be
rewritten as first-order systems and passed to the numerical system analyzer.
