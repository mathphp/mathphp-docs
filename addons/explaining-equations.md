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

For mutually dependent sequences, use `RecurrenceSystemAnalyzer` with one
equation per variable. Updates are synchronous: every right-hand side reads
the prior index before all target values are committed.

```php
use MathPHP\Explaining\RecurrenceSystemAnalyzer;

$system = (new RecurrenceSystemAnalyzer())->analyze(
    ['x[n+1] = y[n] + 1', 'y[n+1] = x[n]'],
    ['x' => [0 => 0], 'y' => [0 => 2]],
    terms: 6,
);
// $system->sequences['x'] and ['y'] contain synchronized finite terms.
```

The system requires one shared target offset and earlier-index references. It
retains per-variable sequences, generated-term steps, and a `complete` flag;
missing terms or undefined updates are reported as `partial` rather than being
silently substituted.

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

For rapidly oscillating expressions, opt into bounded dyadic refinement:

```php
$analysis = (new NumericalEquationAnalyzer())->analyze(
    'sin(40*x) = 0', 'x', 0, pi(), samples: 8, refinementDepth: 4,
);
// solutions['samples'] is 128 after four doublings.
```

`refinementDepth` is clamped to 0–6 and the effective sample count is retained
in the result. More samples improve the chance of finding narrow or oscillatory
roots, but do not establish that every root in the interval was found.

Pass `EvaluationOptions` when an equation uses an explicitly registered Core
function; both sides are then evaluated through the same function registry and
resource limits as the rest of your application.

## Implicit two-variable equations

A single equality in two unknowns usually describes a curve rather than a
finite list of roots. `NumericalImplicitEquationAnalyzer` samples a bounded
rectangle and returns marching-squares segments for the approximate zero
contour:

```php
use MathPHP\Explaining\NumericalImplicitEquationAnalyzer;

$analysis = (new NumericalImplicitEquationAnalyzer())->analyze(
    'x^2 + y^2 = 1',
    'x', 'y',
    -1.5, 1.5,
    -1.5, 1.5,
    firstPoints: 96,
    secondPoints: 96,
);
// solutions['segments'] contains {start: {x, y}, end: {x, y}} records.
```

The analyzer accepts Core-compatible expressions and optional known scalar
values, caps each axis at 256 samples, and retains undefined-cell counts. A
finite contour without domain gaps is marked `solved`; no contour or any
undefined cell is `partial`; malformed input or a wholly unevaluable domain is
`unsupported`. Every result includes `complete: false`: grid sampling cannot
prove that a curve has no additional branches or singular points.

## Bounded Fredholm integral equations

`NumericalIntegralEquationAnalyzer` adds a finite collocation solver for linear
Fredholm equations of the second kind:

```php
use MathPHP\Explaining\NumericalIntegralEquationAnalyzer;

$analysis = (new NumericalIntegralEquationAnalyzer())->analyze(
    '1',                 // K(x,t)
    '1',                 // f(x)
    0.5,                 // lambda
    0, 1,                // integration interval
    points: 32,
);
// u(x) = 2 for this constant example; values are midpoint samples.
```

The solver forms `Aᵢⱼ = δᵢⱼ − λhK(xᵢ,tⱼ)` on midpoint nodes and uses
partial-pivot Gaussian elimination. Results include the nodes, approximate
function values, reconstructed residual infinity norm, and a normalized pivot
diagnostic. `complete` is always `false`: finite collocation does not prove a
continuous solution, uniqueness, or accuracy outside the sampled interval.
Undefined expressions and singular or ill-conditioned operators return
`partial`; malformed or invalid bounds return `unsupported`.

The same class supports causal Volterra equations with an upper limit of `x`:

```php
$analysis = (new NumericalIntegralEquationAnalyzer())->analyzeVolterra(
    '1', '1', 1.0, 0, 1, points: 64,
);
// Approximates u(x) = 1 + ∫₀ˣu(t)dt, whose exact solution is eˣ.
```

Volterra collocation uses full weights for prior cells and a half weight for
the current cell, producing a causal lower-triangular operator. The returned
`equationType` is `volterra-second-kind`; values, residuals, pivot diagnostics,
and `complete: false` semantics are retained.

For nonlinear Fredholm equations, use `analyzeNonlinear()` and provide the
full integrand as `F(x,t,u(t))` (the expression is evaluated with `u` bound to
the current iterate):

```php
$analysis = (new NumericalIntegralEquationAnalyzer())->analyzeNonlinear(
    'u', '1', 0.5, 0, 1, points: 32, iterations: 80,
);
// Solves the contractive equation u(x) = 1 + 0.5∫₀¹u(t)dt approximately.
```

Picard updates stop when the infinity-norm change reaches the requested
tolerance or the iteration cap. The history and final residual are returned;
non-contractive, divergent, or undefined iterations remain `partial`, and
`complete` is always `false`.

The causal nonlinear form is available through `analyzeNonlinearVolterra()`:

```php
$analysis = (new NumericalIntegralEquationAnalyzer())->analyzeNonlinearVolterra(
    'u', '1', 1.0, 0, 1, points: 48, iterations: 80,
);
// Approximates u(x) = 1 + ∫₀ˣu(t)dt, whose exact solution is eˣ.
```

Rows advance from left to right. Prior-cell integrands use completed values;
the current cell is solved with scalar Picard updates. `rowHistory` records
the updates and convergence of every midpoint, while non-convergent or
undefined rows keep the overall result `partial`.

## Scalar Itô stochastic differential equations

`NumericalSdeAnalyzer` simulates bounded stochastic equations of the form
`dX = a(t,X)dt + b(t,X)dW` with seeded Euler–Maruyama paths:

```php
use MathPHP\Explaining\NumericalSdeAnalyzer;

$analysis = (new NumericalSdeAnalyzer())->analyze(
    '0.2*x',   // drift a(t,x)
    '0.5',     // diffusion b(t,x)
    0, 1, 1,   // initial time, initial value, target time
    steps: 200,
    paths: 32,
    seed: 42,
);
// solutions['paths'] contains every trajectory and endpoint statistics.
```

The drift and diffusion expressions use Core's evaluator with `t` and `x`
bound at each step. Results retain the seed, step size, completed and failed
paths, endpoint mean/variance, and `scheme: euler-maruyama`. Stochastic
simulation is an approximation, so `complete` is always `false`; domain exits
are `partial`, while invalid time ranges or oversized batches are
`unsupported`.

## Coupled vector Itô systems

`NumericalSdeSystemAnalyzer` applies the same bounded contract to a vector of
coupled state variables. Pass one drift and one diffusion expression per
variable; every component is evaluated from the same previous state and then
advanced on a shared grid:

```php
use MathPHP\Explaining\NumericalSdeSystemAnalyzer;

$analysis = (new NumericalSdeSystemAnalyzer())->analyze(
    ['x', 'v'],
    ['v', '-x'],       // dx = v dt, dv = -x dt
    ['0', '0'],        // deterministic oscillator in this example
    ['x' => 1, 'v' => 0],
    targetTime: 1.5708,
    steps: 200,
    paths: 32,
    seed: 42,
);
```

The default system contract is
`dX_i = a_i(t, X)dt + b_i(t, X)dW_i`, with independent Brownian components.
For correlated components, pass a finite symmetric positive-semidefinite
covariance matrix using the `covariance:` named argument; the analyzer validates
it and generates increments with a Cholesky factor. Results retain synchronized
paths, per-variable endpoint means and variances, the exact step size,
failed-path counts, covariance, and the seed. `complete` remains `false`
because Euler–Maruyama is a finite stochastic approximation; undefined paths
become `partial`. Jump processes, stochastic algebraic constraints, and other
SDE families remain unsupported until they have their own explicit contract.

## Scalar compound-Poisson jump diffusions

`NumericalJumpSdeAnalyzer` adds a seeded scalar jump-diffusion contract:
`dX = a(t,X)dt + b(t,X)dW + j(t,X)dN`, where `N` has a supplied intensity.

```php
use MathPHP\Explaining\NumericalJumpSdeAnalyzer;

$analysis = (new NumericalJumpSdeAnalyzer())->analyze(
    '0.1*x',  // drift
    '0.2',    // diffusion
    '1',      // jump size expression
    2.0,      // Poisson intensity
    0, 0, 1,
    steps: 200,
    paths: 32,
    seed: 42,
);
```

