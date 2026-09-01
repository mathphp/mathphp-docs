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

## Conditional and piecewise expressions

For a numeric result selected by conditions, use `PiecewiseEvaluator`. It
evaluates conditions from top to bottom, evaluates only the selected branch,
and returns the branch number plus ordered explanation steps:

```php
use MathPHP\Explaining\PiecewiseEvaluator;
use MathPHP\Explaining\PiecewiseEquationAnalyzer;

$result = (new PiecewiseEvaluator())->explain(
    'piecewise(x < 0: -x; otherwise: x)',
    ['x' => -3],
);

// $result->value === 3
// $result->branch === 1
// $result->toArray()['steps'] describes the selection.
```

The `if(condition, whenTrue, whenFalse)` shorthand is also accepted. Conditions
support `<`, `<=`, `>`, `>=`, `=`, `!=`, numeric truthiness, and bounded `and`,
`or`, and `not` combinations. Up to 64 branches and 100,000 source characters
are accepted; branch values still use Core's scalar grammar, domains, and
resource limits. A missing match is a `DomainException`, not a guessed value.
Piecewise selection is an evaluation feature, not a global proof of continuity,
limits, or every possible solution of a discontinuous equation.

To find roots of a piecewise equality on a finite interval, use
`PiecewiseEquationAnalyzer`:

```php
$analysis = (new PiecewiseEquationAnalyzer())->analyze(
    'piecewise(x < 0: -x; otherwise: x) = 3',
    'x',
    -5,
    5,
);
// roots are approximately -3 and 3; status is partial because the branch
// transition is observed and no finite sample can prove global completeness.
```

The solver only bisects sign changes that remain within the same selected
branch. A jump, undefined sample, or missing root evidence is reported as
`partial`; a branch discontinuity is never presented as a zero.

Piecewise wrappers can also be nested inside ordinary arithmetic, for example
`2 * piecewise(x < 0: -x; otherwise: x) + 1`. Nested wrappers are resolved
inside-out and retain the same domain and resource limits.

## Discrete recurrences

`RecurrenceAnalyzer` expands a finite sequence from supplied initial values.
Both function and bracket notation are accepted, and prior terms may be used
in the right-hand side:

```php
use MathPHP\Explaining\RecurrenceAnalyzer;

$sequence = (new RecurrenceAnalyzer())->analyze(
    'u[n+2] = u[n+1] + u[n]',
    [0 => 0, 1 => 1],
    terms: 8,
);
// sequence: 0, 1, 1, 2, 3, 5, 8, 13
```

The analyzer performs bounded forward substitution and can use Core functions
of `n` for forcing terms. Forward references, missing initial values, domain
errors, and requests beyond the finite term limit return `unsupported` or
`partial`; no infinite sequence or closed form is implied.

## Finite numerical limits

`LimitAnalyzer` estimates a finite one-sided or two-sided limit by evaluating
geometrically shrinking distances from a finite point. It is intentionally a
numerical aid for explanations, not a symbolic algebra system or a proof:

```php
use MathPHP\Explaining\LimitAnalyzer;

$analysis = (new LimitAnalyzer())->analyze(
    'sin(x) / x',
    'x',
    point: 0,
    direction: 'both',
);

// status: solved; limit is approximately 1
// $analysis->solution['complete'] === false
```

Use `direction: 'left'` or `direction: 'right'` for a one-sided estimate.
Undefined samples, side disagreement, non-finite values, and non-convergence
remain explicit `partial` results. The analyzer preserves sampled points and
steps in `LimitAnalysis::toArray()` and includes a renderer-neutral
`limit-approach` visual model. It does not certify a symbolic limit, infer a
value through a removable hole without samples, or prove divergence/global
continuity.

## Chained inequalities

`InequalityAnalyzer` also accepts a bounded chained relation such as
`1 < x ≤ 3`. It analyzes both component relations and intersects their
intervals, preserving whether each endpoint is open or closed:

```php
$analysis = (new InequalityAnalyzer())->analyze('1 < x ≤ 3', 'x', -5, 5);
// one solved interval: (1, 3]
```

The result is still scoped to the requested finite domain. If either component
contains undefined samples or a non-polynomial relation, the combined result
keeps the appropriate `partial` status.

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
uses bisection for sign-changing brackets, and applies guarded Newton
refinement to finite local minima so repeated roots can be detected. `solved` means every discovered
bracket converged without a domain gap; `partial` means roots may have been
missed or the interval contained undefined samples; `unsupported` means the
input could not be evaluated. Numerical sampling never claims a global proof
of completeness, especially for oscillatory functions or tangent roots.

Pass `EvaluationOptions` when an equation uses an explicitly registered Core
function; both sides are then evaluated through the same function registry and
resource limits as the rest of your application.

## Numerical higher-order ODEs

`NumericalHigherOrderOdeAnalyzer` gives scalar third- through eighth-order
initial-value equations a direct interface. It reduces the equation to a
first-order state system and integrates it with bounded RK4 steps:

```php
use MathPHP\Explaining\NumericalHigherOrderOdeAnalyzer;

$analysis = (new NumericalHigherOrderOdeAnalyzer())->analyze(
    "y''' = -y'",
    [0, 1, 0], // y, y', y'' at the initial coordinate
    targetIndependent: pi() / 2,
);
// final state[0] is approximately 1 for y = sin(x)
```

Apostrophe notation (`y'''`) and d-notation (`d3y/dx3`) are accepted. The
right-hand side can reference the dependent value and lower derivatives, plus
known finite parameters. Every state sample is retained; undefined slopes,
overflow, or malformed initial state return `partial` or `unsupported` rather
than a fabricated trajectory. This is numerical IVP integration, not a
symbolic higher-order ODE proof or a complete family of solutions.

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

## Elementary inverse equations

`ElementaryEquationAnalyzer` solves inverse-function forms in which the unknown
appears in one affine input. Exponentials, `ln`, base-`log`, principal `sqrt`,
`abs`, and `sin`/`cos` are supported, with domain checks and exact principal
roots. Trigonometric results also include complete periodic families using
`k ∈ ℤ`:

```php
$power = (new ElementaryEquationAnalyzer())->analyze('2^(x + 1) = 8');
// roots: [2], complete: true

$periodic = (new ElementaryEquationAnalyzer())->analyze('sin(x) = 0');
// families: x = 2πk and x = π + 2πk, complete: true
```

`EquationAnalyzer::analyze()` dispatches these forms after identifying the
actual unknown (built-in function names are not treated as variables). Mixed
nonlinear expressions still need a bounded numerical analyzer; its result is
marked partial when sampling cannot prove global completeness.

## Inequalities and larger systems

Use `InequalityAnalyzer` for bounded real relations. ASCII relations (`<`,
`<=`, `>`, `>=`) and Unicode aliases (`≤`, `≥`, `≠`) are accepted. Linear and
quadratic
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

## Nonlinear second-order initial-value problems

`NumericalSecondOrderOdeAnalyzer` covers second-order IVPs whose acceleration is
an arbitrary Core expression. It accepts `y'' = f(x, y, velocity)` (or the
equivalent `d2y/dx2` notation), integrates with bounded RK4 steps, and returns
both position and first-derivative samples:

```php
use MathPHP\Explaining\NumericalSecondOrderOdeAnalyzer;

$trajectory = (new NumericalSecondOrderOdeAnalyzer())->analyze(
    "y'' = -y - 0.1*velocity",
    dependent: 'y',
    independent: 'x',
    initialValue: 1,
    initialDerivative: 0,
    targetIndependent: 10,
    steps: 1000,
);
```

This is a numerical initial-value trajectory, not a closed-form or global
existence proof. Domain failures, overflow, and unstable trajectories return
`partial` with the completed points preserved.

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