Each interval draws a normal diffusion increment and an exact bounded Poisson
count, retaining per-step counts, total jumps, endpoint statistics, and the
seed. The method marks itself `complete: false` because this is a finite
stochastic approximation. For deterministic performance bounds, the exact
inversion sampler requires intensity × step size ≤ 50; high-rate, marked, and
vector jump processes need a separate contract.

## Numerical higher-order ODEs

`NumericalHigherOrderOdeAnalyzer` gives scalar third- through 32nd-order
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

Apostrophe notation (`y'''`), d-notation (`d3y/dx3`), and caret notation
(`d^9y/dx^9`) are accepted. The right-hand side can reference the dependent
value and lower derivatives, plus known finite parameters. Every state sample
is retained; undefined slopes, overflow, or malformed initial state return
`partial` or `unsupported` rather than a fabricated trajectory. This is
numerical IVP integration, not a symbolic higher-order ODE proof or a complete
family of solutions.

## Bounded one-dimensional parabolic PDEs

`NumericalPdeAnalyzer` covers a deliberately bounded initial-boundary problem
for a dependent field `u(x,t)`:

```text
u_t = F(x, t, u, u_x, u_xx, u_xxx, ...)
```

Affine right-hand sides cover advection–diffusion forms and pure heat
equations. Nonlinear spatial operators are also evaluated directly on the
finite-difference stencil when their local sensitivity remains finite. Supply
an initial profile and fixed (Dirichlet) values at the left and right spatial
boundaries:

```php
use MathPHP\Explaining\NumericalPdeAnalyzer;

$analysis = (new NumericalPdeAnalyzer())->analyze(
    'u_t = v*u_x + k*u_xx',
    'sin(pi()*x)', // u(x, 0)
    '0',           // u(0, t)
    '0',           // u(1, t)
    known: ['v' => 0.4, 'k' => 0.1],
    spacePoints: 41,
    timeSteps: 100,
);
```

The solver uses centered first- and second-derivative finite-difference
stencils plus a wider centered third-derivative stencil (`u_xxx` or
`d3u/dx3`), a combined advection/diffusion stability bound, and retains field
snapshots in `solution['points']`. For nonlinear operators, the result includes
`solution['operatorMode'] = 'direct-nonlinear'` and a local sensitivity-based
stability guard. The visual model
has kind `pde-heatmap` so a private renderer can draw a space-time heat map.
`solved` means the requested bounded grid completed; `partial` means a
stability cap, undefined boundary, or non-finite state interrupted or weakened
the trajectory. Undefined/non-finite operators, higher-dimensional domains,
or symbolic closed-form requests are reported as `unsupported` or `partial`.
Numerical completion is never a proof for every PDE solution. Paired periodic
boundaries are supported by setting both edges to `['type' => 'periodic']`:

```php
$periodic = (new NumericalPdeAnalyzer())->analyze(
    'u_t = u_xx', '1', '1', '1',
    boundaryConditions: [
        'left' => ['type' => 'periodic'],
        'right' => ['type' => 'periodic'],
    ],
);
```

The same analyzer accepts mixed boundary types through the optional
`boundaryConditions` map. A Neumann edge prescribes the first spatial
derivative; a Robin edge prescribes `alpha*u + beta*u_x = value`:

```php
$analysis = (new NumericalPdeAnalyzer())->analyze(
    'u_t = u_xx', '0', '0', '0',
    boundaryConditions: [
        'left' => ['type' => 'neumann', 'value' => '0'],
        'right' => ['type' => 'robin', 'alpha' => 1, 'beta' => 0, 'value' => '0'],
    ],
);
```

The solver uses one-sided finite-difference edge formulas and retains the
normalized boundary types and coefficients in `solution['boundaryConditions']`.
Periodic endpoints wrap opposite interior points before each update and are
retained in the normalized metadata. Unknown types, unpaired periodic edges,
singular Robin coefficients, undefined values, and nonlocal conditions are
reported explicitly.

## Second-order boundary-value ODEs

`NumericalBoundaryValueOdeAnalyzer` handles a bounded scalar second-order
boundary-value problem with two fixed endpoint values:

```php
use MathPHP\Explaining\NumericalBoundaryValueOdeAnalyzer;

$analysis = (new NumericalBoundaryValueOdeAnalyzer())->analyze(
    "y'' = -k*y",
    '0',
    '1',
    known: ['k' => 1],
    steps: 100,
);
```

The analyzer treats the unknown initial slope as a shooting parameter, solves
each trial with bounded RK4 integration, then refines a sign-changing endpoint
residual with bisection and guarded secant steps. The returned model includes
the selected slope, endpoint residual, trajectory, and iteration count. A
`solved` result means the residual met the requested tolerance for one found
trajectory (`complete: false`); a `partial` result means no trial met it or an
integration became undefined. This focused method does not prove uniqueness,
find every branch, or cover higher-order/multi-point, singular, or derivative
boundary conditions.

## Two-dimensional elliptic PDEs

`NumericalEllipticPdeAnalyzer` adds a bounded rectangular solver for equations
whose residual is affine in `u_xx`, `u_yy`, and `u_xy`, such as Laplace, Poisson,
rotated-coordinate forms, and semilinear value equations. Value-dependent
coefficients and source terms are re-evaluated with a Picard-style update:

```php
use MathPHP\Explaining\NumericalEllipticPdeAnalyzer;

$analysis = (new NumericalEllipticPdeAnalyzer())->analyze(
    'u_xx + u_yy = 0',
    'y', '1 + y', 'x', 'x + 1',
    firstPoints: 25,
    secondPoints: 25,
);
```

The four boundary expressions define the left, right, bottom, and top
Dirichlet edges by default. The optional `boundaryConditions` map can replace
any edge with a one-sided Neumann or Robin condition:

```php
$analysis = (new NumericalEllipticPdeAnalyzer())->analyze(
    'u_xx + u_yy = 0',
    '0', '1', '1', '1',
    boundaryConditions: [
        'left' => ['type' => 'neumann', 'value' => '0'],
        'right' => ['type' => 'robin', 'alpha' => 1, 'beta' => 0, 'value' => '1'],
    ],
);
```

The solver derives the affine spatial stencil, iterates interior nodes with
Gauss–Seidel updates, re-evaluates value terms at the current iterate, includes
a diagonal stencil for the mixed derivative, reapplies edge
conditions, and retains grids in
`solution['snapshots']` for a heat map or surface renderer. Normalized edge
types and coefficients are returned in `solution['boundaryConditions']`.
`solved` means the finite grid met the requested update and residual tolerances;
`partial` means the iteration limit was reached or a field update failed.
Non-elliptic principal parts, nonlinear derivative terms, singular Robin coefficients,
incompatible corners, nonlocal conditions, and higher dimensions are outside
this focused contract, and convergence does not prove a unique or complete PDE
solution. For periodicity, set both edges of an axis to
`['type' => 'periodic']`; the solver wraps opposite interior edges into the
centered stencil and rejects an unpaired periodic edge:

```php
$analysis = (new NumericalEllipticPdeAnalyzer())->analyze(
    'u_xx + u_yy = 0',
    '0', '0', '0', '1',
    boundaryConditions: [
        'left' => ['type' => 'periodic'],
        'right' => ['type' => 'periodic'],
    ],
);
```

## Three-dimensional elliptic PDEs

`NumericalEllipticPde3DAnalyzer` extends the elliptic contract to a bounded
rectangular volume. It accepts diagonal second derivatives `u_xx`, `u_yy`, and
`u_zz`, affine mixed derivatives `u_xy`, `u_xz`, and `u_yz`, six face
expressions, and optional per-face Dirichlet, Neumann, Robin, or paired
periodic conditions:

```php
use MathPHP\Explaining\NumericalEllipticPde3DAnalyzer;

$analysis = (new NumericalEllipticPde3DAnalyzer())->analyze(
    'u_xx + u_yy + u_zz = 0',
    'y + z', '1 + y + z',
    'x + z', 'x + 1 + z',
    'x + y', 'x + y + 1',
    firstPoints: 15,
    secondPoints: 15,
    thirdPoints: 15,
);
```

The solver uses a seven-point plus diagonal mixed-derivative Gauss–Seidel
stencil, re-evaluates nonlinear value terms with Picard updates, checks the
full symmetric principal-part matrix for positive or negative definiteness,
and retains volumetric snapshots in `solution['snapshots']`. `solved` means the
finite grid reached the configured update and residual tolerances for one run.
For periodicity, set both faces of an axis to `['type' => 'periodic']`; the
solver wraps the opposite interior planes into the centered stencil and keeps
the normalized pair in `solution['boundaryConditions']`:

```php
$analysis = (new NumericalEllipticPde3DAnalyzer())->analyze(
    'u_xx + u_yy + u_zz = 0',
    '0', '0', '0', '1', '0', '0',
    boundaryConditions: [
        'left' => ['type' => 'periodic'],
        'right' => ['type' => 'periodic'],
        'front' => ['type' => 'periodic'],
        'back' => ['type' => 'periodic'],
    ],
);
```

Unpaired periodic faces, nonlocal faces, nonlinear derivative operators, and
uniqueness/completeness proofs remain outside this focused numerical contract.

## One-dimensional wave equations

`NumericalWavePdeAnalyzer` supports a bounded hyperbolic initial-boundary
problem with displacement and velocity data. Pure third spatial derivatives
(`u_xxx` and `d3u/dx3`) are supported through the wider direct-evaluation
stencil:

```php
use MathPHP\Explaining\NumericalWavePdeAnalyzer;

$analysis = (new NumericalWavePdeAnalyzer())->analyze(
    'u_tt = c^2*u_xx',
    'sin(pi()*x)', // u(x, 0)
    '0',           // u_t(x, 0)
    '0',           // u(0, t)
    '0',           // u(1, t)
    known: ['c' => 1],
);
```

The positional edges are Dirichlet defaults. The optional
`boundaryConditions` map adds a Neumann, Robin, or paired periodic condition;
one-sided finite differences are used for Neumann/Robin edges and normalized
edge metadata is retained.

```php
$analysis = (new NumericalWavePdeAnalyzer())->analyze(
    'u_tt = c^2*u_xx', '1', '0', '1', '1',
    known: ['c' => 2],
    boundaryConditions: ['left' => ['type' => 'neumann', 'value' => '0']],
);
```

Set both endpoints to periodic to wrap the duplicate endpoints to the
opposite interior points before each wave update:

```php
$periodic = (new NumericalWavePdeAnalyzer())->analyze(
    'u_tt = u_xx',
    'x', '0', '0', '0',
    boundaryConditions: [
        'left' => ['type' => 'periodic'],
        'right' => ['type' => 'periodic'],
    ],
);
```

The solver applies a centered explicit finite-difference update and chooses
substeps from a conservative CFL bound. It returns time-stamped field
snapshots, the effective step size, and explicit stability/finite-range
diagnostics. `solved` means the finite trajectory completed; `partial` means a
guard or boundary/domain failure interrupted it. Nonlinear spatial operators
are evaluated directly with local sensitivity-based CFL estimates and expose
`solution['operatorMode'] = 'direct-nonlinear'`. Unpaired periodic endpoints,
nonlocal edges, higher dimensions, or symbolic general solutions remain outside
this focused contract.

## Two-dimensional parabolic PDEs

`NumericalParabolicPdeAnalyzer` covers bounded rectangular heat and diffusion
initial-boundary problems for a field `u(x, y, t)`:

```text
u_t = F(x, y, t, u, u_x, u_y, u_xx, u_yy, u_xy, u_xxx, u_yyy, ...)
```

Affine right-hand sides may include `u_x`, `u_y`, `u_xx`, `u_yy`, and `u_xy`,
with non-negative diffusion coefficients and a positive-semidefinite principal
part. Pure third derivatives (`u_xxx`, `u_yyy`, and `d3u/dx3`-style aliases) are
evaluated directly with wider centered stencils. Nonlinear spatial operators
are evaluated directly with local sensitivity estimates when all stencil samples
remain finite. Supply an initial profile and the four positional edge
expressions (which remain Dirichlet defaults):

```php
use MathPHP\Explaining\NumericalParabolicPdeAnalyzer;

$analysis = (new NumericalParabolicPdeAnalyzer())->analyze(
    'u_t = vx*u_x + vy*u_y + alpha*u_xx + beta*u_yy',
    '1',                // u(x, y, 0)
    '1', '1', '1', '1', // left, right, bottom, top
    known: ['vx' => 0.2, 'vy' => -0.1, 'alpha' => 0.1, 'beta' => 0.1],
    firstPoints: 25,
    secondPoints: 25,
    timeSteps: 100,
);
```

The analyzer applies centered advection stencils, an explicit five-point
diffusion stencil, plus diagonal mixed-derivative stencils when `u_xy` is
present. Third derivatives use a wider centered stencil. It chooses a combined
advection/diffusion CFL time step and retains
time-stamped grids in
`solution['points']`. The visual model has kind `pde-heatmap-2d`. A `solved`
status means the finite grid completed under the stability guard; nonlinear
operator runs expose `solution['operatorMode'] = 'direct-nonlinear'`. The
solution still has `complete: false` because it is a numerical approximation.

Each edge can instead be configured with the optional `boundaryConditions`
map. Neumann edges prescribe the outward first derivative and Robin edges
prescribe `alpha*u + beta*u_n = value`, using one-sided finite differences:

```php
$analysis = (new NumericalParabolicPdeAnalyzer())->analyze(
    'u_t = alpha*u_xx + beta*u_yy',
    '1', '1', '1', '1', '1',
    known: ['alpha' => 0.1, 'beta' => 0.1],
    boundaryConditions: [
        'left' => ['type' => 'neumann', 'value' => '0'],
        'right' => ['type' => 'robin', 'alpha' => 1, 'beta' => 0, 'value' => '1'],
    ],
);
```

Opposite edges can be paired as periodic boundaries. The solver wraps each
duplicate endpoint to the opposite interior edge before every update, so the
centered stencil sees a continuous periodic field in that axis:

```php
$periodic = (new NumericalParabolicPdeAnalyzer())->analyze(
    'u_t = alpha*u_xx + beta*u_yy',
    '1', '1', '1', '1', '1',
    known: ['alpha' => 0.1, 'beta' => 0.1],
    boundaryConditions: [
        'left' => ['type' => 'periodic'],
        'right' => ['type' => 'periodic'],
        'bottom' => ['type' => 'periodic'],
        'top' => ['type' => 'periodic'],
    ],
);
```

Normalized edge types and Robin coefficients are returned in
`solution['boundaryConditions']`. Undefined values, singular Robin
coefficients, incompatible Dirichlet corners, backward diffusion, unpaired
periodic edges, nonlocal conditions, and higher dimensions are reported as
`unsupported` or `partial` rather than guessed. Nonlinear derivative operators
are evaluated directly only when their local stencil samples remain finite.

## Coupled one-dimensional parabolic systems

`NumericalCoupledParabolicPdeAnalyzer` handles bounded systems of fields sharing
one spatial grid. Each equation has one time derivative, and the right side may
couple all field values while remaining affine in their spatial first and
second derivatives:

```text
u_t = a*u_x + u_xx + v
v_t = b*v_x + v_xx - u
```

Provide one initial profile and two edge expressions per field. Positional
expressions remain Dirichlet defaults, while the optional edge-first
`boundaryConditions` map can configure each field independently:

```php
use MathPHP\Explaining\NumericalCoupledParabolicPdeAnalyzer;

$analysis = (new NumericalCoupledParabolicPdeAnalyzer())->analyze(
    'u_t = a*u_x + u_xx + v; v_t = b*v_x + v_xx - u',
    ['u', 'v'],
    ['u' => 'sin(pi()*x)', 'v' => '0'],
    ['u' => '0', 'v' => '0'], // left edges
    ['u' => '0', 'v' => '0'], // right edges
    known: ['a' => 0.2, 'b' => -0.1],
    boundaryConditions: [
        'left' => ['u' => ['type' => 'neumann', 'value' => '0']],
        'right' => ['v' => ['type' => 'robin', 'alpha' => 1, 'beta' => 0, 'value' => '0']],
    ],
    spacePoints: 41,
    timeSteps: 100,
);
```

The explicit update integrates all components at the same time level, using
centered advection and diffusion stencils, and retains per-field snapshots in
`solution['points']`. Cross-diffusion terms are accepted when they are affine
and non-negative under the conservative CFL bound. A field may instead use a
directly evaluated nonlinear spatial operator when its stencil samples remain
finite; per-field modes are returned in `solution['operatorModes']`.
Normalized edge conditions are returned in `solution['boundaryConditions']`.
Pair the left and right periodic endpoints independently for each field to
wrap duplicate endpoints to the opposite interior values:

```php
$periodic = (new NumericalCoupledParabolicPdeAnalyzer())->analyze(
    'u_t = u_xx; v_t = v_xx',
    ['u', 'v'],
    ['u' => 'x', 'v' => '2*x'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    boundaryConditions: [
        'left' => ['u' => ['type' => 'periodic'], 'v' => ['type' => 'periodic']],
        'right' => ['u' => ['type' => 'periodic'], 'v' => ['type' => 'periodic']],
    ],
);
```

Unpaired periodic endpoints, nonlocal boundaries, and symbolic/global PDE
solutions remain outside this numerical contract.
`solved` means only that the requested finite grid completed; the serialized
result remains `complete: false`. Backward diffusion and higher-dimensional
systems remain outside this contract.

## Three-dimensional parabolic PDEs

`NumericalParabolicPde3DAnalyzer` covers a resource-capped rectangular heat or
diffusion field `u(x, y, z, t)` with six positional faces. Each face defaults
to Dirichlet; an optional `boundaryConditions` map can replace any face with a
normal-derivative (Neumann), mixed (Robin), or paired periodic condition:

```text
u_t = F(x, y, z, t, u, u_xx, u_yy, u_zz)
```

Affine diffusion coefficients must be non-negative. Nonlinear combinations of
the three pure second derivatives, centered mixed derivatives (`u_xy`, `u_xz`,
`u_yz`), centered first derivatives (`u_x`, `u_y`, `u_z`), and pure third
derivatives (`u_xxx`, `u_yyy`, `u_zzz`) are evaluated directly when all stencil
samples remain finite, with local sensitivity estimates used for the explicit
time-step guard. Third derivatives use a wider centered stencil. Derivative
aliases such as `d3u/dx3`, `d2u/dxdy`, and `du/dx` are accepted:

```php
use MathPHP\Explaining\NumericalParabolicPde3DAnalyzer;

$analysis = (new NumericalParabolicPde3DAnalyzer())->analyze(
    'u_t = alpha*u_xx + beta*u_yy + gamma*u_zz',
    '1', '1', '1', '1', '1', '1', '1',
    known: ['alpha' => 0.05, 'beta' => 0.05, 'gamma' => 0.05],
    boundaryConditions: [
        'front' => ['type' => 'neumann', 'value' => '0'],
        'back' => ['type' => 'robin', 'alpha' => 1, 'beta' => 0.5, 'value' => '1'],
    ],
    firstPoints: 9, secondPoints: 9, thirdPoints: 9,
    timeSteps: 10,
);
```

The result contains bounded 3D snapshots with visual kind `pde-heatmap-3d`,
six normalized face conditions, grid spacing, effective time step, and
`solution['operatorMode']`. Neumann faces use one-sided outward finite
differences; Robin faces enforce `alpha*u + beta*u_n = value` and reject
singular coefficients. Pair opposite faces on an axis as periodic to wrap
duplicate faces to the opposite interior planes before each update:

```php
$periodic = (new NumericalParabolicPde3DAnalyzer())->analyze(
    'u_t = u_xx + u_yy + u_zz',
    '1', '1', '1', '1', '1', '1', '1',
    boundaryConditions: [
        'left' => ['type' => 'periodic'], 'right' => ['type' => 'periodic'],
        'bottom' => ['type' => 'periodic'], 'top' => ['type' => 'periodic'],
        'front' => ['type' => 'periodic'], 'back' => ['type' => 'periodic'],
    ],
);
```

Unpaired periodic faces, nonlocal boundaries, larger dimensions, arbitrary
mixed third derivatives, and symbolic general solutions remain outside this
focused contract.

## Three-dimensional wave equations

`NumericalWavePde3DAnalyzer` covers a resource-capped rectangular hyperbolic
field `u(x, y, z, t)` with initial displacement, initial velocity, and six
positional faces (Dirichlet by default). The same `boundaryConditions` map
supports per-face Neumann, Robin, and paired periodic data:

```text
u_tt = F(x, y, z, t, u, u_xx, u_yy, u_zz)
```

Affine wave coefficients must be non-negative. Nonlinear combinations of the
three pure and three centered mixed second derivatives, centered first
derivatives (`u_x`, `u_y`, `u_z`), and pure third derivatives (`u_xxx`, `u_yyy`,
`u_zzz`) are evaluated directly when stencil samples remain finite, with local
sensitivity estimates used for the CFL guard. Third derivatives use a wider
centered stencil. Derivative aliases such as `d3u/dx3`, `d2u/dxdy`, and `du/dx`
are accepted:

```php
use MathPHP\Explaining\NumericalWavePde3DAnalyzer;

$analysis = (new NumericalWavePde3DAnalyzer())->analyze(
    'u_tt = c2*u_xx + c2*u_yy + c2*u_zz',
    '1', '0', '1', '1', '1', '1', '1', '1',
    known: ['c2' => 1],
    firstPoints: 9, secondPoints: 9, thirdPoints: 9,
    timeSteps: 10,
);
```

The result contains `pde-wave-3d` snapshots, normalized face conditions, grid
spacing, effective time step, and `solution['operatorMode']`. Pair opposite
faces on an axis as periodic to wrap duplicate faces to the opposite interior
planes before each update:

```php
$periodic = (new NumericalWavePde3DAnalyzer())->analyze(
    'u_tt = u_xx + u_yy + u_zz',
    '1', '0', '1', '1', '1', '1', '1', '1',
    boundaryConditions: [
        'left' => ['type' => 'periodic'], 'right' => ['type' => 'periodic'],
        'bottom' => ['type' => 'periodic'], 'top' => ['type' => 'periodic'],
        'front' => ['type' => 'periodic'], 'back' => ['type' => 'periodic'],
    ],
);
```

Unpaired periodic faces, nonlocal boundaries, larger dimensions, arbitrary
mixed third derivatives, and symbolic general solutions remain outside this
focused solver.

## Coupled three-dimensional parabolic systems

`NumericalCoupledParabolicPde3DAnalyzer` integrates up to eight dependent
fields on one shared rectangular grid. Provide one time-derivative equality per
field; right-hand sides may reference any field value and its first, pure
second, mixed, or pure third spatial derivatives (`u_xxx`, `u_yyy`, `u_zzz`):

```text
u_t = u_xx + v
v_t = v_yy - u
```

```php
use MathPHP\Explaining\NumericalCoupledParabolicPde3DAnalyzer;

$analysis = (new NumericalCoupledParabolicPde3DAnalyzer())->analyze(
    'u_t = u_xx + v; v_t = v_yy - u',
    ['u', 'v'],
    ['u' => '1', 'v' => '0'],
    ['u' => '1', 'v' => '0'], ['u' => '1', 'v' => '0'],
    ['u' => '1', 'v' => '0'], ['u' => '1', 'v' => '0'],
    ['u' => '1', 'v' => '0'], ['u' => '1', 'v' => '0'],
    firstPoints: 9, secondPoints: 9, thirdPoints: 9,
    timeSteps: 10,
);
```

The six face arguments are per-field Dirichlet expressions. The optional
edge-first `boundaryConditions` map can replace any field face with Neumann,
Robin, or paired periodic data. Pair opposite faces independently for each
field; periodic faces wrap to the opposite interior planes before each update,
while omitted faces retain their Dirichlet defaults. Results retain
per-field 3D snapshots, normalized conditions, operator modes, grid spacing,
and explicit stability metadata under the `pde-system-heatmap-3d` visual kind.
Pure third derivatives use a wider centered stencil and sensitivity-aware
stability bounds. This remains a bounded explicit approximation; arbitrary mixed
third derivatives, unpaired periodic faces, nonlocal boundaries, higher
dimensions, and symbolic general solutions are not implied.

## Two-dimensional wave equations

`NumericalWavePde2DAnalyzer` supports a bounded rectangular hyperbolic problem
with initial displacement, initial velocity, and four edge expressions. The
spatial operator may include affine `u_xy` terms when its principal part is
positive-semidefinite, or nonlinear combinations of `u_xx`, `u_yy`, `u_xy`,
`u_xxx`, and `u_yyy` evaluated directly with local sensitivity estimates. Pure
third derivatives use wider centered stencils:

```text
u_tt = c^2 * (u_xx + u_yy)
```

```php
use MathPHP\Explaining\NumericalWavePde2DAnalyzer;

$analysis = (new NumericalWavePde2DAnalyzer())->analyze(
    'u_tt = c^2*(u_xx + u_yy)',
    'sin(pi()*x)*sin(pi()*y)', // displacement at t = 0
    '0',                       // velocity at t = 0
    '0', '0', '0', '0',        // left, right, bottom, top
    known: ['c' => 1],
    firstPoints: 25,
    secondPoints: 25,
    timeSteps: 100,
);
```

The positional edges are Dirichlet defaults. The optional
`boundaryConditions` map adds per-edge Neumann, Robin, or paired periodic
conditions using one-sided finite differences for Neumann/Robin edges:

```php
$analysis = (new NumericalWavePde2DAnalyzer())->analyze(
    'u_tt = u_xx + u_yy',
    '1', '0', '1', '1', '1', '1',
    boundaryConditions: [
        'left' => ['type' => 'neumann', 'value' => '0'],
    ],
);
```

Pair opposite edges as periodic to wrap duplicate edges to the opposite
interior values before and after each wave update:

```php
$periodic = (new NumericalWavePde2DAnalyzer())->analyze(
    'u_tt = u_xx + u_yy',
    'x + 2*y', '0', '0', '0', '0', '0',
    boundaryConditions: [
        'left' => ['type' => 'periodic'], 'right' => ['type' => 'periodic'],
        'bottom' => ['type' => 'periodic'], 'top' => ['type' => 'periodic'],
    ],
);
```

The solver uses a centered leapfrog stencil (including diagonal mixed-term
coupling when present) and chooses substeps from a conservative two-dimensional
CFL bound. `solution['points']` contains
time-stamped grids and the visual model has kind `pde-wave-2d`. A `solved`
result means only that the requested finite trajectory completed; it remains
`complete: false` because it is a numerical approximation. Nonlinear runs
expose `solution['operatorMode'] = 'direct-nonlinear'`. Unpaired periodic
edges, nonlocal conditions, unstable coefficients, arbitrary mixed third
derivatives, higher dimensions, and symbolic general solutions are reported as
`unsupported` or `partial`.

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
initial estimate per variable. Square systems use damped Newton's method; systems
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
Jacobians. Each update is backtracked by up to ten halvings when a full step
increases the residual or leaves the evaluable domain. The serialized result
includes each iterate and residual norm, and returns
`partial` when the Jacobian is singular, an expression leaves its domain, or
the configured iteration limit is reached. A converged starting point finds
one nearby solution; it does not establish that every solution exists or has
been found. Underdetermined systems return one minimum-norm member with
`partial` status because a free-variable or constraint-set representation is
needed for the full family. Partial differential equations, jump SDEs, and
global piecewise/discontinuous proofs remain outside this general-purpose
numeric analyzer and are reported as unsupported or partial.

## Index-1 differential-algebraic equations

`NumericalDaeAnalyzer` covers a bounded index-1 contract with explicit
differential variables and algebraic variables:

```php
use MathPHP\Explaining\NumericalDaeAnalyzer;

$analysis = (new NumericalDaeAnalyzer())->analyze(
    ['x'],                 // differential variables
    ['1'],                 // x' = 1
    ['z'],                 // algebraic variables
    ['z = x'],             // algebraic constraint
    ['x' => 0, 'z' => 4],  // z is projected to the constraint initially
    targetIndependent: 1,
    steps: 100,
);
```

The analyzer advances differential variables with projected Euler steps and
uses a finite-difference Newton solve to enforce the algebraic constraints at
the initial point and after every step. Constraint residuals are retained in
each trajectory point. For coupled differential blocks, pass a finite constant
dense `massMatrix` to solve `M·x′ = f`:

```php
$analysis = (new NumericalDaeAnalyzer())->analyze(
    ['x', 'y'], ['1', '2'], ['z'], ['z = x'],
    ['x' => 0, 'y' => 0, 'z' => 0],
    targetIndependent: 1,
    massMatrix: [[2, 1], [1, 2]],
);
```

The matrix is validated as square and nonsingular, and is retained in the
result. This remains a finite approximation for locally nonsingular index-1
systems; higher-index, complementarity, and inconsistent systems are reported
as `partial` or `unsupported`.

For a variable mass matrix, provide expressions instead of numeric values. The
expressions may reference the independent coordinate and all current state
variables:

```php
$analysis = (new NumericalDaeAnalyzer())->analyze(
    ['x'], ['1'], ['z'], ['z = x'],
    ['x' => 0, 'z' => 0],
    targetIndependent: 1,
    massMatrixExpressions: [['1 + t']],
);
```

`M(t,x,z)` is evaluated before each slope solve, checked for finite values and
non-singularity, and retained in each trajectory point. Numeric
`massMatrix` and `massMatrixExpressions` are mutually exclusive.

### First-differentiated constraints

Some index-2-style systems do not expose the algebraic variables in the
original constraint, so its algebraic Jacobian is singular. Supply one
first-differentiated residual per algebraic variable to select the algebraic
state during projection:

```php
$analysis = (new NumericalDaeAnalyzer())->analyze(
    ['x'], ['z'], ['z'], ['x - t = 0'],
    ['x' => 0, 'z' => 0],
    targetIndependent: 1,
    differentiatedAlgebraicEquations: ['z - 1 = 0'],
);
```

The result identifies the `projected-euler-differentiated-constraint` method,
retains the supplied derivative residuals, and reports the original constraint
residual at every trajectory point. This is a bounded first-differentiation
pathway, not a general arbitrary-index or complementarity DAE solver.

## Complementarity differential systems

Some contact, switching, and constrained dynamical models use complementarity
pairs rather than equality-only algebraic constraints:

`0 <= z_i ⟂ g_i(t,x,z) >= 0`, meaning `z_i >= 0`, `g_i >= 0`, and
`z_i * g_i = 0`.

`NumericalComplementarityAnalyzer` provides a bounded active-set method for
coupled differential states and up to eight algebraic pairs:

```php
use MathPHP\Explaining\NumericalComplementarityAnalyzer;

$analysis = (new NumericalComplementarityAnalyzer())->analyze(
    ['x'],
    ['1'],
    ['z'],
    ['z + x'],             // 0 <= z ⟂ z + x >= 0
    ['x' => -1, 'z' => 1],
    targetIndependent: 2,
    steps: 200,
);
```

At each projected-Euler step it enumerates the bounded active sets, fixes
active variables to zero, solves inactive residuals with finite-difference
Newton updates, and retains the selected `activeSet` and complementarity
`residual` alongside the state. The result is a transparent numerical
approximation, not a global certificate: generalized mixed complementarity formulations beyond the supported box rules,
higher-index DAEs, unbounded active-set searches, and non-paired inequality
constraints remain outside this focused contract.

### Mixed complementarity bounds

For box-constrained pairs, use `analyzeMixed()` with one finite lower and upper
bound per algebraic variable. The active-set rule is:

- `z = lower` implies `g >= 0`;
- `lower < z < upper` implies `g = 0`;
- `z = upper` implies `g <= 0`.

```php
$analysis = (new NumericalComplementarityAnalyzer())->analyzeMixed(
    ['x'], ['1'], ['z'], ['z - x'],
    ['x' => -1, 'z' => 0],
    [0], [1],
    targetIndependent: 3,
    steps: 200,
);
// z follows x in the interior, then remains at its upper bound of 1.
```

The result retains `activeSet` labels such as `z@upper` and the maximum bound
or residual violation at every point. This remains a bounded active-set
approximation; generalized variational inequalities, coupled nonsmooth
normal-cone operators, and globally certified solutions require a specialized
solver outside this package.

### Generalized feasible-set inequalities

`analyzeGeneralized()` extends the projected method to a box intersected with
up to eight nonlinear residual constraints `c_j(z) <= 0`:

```php
$analysis = (new NumericalVariationalInequalityAnalyzer())->analyzeGeneralized(
    ['x'],
    ['x - 1'],
    ['x' => 0.2],
    [0], [1],
    ['x <= limit'],
    known: ['limit' => 0.5],
    stepSize: 0.5,
);
```

Each candidate is first projected to the box, then corrected by bounded
finite-difference linearizations of the violated constraints. The result keeps
constraint values and the maximum positive constraint residual at every
iterate. This is a local convex/regular-set approximation; nonconvex sets,
nonsmooth normal-cone operators, and global convergence certificates remain
outside the focused contract.

## Nonsmooth normal-cone inclusions

For a bounded nonsmooth potential, use
`NumericalNonsmoothNormalConeAnalyzer` for the local inclusion
`0 ∈ F(z) + ∂φ(z) + N_K(z)`, where `K` is a finite box. This is useful for
absolute values, hinge-like penalties, and other scalar potentials whose
classical derivative is unavailable at a kink:

```php
use MathPHP\Explaining\NumericalNonsmoothNormalConeAnalyzer;

$analysis = (new NumericalNonsmoothNormalConeAnalyzer())->analyze(
    ['x'],
    ['x - 2'],                 // F(x)
    'abs(x)',                  // scalar potential φ(x)
    ['x' => 0],
    [-1], [1],                 // box K
    stepSize: 0.5,
    tolerance: 1e-9,
);
// The bounded candidate is x = 1; the upper normal-cone condition is active.
```

The analyzer estimates one-sided slopes with centered finite differences. A
material slope mismatch is retained as a `kinked` diagnostic, and the midpoint
is used as one admissible subgradient candidate. Every iterate records the
mapping, subgradient, total residual, potential value, projected candidate,
and residual. `solved` means only that the configured projected residual was
reached from the supplied initial value; arbitrary nonconvex potentials,
multi-valued subdifferentials, global convergence, and exact generalized
derivative certificates require a specialized solver and are reported outside
this focused contract.

## Scalar differential inclusions

An inclusion permits a set of slopes rather than one right-hand side:

`y'(t) ∈ [f_lower(t,y), f_upper(t,y)]`.

`NumericalDifferentialInclusionAnalyzer` retains lower and upper Euler envelope
samples and one selectable trajectory. Use `lower`, `midpoint`, or `upper` to
choose the demonstrative path:

```php
use MathPHP\Explaining\NumericalDifferentialInclusionAnalyzer;

$analysis = (new NumericalDifferentialInclusionAnalyzer())->analyze(
    '-1', '1',             // admissible slopes
    0,
    targetIndependent: 1,
    steps: 100,
    selection: 'midpoint',
);
// Lower path: -1, upper path: 1, midpoint path: 0.
```

The result reports an empty interval, non-finite field, or crossed Euler
envelopes as `partial`. An inclusion generally has many solutions, so this is
an explanatory bounded approximation—not a rigorous reachable-set enclosure
or a uniqueness theorem for state-dependent fields.

## Coupled differential inclusions

For a coupled set-valued system,
`x'_i(t) ∈ [f^-_i(t,x), f^+_i(t,x)]`, use
`NumericalDifferentialInclusionSystemAnalyzer`:

```php
use MathPHP\Explaining\NumericalDifferentialInclusionSystemAnalyzer;

$analysis = (new NumericalDifferentialInclusionSystemAnalyzer())->analyze(
    ['x', 'y'],
    ['-1', '0'],       // lower component slopes
    ['1', '2'],        // upper component slopes
    ['x' => 0, 'y' => 1],
    targetTime: 1,
    steps: 100,
    selection: 'midpoint',
);
```

The result retains lower, upper, and selected values and slopes for every
component at every time sample. A component with an empty interval, a
non-finite field, or crossed Euler envelopes yields `partial`; this is a
bounded explanatory approximation, not a rigorous multidimensional
reachable-set computation.

When a system may have several nearby roots, call `analyzeMany()` with several
initial maps. It deduplicates converged values but keeps failed or partial runs
so callers can show which starting points were inconclusive.

## Box-constrained variational inequalities

A variational inequality seeks `z` in a feasible set `K` such that
`F(z)·(w−z) >= 0` for every `w` in `K`. For a box
`K = [lower, upper]`, `NumericalVariationalInequalityAnalyzer` uses the
projected-gradient fixed-point condition
`z = P_K(z − αF(z))`:

```php
use MathPHP\Explaining\NumericalVariationalInequalityAnalyzer;

$analysis = (new NumericalVariationalInequalityAnalyzer())->analyze(
    ['z'],
    ['z - 2'],                 // F(z)
    ['z' => 0],
    [0], [1],                  // feasible box
    stepSize: 0.5,
    tolerance: 1e-9,
);
// The solution is z = 1, where the upper-bound VI sign condition holds.
```

Each iterate retains the mapping value, box-projected candidate, step size,
and projected residual. The analyzer reports `solved` only when the residual
reaches the configured tolerance; otherwise it reports `partial`. This is a
bounded numerical method, not a proof of existence, uniqueness, monotonicity,
or global convergence for arbitrary nonsmooth/nonmonotone mappings.

## Complex equation systems

`ComplexSystemAnalyzer` solves a square system of up to eight complex
equalities with a local Newton iteration:

```php
use MathPHP\Explaining\ComplexSystemAnalyzer;

$analysis = (new ComplexSystemAnalyzer())->analyze(
    ['z + w = 1', 'z - w = i'],
    ['z', 'w'],
    ['z' => 0, 'w' => 0],
);
// z ≈ 0.5 + 0.5i, w ≈ 0.5 − 0.5i
```

The solver approximates a complex Jacobian with centered finite differences,
solves each Newton linear system with complex Gaussian elimination, and keeps
the full iterate/residual history. It reports one nearby root from the supplied
initial values; singular Jacobians, undefined expressions, and iteration limits
remain `partial`, so convergence is not a proof that all complex roots were
found.

For bounded discovery without manually choosing starts, use `analyzeGrid()`:

```php
$runs = (new NonlinearSystemAnalyzer())->analyzeGrid(
    'x^2 + y^2 = 5; x - y = 1',
    ['x', 'y'],
    ['x' => -3, 'y' => -3],
    ['x' => 3, 'y' => 3],
    pointsPerDimension: 5,
);
```

The helper creates evenly spaced starts in the finite box, caps the batch at
4096 starts, and reuses the same damped Newton/Gauss–Newton iteration. It is a
search aid rather than a completeness proof: each returned run retains its
own convergence status, residual history, and local-root limitations.

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

## Coupled ODE boundary-value systems

`NumericalOdeSystemBvpAnalyzer` finds an unknown initial vector whose RK4
trajectory reaches specified terminal values:

```php
use MathPHP\Explaining\NumericalOdeSystemBvpAnalyzer;

$analysis = (new NumericalOdeSystemBvpAnalyzer())->analyze(
    "x' = v; v' = -x",
    ['x', 'v'],
    ['x' => 0, 'v' => 0],
    ['x' => 0, 'v' => -1],
    targetIndependent: pi() / 2,
    steps: 100,
);
// The shooting result recovers an initial state near x=1, v=0.
```

The terminal-state map is finite-differenced and solved with bounded Newton
shooting. Each trial initial state, terminal residual, and final trajectory
summary is retained. This finds one local branch only; failed integrations,
singular shooting maps, and iteration limits are reported as `partial`.

## Retarded delay differential equations

`NumericalDelayOdeAnalyzer` covers scalar retarded equations whose derivative
depends on a fixed delayed state:

```php
use MathPHP\Explaining\NumericalDelayOdeAnalyzer;

$analysis = (new NumericalDelayOdeAnalyzer())->analyze(
    'yd',       // y'(t) = y(t - τ)
    '1',        // history for t <= 0
    delay: 0.5,
    initialIndependent: 0,
    targetIndependent: 2,
    steps: 200,
);
```

The analyzer uses method-of-steps Euler integration. History is evaluated for
delayed times at or before the initial coordinate; later delayed values are
linearly interpolated from completed trajectory samples and exposed as `yd`.
The finite trajectory is numerical (`complete: false`); neutral, advanced, and
state-dependent-delay equations remain unsupported by this discrete-lag
analyzer.

## Distributed-delay ODEs

`NumericalDistributedDelayOdeAnalyzer` covers a bounded scalar retarded
distributed-delay equation:

`y′(t) = f(t,y,yd)`, where `yd = ∫₀^τ K(s)y(t−s) ds`.

```php
use MathPHP\Explaining\NumericalDistributedDelayOdeAnalyzer;

$analysis = (new NumericalDistributedDelayOdeAnalyzer())->analyze(
    'yd',       // RHS receives the quadrature result as yd
    '1',        // history for t <= t0
    'exp(-s)',  // kernel K(s), with lag variable s
    0.5,        // maximum delay τ
    quadraturePoints: 32,
    steps: 200,
);
```

The analyzer uses composite-trapezoid quadrature over the lag interval and
method-of-steps Euler updates, retaining the distributed value at every
trajectory point. This is a finite numerical approximation; neutral, advanced,
state-dependent, and higher-dimensional distributed-delay equations remain
outside this focused contract.

## Neutral-delay ODEs

`NumericalNeutralDelayOdeAnalyzer` covers bounded scalar neutral retarded
equations where the RHS uses both a delayed state and delayed derivative:

`y′(t) = f(t,y,yd,ydd)`, with `yd = y(t−τ)` and `ydd = y′(t−τ)`.

```php
use MathPHP\Explaining\NumericalNeutralDelayOdeAnalyzer;

$analysis = (new NumericalNeutralDelayOdeAnalyzer())->analyze(
    '0.5*ydd',  // RHS receives the delayed derivative as ydd
    '1',        // history y(t)
    '1',        // history derivative y′(t)
    0.5,
    steps: 200,
);
```

The analyzer uses method-of-steps Euler integration, interpolating both state
and slope for delayed times and retaining them in every trajectory point.
Advanced, state-dependent, and neutral equations with discontinuous or
distributed derivative histories remain outside this focused contract.

## Coupled marked jump-diffusions

`NumericalJumpSdeSystemAnalyzer` extends vector Euler–Maruyama systems with a
marked compound-Poisson jump term for each component:

`dX_i = a_i(t,X)dt + b_i(t,X)dW_i + Σ J_i(t,X,m_k)`.

```php
use MathPHP\Explaining\NumericalJumpSdeSystemAnalyzer;

$analysis = (new NumericalJumpSdeSystemAnalyzer())->analyze(
    ['x', 'y'],
    ['0', '0'],
    ['0', '0'],
    ['m', '2*m'],       // jump expressions; m is a seeded uniform mark
    [4, 2],             // component Poisson intensities
    ['x' => 0, 'y' => 0],
    steps: 200,
    paths: 16,
);
```

Each component receives independent Poisson counts; every event gets a
reproducible mark under the supplied seed. Trajectory points retain jump
counts and accumulated jump sums, and endpoint means/variances are reported.
Correlated jump measures and state-dependent intensities remain outside this
focused contract.

## State-dependent-delay ODEs

`NumericalStateDependentDelayOdeAnalyzer` covers scalar retarded equations
whose lag depends on the current time and state:

`y′(t) = f(t,y,yd)`, with `yd = y(t − τ(t,y))`.

```php
use MathPHP\Explaining\NumericalStateDependentDelayOdeAnalyzer;

$analysis = (new NumericalStateDependentDelayOdeAnalyzer())->analyze(
    'yd',              // RHS receives the delayed value as yd
    '1',               // history for t <= t0
    '0.5 + 0.1*y',     // τ(t,y)
    1.0,               // maximum allowed lag
    targetIndependent: 1,
    steps: 200,
);
```

The lag expression is reevaluated at every step and must remain finite,
strictly positive, and no greater than `maximumDelay`. Delayed samples use
history evaluation before the initial time and linear interpolation afterward;
the evaluated lag and delayed value are retained in each point. Advanced,
discontinuous, and neutral state-dependent delays remain outside this focused
contract.

## Scalar Caputo fractional ODEs

`NumericalFractionalOdeAnalyzer` covers scalar Caputo initial-value equations
with order `0 < α < 1`:

```php
use MathPHP\Explaining\NumericalFractionalOdeAnalyzer;

$analysis = (new NumericalFractionalOdeAnalyzer())->analyze(
    '1',
    order: 0.5,
    initialValue: 0,
    initialIndependent: 0,
    targetIndependent: 1,
    steps: 200,
);
```

The explicit fractional Adams–Bashforth rule retains every forcing sample and
its power-law memory weight, so the nonlocal dependency is visible in the
result. It is a bounded numerical approximation (`complete: false`), not a
symbolic fractional solver; variable-order operators remain outside this
scalar contract.

## Variable-order Caputo fractional ODEs

`NumericalVariableOrderFractionalOdeAnalyzer` evaluates an explicit order
expression at each target coordinate, allowing bounded equations such as
`D_C^(0.5 + 0.1t) y = f(t,y)`:

```php
use MathPHP\Explaining\NumericalVariableOrderFractionalOdeAnalyzer;

$analysis = (new NumericalVariableOrderFractionalOdeAnalyzer())->analyze(
    '1',
    '0.5 + 0.1*t',
    0,
    targetIndependent: 1,
    steps: 200,
);
```

The result retains the per-step order history, forcing history, and trajectory.
The explicit contract requires every evaluated order to stay strictly within
`0 < α < 1`; mixed-order systems and symbolic fractional solutions remain
unsupported.

## Coupled Caputo fractional ODE systems

`NumericalFractionalOdeSystemAnalyzer` extends the same order range to a
coupled vector system. Each component shares the memory quadrature while its
forcing can reference every component of the current state:

```php
use MathPHP\Explaining\NumericalFractionalOdeSystemAnalyzer;

$analysis = (new NumericalFractionalOdeSystemAnalyzer())->analyze(
    "x' = y; y' = -x",
    ['x', 'y'],
    0.8,
    initial: ['x' => 1, 'y' => 0],
    targetIndependent: 1,
    steps: 200,
);
```

The result retains vector forcing history, component trajectories, and the
shared fractional order. Mixed orders can be supplied as a numeric/order-
expression map per component:

```php
$mixed = (new NumericalFractionalOdeSystemAnalyzer())->analyze(
    "x' = 1; y' = 2",
    ['x', 'y'],
    ['x' => 0.5, 'y' => '0.6 + 0.1*t'],
    initial: ['x' => 0, 'y' => 0],
);
```

Each component retains its own order history and memory weights. The bounded
explicit approximation still requires every evaluated order to remain in
`0 < α_i < 1`; symbolic fractional solutions are unsupported.

## One-dimensional time-fractional diffusion

`NumericalFractionalPdeAnalyzer` covers a bounded constant-order diffusion
model, `D_t^α u = κu_xx + s(x,t,u)`, with explicit spatial stencils and
configurable Dirichlet, Neumann, Robin, or paired periodic boundaries:

```php
use MathPHP\Explaining\NumericalFractionalPdeAnalyzer;

$analysis = (new NumericalFractionalPdeAnalyzer())->analyze(
    '0',                 // source s(x,t,u)
    0.8,                 // Caputo order
    0.01,                // diffusivity κ
    'x*(1-x)',           // initial profile
    '0',                 // left boundary
    '0',                 // right boundary
    spacePoints: 41,
    timeSteps: 100,
);
```

Use the optional boundary map for mixed conditions or a periodic interval. A
periodic pair wraps the duplicate endpoints to the opposite interior values
before each memory update:

```php
$periodic = (new NumericalFractionalPdeAnalyzer())->analyze(
    '0', 0.8, 0.01, 'x', '0', '0',
    spacePoints: 41,
    boundaryConditions: [
        'left' => ['type' => 'periodic'],
        'right' => ['type' => 'periodic'],
    ],
);
```

For a bounded nonlocal spatial operator, pass a fractional spatial order
`0 < β < 2` with `spatialOrder`. The solver evaluates the symmetric
finite-grid interaction
`L_βu(x_i) = Δx Σⱼ≠ᵢ (u(x_j) − u(x_i)) / |x_j − x_i|^(1+β)` and retains its
operator mode and stability estimate:

```php
$nonlocal = (new NumericalFractionalPdeAnalyzer())->analyze(
    '0', 0.8, 0.002, 'x*(1-x)', '0', '0',
    spacePoints: 41,
    timeSteps: 100,
    spatialOrder: 1.25,
);
```

`spatialOrder: 2.0` uses the existing local centered second derivative. The
nonlocal path is a bounded symmetric finite-grid approximation; singular
integrals, unbounded domains, and three-dimensional nonlocal kernels remain
outside this focused contract.

### Two-dimensional time-fractional diffusion

`NumericalFractionalPde2DAnalyzer` applies the same bounded constant-order
Caputo memory rule to rectangular fields of the form
`D_t^α u = κ(u_xx + u_yy) + s(x,y,t,u)`:

```php
use MathPHP\Explaining\NumericalFractionalPde2DAnalyzer;

$field = (new NumericalFractionalPde2DAnalyzer())->analyze(
    '0', 0.8, 0.01,
    'sin(pi*x)*sin(pi*y)',
    '0', '0', '0', '0',
    firstPoints: 33,
    secondPoints: 33,
    timeSteps: 40,
);
```

The result retains every two-dimensional forcing field, final values, and
intermediate snapshots, and exposes a `pde-heatmap-2d` visual representation.
Each axis accepts Dirichlet, Neumann, Robin, or paired periodic edges. The
implementation is an explicit bounded diffusion approximation. Pass
`spatialOrder` between `0` and `2` to use the same bounded symmetric nonlocal
kernel as the one-dimensional solver; `spatialOrder: 2.0` keeps the local
five-point Laplacian. Fractional wave equations, three-dimensional nonlocal
kernels, dimensions beyond this 3D contract, and symbolic fractional
solutions remain unsupported.

### Three-dimensional time-fractional diffusion

`NumericalFractionalPde3DAnalyzer` extends the bounded Caputo diffusion model
to `D_t^α u = κ(u_xx + u_yy + u_zz) + s(x,y,z,t,u)` on a rectangular box:

```php
use MathPHP\Explaining\NumericalFractionalPde3DAnalyzer;

$volume = (new NumericalFractionalPde3DAnalyzer())->analyze(
    '0', 0.8, 0.01,
    'sin(pi*x)*sin(pi*y)*sin(pi*z)',
    '0', '0', '0', '0', '0', '0',
    firstPoints: 9,
    secondPoints: 9,
    thirdPoints: 9,
    timeSteps: 20,
);
```

The six faces accept Dirichlet, Neumann, Robin, or paired periodic conditions;
the result retains volume forcing history and exposes `pde-heatmap-3d` data.
This remains an explicit bounded approximation, not a symbolic fractional PDE
solver. Pass `spatialOrder` between `0` and `2` to select the bounded symmetric
nonlocal volume kernel; `spatialOrder: 2.0` preserves the local seven-point
Laplacian. Unbounded and singular kernels remain outside this contract.

Neumann edges prescribe the outward first derivative; Robin edges prescribe
`alpha*u + beta*u_n = value`. Opposite periodic endpoints must be paired;
unpaired periodic or nonlocal spatial conditions remain unsupported.

## Variable-order one-dimensional fractional diffusion

`NumericalVariableOrderFractionalPdeAnalyzer` evaluates an order expression at
each spatial grid point and time step for bounded problems of the form
`D_t^(α(x,t,u)) u = κu_xx + s(x,t,u)`:

```php
use MathPHP\Explaining\NumericalVariableOrderFractionalPdeAnalyzer;

$field = (new NumericalVariableOrderFractionalPdeAnalyzer())->analyze(
    '0',
    '0.5 + 0.1*t',
    0.01,
    'x*(1-x)',
    '0', '0',
    spacePoints: 41,
    timeSteps: 100,
);
```

The result retains a complete order field for every snapshot alongside the
forcing history. Every evaluated order must remain strictly within
`0 < α < 1`; the implementation is an explicit bounded approximation, not a
symbolic fractional PDE solver. For constant-order nonlocal spatial kernels,
use the `spatialOrder` option on the fractional 1D, 2D, or 3D analyzers.

The variable-order 1D analyzer also accepts `spatialOrder` between `0` and `2`,
so the temporal order field and bounded symmetric nonlocal kernel can be used
together. `spatialOrder: 2.0` preserves the local centered derivative; the
result records `operatorMode` and the variable-order history together.

The solver retains every spatial forcing field used by the Caputo memory
quadrature, reports the fractional diffusion stability number, and marks runs
that exceed its explicit guard as `partial`. Fractional wave equations,
unbounded or singular spatial kernels, dimensions beyond the bounded 3D
diffusion contract, and symbolic fractional solutions remain outside this
focused contract.

## One-dimensional Caputo fractional waves

`NumericalFractionalWavePdeAnalyzer` covers bounded one-dimensional Caputo wave
equations with `1 < α ≤ 2`:
`D_t^α u = c²u_xx + s(x,t,u)`. It retains both the initial displacement and
initial velocity, then applies the explicit memory integral to every spatial
forcing field:

```php
use MathPHP\Explaining\NumericalFractionalWavePdeAnalyzer;

$wave = (new NumericalFractionalWavePdeAnalyzer())->analyze(
    '0',
    1.5,                 // Caputo wave order
    0.1,                 // wave speed c
    'x*(1-x)',           // initial displacement
    '0',                 // initial velocity
    '0', '0',             // left/right boundaries
    spacePoints: 41,
    timeSteps: 100,
);
```

The same Dirichlet, Neumann, Robin, and paired-periodic boundary map is
available as diffusion. Pass `spatialOrder` between `0` and `2` to select the
bounded symmetric nonlocal kernel; `spatialOrder: 2.0` preserves the centered
second derivative. Results expose `caputo-explicit-fractional-wave` (or its
nonlocal variant), the order, operator mode, stability number, forcing history,
and `pde-heatmap` snapshots. Fractional wave systems and 2D/3D fractional waves
remain outside this focused contract.

## Variable-order two-dimensional fractional diffusion

`NumericalVariableOrderFractionalPde2DAnalyzer` extends the explicit bounded
Caputo approximation to rectangular fields whose order is evaluated at every
grid node:
`D_t^(α(x,y,t,u)) u = κ(u_xx + u_yy) + s(x,y,t,u)`.

```php
use MathPHP\Explaining\NumericalVariableOrderFractionalPde2DAnalyzer;

$field = (new NumericalVariableOrderFractionalPde2DAnalyzer())->analyze(
    '0',
    '0.5 + 0.05*t + 0.02*u',
    0.01,
    'x + 2*y',
    '0', '0', '0', '0',
    firstPoints: 17,
    secondPoints: 17,
    timeSteps: 30,
);
```

Each snapshot retains both the field values and its two-dimensional order
field, so explanations can show how the memory exponent changes over space,
time, and state. The result exposes the `pde-heatmap-2d` visual and supports
Dirichlet, Neumann, Robin, or paired-periodic edges on both axes. Every
evaluated order must remain strictly within `0 < α < 1`; variable-order 3D
diffusion is documented below. Pass `spatialOrder` between `0` and `2` to
combine the variable temporal order with the bounded symmetric nonlocal
spatial kernel; `spatialOrder: 2.0` preserves the local five-point operator.
Both order and operator histories remain in the solution metadata. Variable-
order fractional waves and symbolic fractional solutions remain outside this
focused contract.

## Variable-order three-dimensional fractional diffusion

`NumericalVariableOrderFractionalPde3DAnalyzer` extends the same explicit
contract to bounded rectangular boxes with an order expression evaluated at
each volume node:
`D_t^(α(x,y,z,t,u))u = κ(u_xx + u_yy + u_zz) + s(x,y,z,t,u)`.

```php
use MathPHP\Explaining\NumericalVariableOrderFractionalPde3DAnalyzer;

$volume = (new NumericalVariableOrderFractionalPde3DAnalyzer())->analyze(
    '0',
    '0.5 + 0.02*t + 0.01*u',
    0.01,
    'x + 2*y + 3*z',
    '0', '0', '0', '0', '0', '0',
    firstPoints: 7,
    secondPoints: 7,
    thirdPoints: 7,
    timeSteps: 20,
);
```

The result retains a three-dimensional order field and forcing history for
every snapshot and exposes the `pde-heatmap-3d` visual. All six faces accept
Dirichlet, Neumann, Robin, or paired-periodic conditions; every evaluated
order must remain strictly within `0 < α < 1`. Pass `spatialOrder` between `0`
and `2` to combine the variable temporal order with a bounded symmetric
nonlocal volume kernel; `spatialOrder: 2.0` preserves the local seven-point
operator. The solution records the combined operator mode and retains both
histories, while nonlocal runs use a resource-aware grid cap. This remains an
explicit bounded approximation: unbounded or singular kernels, fractional wave
equations, and symbolic fractional solutions are outside the contract.

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
