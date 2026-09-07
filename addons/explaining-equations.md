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

## Generic calculus expressions

The same entry point recognizes compact symbolic derivative and antiderivative
requests and preserves the calculus steps and result metadata:

```php
$derivative = (new EquationAnalyzer())->analyze('d/dx (x^2 + sin(x))');
// solutions['method'] === 'automatic-calculus-derivative'
// solutions['result'] === '2x + cos(x)'

$integral = (new EquationAnalyzer())->analyze('∫ x^2 dx');
// solutions['method'] === 'automatic-calculus-integral'
// solutions['result'] === '0.33333333333333x^3 + C'
```

`derivative(expression, variable)` and `integral(expression, variable)` are
equivalent function forms. Elementary antiderivatives also apply substitution
to affine arguments such as `sin(2*x + 1)`, `exp(3*x - 2)`, `sqrt(4*x - 2)`,
and `(2*x + 1)^3`. Affine reciprocal powers use the logarithmic exception.
This is symbolic coverage for supported elementary operations;
branch-sensitive, non-elementary, or otherwise unsupported terms remain
explicitly `partial` or `unsupported`. See
[explaining calculus](explaining-calculus.md) for the direct analyzer API.

## Automatic separable ODE dispatch

First-order equations whose right-hand side factors independently into x-only
and y-only terms are separated automatically:

```php
$analysis = (new EquationAnalyzer())->analyze("y' = x*y");
// solutions['method'] === 'automatic-separable-ode'
// solutions['implicit'] contains the elementary implicit family.
```

`analyzeSeparableOde()` is the explicit facade. The bounded parser accepts
top-level products and quotients; each factor must depend on x, y, or neither.
Reciprocal affine and negative-power terms use the logarithmic/power rules from
the symbolic integrator. Mixed-variable factors are `unsupported`, while
separable equations with non-elementary separated integrals are `partial` and
retain both separated integrands instead of claiming a closed form.

## Generic numerical limits

Bounded numerical limits can use the same generic entry point:

```php
$limit = (new EquationAnalyzer())->analyze('limit(sin(x) / x, x, 0)');
// solutions['method'] === 'automatic-limit'
// solutions['limit'] is approximately 1

$oneSided = (new EquationAnalyzer())->analyze('lim_{x->0+} 1 / x');
// direction: right; samples and side evidence are retained
```

`limit(expression, variable, point[, direction])` accepts `left`, `right`, or
`both`; subscript notation also accepts `x→point` and one-sided `+`/`-`
suffixes. These results are finite geometric-sampling estimates with
`complete: false`, not symbolic proofs or global divergence classifications.
For explicit sample counts, tolerances, and known values, use `LimitAnalyzer`
directly.

## Generic finite sums and products

Finite series notation is evaluated term by term through the generic entry
point:

```php
$sum = (new EquationAnalyzer())->analyze('sum(k^2, k, 1, 5)');
// solutions['method'] === 'automatic-finite-sum'
// solutions['value'] === 55

$sigma = (new EquationAnalyzer())->analyze('Σ_{k=1}^{5} k^2');
// solutions['value'] === 55
```

`prod(expression, variable, start, end)` and `product(...)` use the same
integer-bound grammar. Direct evaluation is capped at 2,048 terms, and every
finite term is retained in the structured result. Undefined terms or ranges
over the cap remain explicitly `partial` or `unsupported`.

## Generic definite integrals

Bounded definite integrals use the same generic entry point:

```php
$area = (new EquationAnalyzer())->analyze('integral(x^2, x, 0, 1)');
// solutions['method'] === 'automatic-definite-integral'
// solutions['area'] is approximately 0.3333333333

$unicodeArea = (new EquationAnalyzer())->analyze('∫_0^1 x^2 dx');
```

Both forms use bounded Simpson sampling and return `complete: false`. If a
sample is undefined or non-finite, the result is `partial` and the gap is kept
visible rather than coerced to zero.

## Generic bounded optimization

One-variable bounded objectives can use golden-section search through the same
entry point:

```php
$minimum = (new EquationAnalyzer())->analyze('minimize((x - 2)^2, x, -5, 5)');
// solutions['method'] === 'automatic-bounded-minimize'
// solutions['optimum'] is approximately 2

$maximum = (new EquationAnalyzer())->analyze('argmax(-(x - 1)^2 + 4, x, -3, 3)');
// solutions['method'] === 'automatic-bounded-maximize'
// solutions['value'] is approximately 4
```

`min`, `max`, `argmin`, and `argmax` are accepted aliases. The iteration
history is retained, but `complete: false` is intentional: golden-section
search gives local numerical evidence and does not prove a global optimum for
multimodal objectives.

Two-variable bounded objectives use deterministic coarse-grid and local
refinement search:

```php
$minimum2d = (new EquationAnalyzer())->analyze(
    'minimize2d((x - 1)^2 + (y + 2)^2, x, y, -5, 5, -5, 5)'
);
// solutions['method'] === 'automatic-bounded-2d-minimize'
// solutions['optimum'] is approximately ['x' => 1, 'y' => -2]
```

`maximize2d`, `argmin2d`, and `argmax2d` are accepted aliases. The bounded
rectangle and refinement history are retained, but `complete: false` is
intentional because multimodal global optimality is not proven.

## Generic bounded transforms

Bounded numerical Laplace and Fourier transforms can use the generic entry
point:

```php
$laplace = (new EquationAnalyzer())->analyze('laplace(t, t, 1, 0, 1)');
// solutions['method'] === 'automatic-laplace-transform'

$fourier = (new EquationAnalyzer())->analyze('fourier(1, x, 0, 0, 6.283185307179586)');
// solutions['method'] === 'automatic-fourier-transform'
// solutions['result'] contains real, imaginary, magnitude, and phase
```

The dispatcher uses 101-point composite Simpson sampling over the supplied
finite interval. Results are bounded numerical evidence with `complete: false`;
infinite-domain convergence, symbolic inversion, and transform theorems are
not inferred from the finite sample.

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
`partial`.

When the rule is a numeric first- or second-order linear recurrence, the same
result also includes a characteristic equation and a closed form:

```php
$analysis = (new RecurrenceAnalyzer())->analyze(
    'a[n+2] = a[n+1] + a[n]',
    [0 => 0, 1 => 1],
);
// $analysis->solution['closedForm'] contains the Fibonacci family.
// $analysis->solution['characteristicRoots'] contains both roots.
```

Distinct real roots, repeated roots, and complex-conjugate roots are reported
with separate branch metadata. Numeric linear recurrences through order eight
with distinct, well-conditioned roots receive a generalized complex closed
form reconstructed from the supplied seeds. Repeated roots are reconstructed
with polynomial-in-index factors when the generated sequence verifies the
recurrence. Near-repeated or ill-conditioned roots retain characteristic
metadata with `closedFormComplete: false`; higher-
order beyond the supported bound, nonlinear, variable-coefficient, or
incomplete-seed recurrences continue to return bounded terms without an
invented infinite closed form.

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

The generic `EquationAnalyzer::analyze()` entry point recognizes seeded
recurrence systems when the seeds and rules are supplied together:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'a[0] = 0; a[1] = 1; a[n+2] = a[n+1] + a[n]',
);
// solutions['sequence'] contains 12 finite terms
// solutions['method'] === 'automatic-bounded-recurrence'
```

Multiple seeded rules use synchronous expansion and return
`automatic-bounded-recurrence-system`. The default is deliberately finite
(12 terms); use `analyzeRecurrence()` or `analyzeRecurrenceSystem()` to choose
the term count, start index, and known forcing parameters.

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

The Core evaluator also provides domain-checked special functions such as
`erf`, `erfc`, `gamma`, and `lgamma`. They can be used directly in bounded
equation searches and in any explaining analyzer that evaluates Core
expressions.

The generic `EquationAnalyzer::analyze()` entry point uses the exact polynomial,
rational, and elementary strategies first. When none applies but the equality
is a single-variable Core expression, it now delegates automatically to the
bounded numerical solver on `[-100, 100]`. Such results expose
`method: automatic-bounded-numerical` and `automaticDomain`; call
`analyzeNumerically()` when a different interval, sample count, or refinement
depth is required.

Product-exponential equations of the form `(a*x+b)*exp(a*x+b) = c` are
dispatched to a real Lambert-W transformation before numerical sampling:

```php
$analysis = (new EquationAnalyzer())->analyze('x*exp(x) = -0.1');
// solutions['method'] === 'automatic-lambert-w'
// solutions['branches'] === [0, -1]
// solutions['roots'] contains both real roots
```

Use `analyzeLambertW()` for the explicit facade. The result exposes the
Lambert-W argument, real branch list, transformed general form, and the
real-domain cutoff at `-1/e`. Expressions outside this strict shape continue
through the bounded numerical route.

Self-power equations such as `x^x = c` are also analyzed on the positive-real
domain:

```php
$analysis = (new EquationAnalyzer())->analyze('x^x = 0.8');
// solutions['method'] === 'automatic-self-power'
// solutions['branches'] === [0, -1]
// solutions['roots'] contains both positive-real roots
```

Use `analyzeSelfPower()` for the explicit facade. The result exposes the
positive-domain minimum `e^(-1/e)`, Lambert-W branches, and a
`positiveDomainComplete` flag. Negative-base rational-exponent cases remain an
explicit domain boundary.

Exponential-linear equations such as `exp(x) = 3*x` are solved through all
real Lambert-W branches:

```php
$analysis = (new EquationAnalyzer())->analyze('exp(x) = 3*x');
// solutions['method'] === 'automatic-exponential-linear'
// solutions['branches'] === [0, -1]
// solutions['roots'] contains both real roots
```

Use `analyzeExponentialLinear()` for the explicit facade. Affine right-hand
sides such as `exp(x) = 3*x + 3` are supported; constant exponents fall back to
exact linear isolation. Other forms continue through bounded numerical
analysis.

Logarithmic-product equations such as `x*ln(x) = -0.1` are also reduced to
Lambert-W branches on the positive domain:

```php
$analysis = (new EquationAnalyzer())->analyze('x*ln(x) = -0.1');
// solutions['method'] === 'automatic-log-product'
// solutions['branches'] === [0, -1]
// solutions['roots'] contains both positive-real roots
```

Use `analyzeLogProduct()` for the explicit facade. Affine forms such as
`(2*x+1)*ln(2*x+1)=1` are supported; the result exposes the `-1/e` cutoff and
retains explicit metadata for logarithm-domain limits.

Affine-base power equations are also analyzed directly:

```php
$analysis = (new EquationAnalyzer())->analyze('x^0.5 = 4');
// solutions['method'] === 'automatic-affine-base-power'
// solutions['roots'] contains [16]
```

Use `analyzePower()` for the explicit facade. Integer exponents retain odd/even
real branches, while non-integer exponents use the positive-base domain and
expose `domainNote`/`complete` metadata. Negative powers exclude a zero base.

Implicit multiplication is accepted in algebraic equation paths, including
`x(x+1)=6` and `(x+1)(x-1)=0`. The normalizer rewrites only an inferred
variable immediately before `(`; named calls such as `sin(x)` remain function
calls, and the original input is preserved in the returned analysis.

Discrete factorial equations are supported on the non-negative integer domain:

```php
$analysis = (new EquationAnalyzer())->analyze('n! = 120');
// solutions['method'] === 'automatic-factorial'
// solutions['roots'] === [5]
```

Use `analyzeFactorial()` for the explicit facade. The result exposes its
integer search limit, domain note, completeness flag, and duplicate roots for
`0! = 1! = 1`.

Variable-free equalities are evaluated directly:

```php
$analysis = (new EquationAnalyzer())->analyze('sqrt(4) = 2');
// solutions['method'] === 'constant-equality'
// solutions['satisfied'] === true
```

Use `analyzeConstant()` for the explicit facade. The result includes both
evaluated sides, residual, tolerance, and a complete satisfaction flag; any
unresolved variable is left for the relevant analyzer.

Discrete real functions return complete intervals instead of sampled points:

```php
$analysis = (new EquationAnalyzer())->analyze('floor(x) = 3');
// solutions['method'] === 'discrete-function-interval'
// solutions['intervals'] === [[
//     'lower' => 3.0, 'upper' => 4.0,
//     'lowerInclusive' => true, 'upperInclusive' => false,
// ]]
```

Affine `floor`, `ceil`, `round` (PHP half-up ties), and `sign` forms preserve
open and closed endpoints and report `complete: true`. Use
`analyzeDiscrete()` to choose the variable or provide known parameters.

Affine-vs-constant `min()` and `max()` forms also return complete interval
solutions:

```php
$analysis = (new EquationAnalyzer())->analyze('max(-x, 5) = 5');
// solutions['method'] === 'piecewise-function-interval'
// solutions['intervals'] === [[
//     'lower' => -5.0, 'upper' => null,
//     'lowerInclusive' => true, 'upperInclusive' => false,
// ]]
```

Use `analyzePiecewiseFunction()` for the explicit facade. Singleton roots,
closed half-lines, and empty sets retain `complete: true` metadata.

Modulo equalities are solved over Core's integer domain:

```php
$analysis = (new EquationAnalyzer())->analyze('x % 2 = 0');
// solutions['method'] === 'integer-congruence'
// solutions['variableDomain'] === 'integers'
// solutions['residue'] === 0
// solutions['modulus'] === 2
```

Affine dividends retain signed-remainder constraints and complete residue
classes. Modulo by zero and non-integer operands remain explicit
`unsupported` results.

One-variable `hypot()` equalities use the Euclidean norm identity directly:

```php
$analysis = (new EquationAnalyzer())->analyze('hypot(x, 3) = 5');
// solutions['method'] === 'exact-hypot'
// solutions['roots'] === [-4.0, 4.0]
// solutions['complete'] === true
```

Affine components, empty radius domains, and fixed-component metadata are
preserved. Use `analyzeHypot()` for the explicit facade.

Core’s stable logarithm/exponential variants use exact inverse analysis for
affine inputs:

```php
$analysis = (new EquationAnalyzer())->analyze('log2(x) = 3');
// solutions['method'] === 'exact-elementary-inverse'
// solutions['roots'] === [8.0]
```

`log1p()` retains its `inner > -1` domain and `expm1()` rejects targets at or
below `-1`.

Affine `erf()` and `erfc()` equalities now use complete monotone inverse
analysis:

```php
$analysis = (new EquationAnalyzer())->analyze('erf(x) = 0.5');
// solutions['method'] === 'monotone-special-inverse'
// solutions['roots'] contains approximately [0.476936128]
// solutions['complete'] === true
```

The open range endpoints are handled explicitly: `erf(x) = ±1` and
`erfc(x) = 0 or 2` have no finite real roots. `gamma()` and `lgamma()` remain
branch-sensitive numerical analyses.

Affine `atan2()` equations are solved with complete quadrant-aware metadata:

```php
$analysis = (new EquationAnalyzer())->analyze('atan2(x, 1) = 0.5');
// solutions['method'] === 'exact-atan2'
// solutions['roots'] contains [tan(0.5)]
// solutions['complete'] === true
```

When an axis angle describes a half-line, the result includes open interval
metadata so the undefined origin is not accidentally included. Impossible
quadrant targets return complete empty results.

`gamma()` and `lgamma()` affine equations are searched branch-by-branch without
crossing their non-positive-integer poles:

```php
$analysis = (new EquationAnalyzer())->analyze('gamma(x) = 1');
// solutions['method'] === 'branch-safe-special-numerical'
// solutions['complete'] === false

$zero = (new EquationAnalyzer())->analyze('gamma(x) = 0');
// solutions['roots'] === []
// solutions['complete'] === true
```

Pole metadata and branch status are returned explicitly. Nonzero gamma and
lgamma searches remain partial because a global symbolic root proof is not
claimed.

Variadic `min()` and `max()` calls with one affine branch and multiple fixed
branches are reduced to complete intervals:

```php
$analysis = (new EquationAnalyzer())->analyze('min(x, 2, 3, 5) = 2');
// solutions['fixedBranches'] === [2.0, 3.0, 5.0]
// solutions['complete'] === true
```

The controlling fixed branch is selected with the same minimum/maximum
semantics as Core, and the result preserves endpoint inclusion.

Multiple affine branches are solved by checking each branch candidate against
all other branches:

```php
$analysis = (new EquationAnalyzer())->analyze('max(x, -x) = 1');
// solutions['roots'] === [-1.0, 1.0]
// solutions['complete'] === true
```

This prevents a branch that reaches the target but is not the controlling
minimum/maximum branch from being reported as a false solution.

Core's one-argument identity forms are also solved completely, for example
`min(x) = 2` and `max(2*x + 1) = 5`.

Multivariable affine piecewise equalities return exact inequality regions:

```php
$analysis = (new EquationAnalyzer())->analyze('min(x, y) = 1');
// solutions['method'] === 'exact-piecewise-region'
// x >= 1, y >= 1, with x = 1 or y = 1 active
// solutions['complete'] === true
```

This keeps the full union-of-boundaries representation instead of sampling a
bounded contour. Use `analyzePiecewiseRegion()` when controlling the call
explicitly.

Piecewise rows can also be composed into a complete system region:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'min(x, y) = 1; max(x, y) = 2'
);
// solutions['method'] === 'exact-piecewise-system'
// solutions['complete'] === true
```

The result keeps constraints tagged by equation row and preserves active
branch alternatives. Mixed non-piecewise systems continue through the general
nonlinear-system path.

Affine equality rows are now composed exactly with piecewise rows:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'min(x, y) = 1; x + y = 3'
);
// exact-piecewise-system with x >= 1, y >= 1, and x + y = 3
// solutions['complete'] === true
```

Nonlinear rows remain explicitly delegated to the nonlinear-system analyzer.

Integer-only `gcd()` and `lcm()` equalities use complete integer-domain
analysis:

```php
$gcd = (new EquationAnalyzer())->analyze('gcd(x, 6) = 2');
// solutions['method'] === 'integer-gcd'
// solutions['variableDomain'] === 'integers'
// solutions['period'] === 6
// solutions['residueClasses'] === [2, 4]

$lcm = (new EquationAnalyzer())->analyze('lcm(x, 6) = 12');
// solutions['method'] === 'integer-lcm'
// solutions['roots'] === [-12.0, -4.0, 4.0, 12.0]
```

Affine arguments such as `gcd(2*x, 6)` are supported. Non-integer arguments
remain explicit `unsupported` results because these Core functions are defined
on integers.

The same generic entry point recognizes semicolon- or newline-separated systems
when every row is an equality. It first attempts exact Gaussian elimination for
affine rows, returning `method: automatic-linear-system` with unique,
inconsistent, or underdetermined metadata. Nonlinear systems then fall back to
one damped Newton solve from zero, returning `method:
automatic-nonlinear-system` and `automaticInitial`. This is intentionally one
local solve: use `analyzeNonlinearSystemMany()` or
`analyzeNonlinearSystemGrid()` to explore multiple basins and roots.

Inequalities and chained relations are also recognized by the generic
`EquationAnalyzer::analyze()` entry point. They delegate to the exact/sampled
bounded inequality analyzer on `[-100, 100]`, expose
`method: automatic-bounded-inequality` and `automaticDomain`, and remain
bounded results rather than global proofs. Use `analyzeInequality()` to choose
the interval and sampling controls explicitly.

Two-variable inequalities use a separate bounded region sampler. For example:

```php
$analysis = (new EquationAnalyzer())->analyze('x^2 + y^2 <= 9');
// solutions['method'] === 'automatic-bounded-inequality-region'
// solutions['cells'] contains fully feasible grid cells
// solutions['boundaryCells'] contains mixed or undefined cells
// solutions['complete'] === false
```

The automatic route infers the two real unknowns and samples
`[-10, 10] × [-10, 10]`. `NumericalImplicitInequalityAnalyzer` and
`analyzeNumericalImplicitInequality()` let callers choose the rectangle and
grid resolution. A cell is classified from its four corner samples, so the
result is deliberately `partial`: narrow feasible bands, disconnected pieces,
and features between grid points require a finer grid or a domain-specific
symbolic solver. Undefined or non-finite samples are kept in
`boundaryCells` rather than silently counted as feasible.

Piecewise and `if(...)` equalities are detected before relation dispatch and
use `PiecewiseEquationAnalyzer` branch by branch. The generic path reports
`method: automatic-bounded-piecewise`, records `automaticDomain`, and keeps
branch jumps or undefined samples as `partial` evidence rather than treating a
discontinuity as a root.

Equalities containing the standalone imaginary unit `i` are routed to the
complex Newton analyzer before real-valued fallbacks. The generic path infers
the unknown, starts at `0 + 0i`, and returns `method:
automatic-complex-newton`, `automaticInitial`, one local complex root, and its
residual history:

```php
$analysis = (new EquationAnalyzer())->analyze('exp(z) = i');
// solutions['root'] ≈ ['real' => 0, 'imaginary' => π/2]
// solutions['method'] === 'automatic-complex-newton'
```

This is intentionally a local solve, not a proof that every complex root was
found. Use `analyzeComplex()` with an explicit `ComplexNumber` start, iteration
limit, tolerance, and optional known scalar parameters when the initial basin
must be controlled. A square semicolon/newline-separated system containing `i`
is also routed automatically to `ComplexSystemAnalyzer` (up to eight unknowns):

```php
$analysis = (new EquationAnalyzer())->analyze('z + w = 1; z - w = i');
// solutions['z'] ≈ 0.5 + 0.5i; solutions['w'] ≈ 0.5 − 0.5i
// solutions['method'] === 'automatic-complex-system-newton'
```

Use `analyzeComplexSystem()` when variable ordering, starting values, or a
different basin must be controlled. Neither automatic route proves that every
complex root was found.

## Implicit two-variable equations

A single equality in two unknowns usually describes a curve rather than a
finite list of roots. `NumericalImplicitEquationAnalyzer` samples a bounded
rectangle and returns marching-squares segments for the approximate zero
contour:

The generic `EquationAnalyzer::analyze()` entry point automatically recognizes
exactly two real unknowns and delegates to the same sampler on the default
domain `[-10, 10] × [-10, 10]`. It returns
`method: automatic-bounded-implicit`, `automaticDomain`, and
`complete: false`; use the explicit analyzer when the curve is larger than
that window or needs a finer grid.

## Dimensional equalities

When `mathphp/mathphp-units` is installed, `UnitEquationAnalyzer` evaluates
both sides through the quantity grammar, compares dimensions and normalized
base values, and keeps the display-unit conversions in the result:

```php
$analysis = (new EquationAnalyzer())->analyze('2m + 200cm = 4m');
// solutions['satisfied'] === true
// solutions['method'] === 'unit-dimensional-equality'
```

The generic entry point detects numeric unit literals automatically. Use
`analyzeUnitEquation()` when variables are `Quantity` objects or when a custom
`UnitCatalog` is needed. Mismatched dimensions are reported as an evaluated,
unsatisfied equality; they are never coerced into scalar values.

## Automatic first-order ODE dispatch

The generic `EquationAnalyzer::analyze()` entry point recognizes first-order
constant-coefficient derivative notation and delegates to the symbolic ODE
analyzer:

```php
$analysis = (new EquationAnalyzer())->analyze("y' = 2*y + 3");
// solutions['general'] contains y(x) = C·exp(2·x) − 1.5
// solutions['method'] === 'automatic-linear-ode'
```

`dy/dx = 4` is accepted as well. Elementary forcing is supported for numeric
or x-dependent p(x):

```php
$analysis = (new EquationAnalyzer())->analyze("y' + 2*y = x");
// solutions['method'] === 'automatic-elementary-linear-ode'
// solutions['forcingAntiderivative'] is retained.
```

`analyzeElementaryLinearOde()` is the explicit facade. The coefficient p(x) and
forcing q(x) must use only the independent variable and supported elementary
operations. If p(x) or the integrating-factor product is not elementary, the
result is `partial`; a q(x) that contains y is not treated as linear forcing.

Constant-coefficient Bernoulli forms use the
`v = y^(1−n)` substitution:

```php
$analysis = (new EquationAnalyzer())->analyze("y' + 2*y = 4*y^3");
// solutions['method'] === 'automatic-bernoulli-ode'
// solutions['general'] is the generic non-zero branch.
```

`analyzeBernoulliOde()` is the explicit facade. Numeric constant `p`, `q`, and
`n` are required, with `n` outside 0 and 1. The result includes `domainNote`
and `complete: false` because real power branches, singular solutions, and
domain restrictions require caller-side interpretation. Other nonlinear,
initial-value, higher-order, delayed, and fractional ODEs remain explicit APIs because they require
additional conditions or numerical controls that a bare equality does not
provide.

### Constant-coefficient Riccati equations

Quadratic first-order equations of the form `y' = a*y² + b*y + c` are routed
through a branch-aware Riccati analyzer:

```php
$analysis = (new EquationAnalyzer())->analyze("y' = y^2 + 1");
// solutions['method'] === 'automatic-riccati-ode'
// solutions['branch'] === 'complex-equilibria'
```

The discriminant selects the two-real-equilibria, repeated-equilibrium, or
complex-equilibria family. `analyzeRiccatiOde()` is the explicit facade. The
result retains `a`, `b`, `c`, the discriminant, branch metadata, and a
`domainNote`; `complete` is false because arbitrary constants can create poles,
and equilibrium solutions and maximal intervals must be interpreted against
the caller's initial data. Non-quadratic or variable-coefficient Riccati
forms remain explicit numerical/partial cases.

### Homogeneous first-order equations

Equations whose right-hand side depends on the ratio `y/x` are routed through
the substitution `v = y/x` when that ratio forcing is a numeric quadratic:

```php
$analysis = (new EquationAnalyzer())->analyze("y' = (y/x)^2 + 1");
// solutions['method'] === 'automatic-homogeneous-ode'
// solutions['branch'] === 'complex-ratio-equilibria'
```

The analyzer returns logarithmic, power, real-ratio, repeated-ratio, or
tangent branches and exposes `analyzeHomogeneousOde()` for direct use. Every
branch retains the `x ≠ 0` domain requirement and pole metadata; non-quadratic
ratio functions or mixed x/y dependence remain explicit numerical or partial
cases.

### Logistic first-order equations

Factored logistic equations `y' = r*y*(1-y/K)` are recognized:

```php
$analysis = (new EquationAnalyzer())->analyze("y' = 2*y*(1-y/10)");
// solutions['method'] === 'automatic-logistic-ode'
// solutions['branch'] === 'nonzero-growth'
```

`analyzeLogisticOde()` is the explicit facade. The result retains `rate`,
`carryingCapacity`, both equilibrium solutions, `complete: false`, and a
denominator-domain note. Zero-growth forms return the full constant family;
nonnumeric, differently factored, or variable-coefficient forms remain
explicit numerical or partial cases.

### Exact first-order equations

The generic dispatcher also recognizes exact equations when the derivative can
be isolated into the additive form `M(x,y) + N(x,y)y' = 0` (or `dy/dx`):

```php
$analysis = (new EquationAnalyzer())->analyze(
    "2*x*y + 1 + (x^2 + 3*y^2)*dy/dx = 0",
);
// solutions['method'] === 'automatic-exact-ode'
// solutions['implicit'] is ψ(x,y) = C
```

The analyzer differentiates `M` and `N`, checks `∂M/∂y = ∂N/∂x` on finite
domain samples, integrates a potential, and verifies both recovered partial
derivatives. `analyzeExactOde()` is the explicit facade. The result includes
`M`, `N`, `dMdy`, `dNdx`, `potential`, `exactnessSamples`, and
`verificationSamples`; non-exact forms and expressions outside the bounded
elementary differentiator/integrator are retained as explicit
`unsupported`/`partial` results rather than being presented as universal
solutions.

The generic entry point can route a complete numeric IVP when its conditions
are included in the same semicolon-separated input:

```php
$analysis = (new EquationAnalyzer())->analyze("y' = sin(x) + y; y(0) = 1");
// solutions['method'] === 'automatic-numerical-ivp'
// solutions['automaticDomain'] === [0.0, 1.0]

$second = (new EquationAnalyzer())->analyze("y'' = -y; y(0) = 1; y'(0) = 0");
// solutions['method'] === 'automatic-numerical-second-order-ivp'
```

The dispatcher also accepts apostrophe notation through order 32 when every
initial derivative is supplied. It uses a finite `[x₀, x₀ + 1]` interval and
128 RK4 steps, returning `partial` if evaluation becomes undefined or
non-finite. This is convenience dispatch, not a global existence theorem; use
the explicit numerical ODE APIs to select domains, step counts, or richer
initial-state controls.

Coupled first-order IVPs can be routed automatically as well:

```php
$analysis = (new EquationAnalyzer())->analyze(
    "x' = v; v' = -x; x(0) = 1; v(0) = 0",
);
// solutions['method'] === 'automatic-numerical-ode-system'
// solutions['final']['values'] contains x and v at the target coordinate
```

The dispatcher infers the state variables, uses `t` for apostrophe notation,
and uses the coordinate named by `dx/dt`-style notation when present. Every
state must have one numeric condition at the same initial coordinate. The
automatic run uses `[t₀, t₀ + 1]` and 128 vector-RK4 steps; choose
`analyzeNumericalOdeSystem()` for explicit control or additional configuration.

Second-order constant-coefficient forms are also recognized automatically:

```php
$analysis = (new EquationAnalyzer())->analyze("y'' + y = 0");
// solutions['roots'] contains the complex characteristic pair
// solutions['method'] === 'automatic-second-order-ode'
```

The route returns the symbolic general solution and characteristic metadata;
call `analyzeSecondOrderOde()` when initial values are available.

Separable first-order forms such as `y' = x*y` are also recognized with
`automatic-separable-ode`. The result retains the separated integrands and an
implicit elementary family; use `analyzeSeparableOde()` for direct access.

The generic dispatcher also recognizes a bounded second-order boundary-value
problem when two endpoint values are supplied:

```php
$bvp = (new EquationAnalyzer())->analyze(
    'd2y/dx2 = 0; y(0) = 0; y(1) = 1; x = {0,1}; steps = 64'
);
// solutions['method'] === 'automatic-boundary-value-ode'
// solutions['automaticDomain'] === [0.0, 1.0]
```

The compact route uses bounded shooting with RK4 and returns the selected
initial slope, endpoint residual, and trajectory. `steps`, `iterations`, and
`tolerance` clauses are optional. It reports `partial` when shooting cannot
meet the residual tolerance; it does not prove uniqueness or cover derivative,
multi-point, singular, or higher-order boundary conditions. Use
`analyzeNumericalBoundaryValueOde()` for those explicit controls.

Scalar differential inclusions are also recognized by the generic dispatcher:

```php
$inclusion = (new EquationAnalyzer())->analyze(
    'dy/dt in [0,1]; y(0) = 0; t = {0,0.2}; steps = 16; selection = upper'
);
// solutions['method'] === 'automatic-differential-inclusion'
// solutions['automaticSelection'] === 'upper'
```

The lower and upper expressions define an interval-valued slope. The bounded
route retains both Euler envelope trajectories plus the selected demonstrative
path; it is not a proof of reachable-set containment. Use
`analyzeNumericalDifferentialInclusion()` for explicit domains, controls, or
known constants.

Complete compact index-1 differential-algebraic systems are also recognized by
the generic dispatcher:

```php
$analysis = (new EquationAnalyzer())->analyze(
    "x' = -z; z - x = 0; x(0) = 1; z(0) = 1",
);
// solutions['method'] === 'automatic-numerical-dae'
// solutions['final']['values'] contains the projected state
```

The parser requires at least one derivative equation, one algebraic constraint,
and one numeric initial value for every state at the same coordinate. The
automatic route uses projected Euler integration on `[t₀, t₀ + 1]` with 128
steps and preserves constraint residuals and `partial` failure states. Use
`analyzeNumericalDae()` when mass matrices, differentiated constraints,
tolerances, or custom intervals are needed.

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

## Numeric matrix equations

The generic entry point also accepts numeric square matrix equations:

```php
$matrix = (new EquationAnalyzer())->analyze('A = [[2,1],[1,3]]; b = [1,2]');
// solutions['method'] === 'automatic-matrix-equation'
// solutions['solution'] === [0.2, 0.6]
```

The result retains Gaussian-elimination rank, consistency, and augmented
matrix diagnostics. Use `analyzeMatrixEquation()` when the matrix and vector
are already available as PHP arrays.

Trace, rank, determinant, transpose, inverse, and bounded spectral operations
are also available for numeric matrices through the generic entry point:

```php
$determinant = (new EquationAnalyzer())->analyze('det([[1,2],[3,4]])');
// solutions['method'] === 'automatic-matrix-determinant'
// solutions['result'] === -2

$spectrum = (new EquationAnalyzer())->analyze('eigenvalues([[2,1],[1,2]])');
// solutions['result'] === [3, 1]

$determinant3d = (new EquationAnalyzer())->analyze('det([[1,2,3],[0,1,4],[5,6,0]])');
// solutions['result'] is approximately 1

$trace = (new EquationAnalyzer())->analyze('trace([[1,2,3],[0,1,4],[5,6,0]])');
// solutions['result'] === 2

$rank = (new EquationAnalyzer())->analyze('rank([[1,2,3],[2,4,6]])');
// solutions['result'] === 1
```

`transpose(...)` accepts rectangular matrices; `inverse(...)` and `det(...)`
require square matrices up to 32 dimensions; `rank(...)` accepts rectangular
matrices up to 32 rows and 32 columns. `eigenvalue(...)` and `spectrum(...)`
require square numeric matrices up to 12×12. The 2×2 case uses an exact
characteristic formula; larger spectra use bounded numerical roots and may be
`partial` when the iteration does not converge. Complex eigenvalues retain
explicit real/imaginary components. Singular inverses remain `partial`.

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

The generic `EquationAnalyzer::analyze()` entry point recognizes a compact
bounded syntax for these four solver variants. A linear Fredholm equation can
be written as:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u(x) = 1 + 0.5 * integral{0,1}(1*u(t))',
);
// solutions['method'] === 'automatic-fredholm-integral'
// solutions['automaticDomain'] === [0.0, 1.0]
```

Use `integral{0,x}` for the causal Volterra form (square-bracket bounds are
also accepted). If the integrand is nonlinear in the unknown, such as
`integral{0,1}(u(t)^2)`, the dispatcher
selects bounded Picard iteration and returns
`automatic-nonlinear-fredholm-integral` (or the Volterra equivalent). The
compact grammar requires the quadrature variable `t`, uses 32 midpoint
points, and always marks the finite result `complete: false`; use the explicit
facades for resolution, iteration, or custom intervals.

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

The generic `EquationAnalyzer::analyze()` entry point recognizes a compact
scalar form when the stochastic clauses are included:

```php
use MathPHP\Explaining\EquationAnalyzer;

$analysis = (new EquationAnalyzer())->analyze(
    'dX = 0.2*x*dt + 0.5*dW; X(0) = 1; target = 1; steps = 128; paths = 16; seed = 42',
);
// solutions['method'] === 'automatic-euler-maruyama-sde'
// solutions['automaticInitial'] === ['t' => 0.0, 'x' => 1.0]
```

The compact form requires the state name `X`, binds drift and diffusion
expressions to `t` and `x`, and uses seeded Euler–Maruyama paths. The default
bounded run uses 128 steps, 16 paths, and seed `12345`; add `steps`, `paths`,
or `seed` clauses to override those defaults. Results remain stochastic finite
approximations with `complete: false`.

Coupled systems use repeated stochastic clauses followed by one initial value
per state:

```php
$system = (new EquationAnalyzer())->analyze(
    'dX = V*dt + 0*dW; dV = -X*dt + 0*dW; X(0) = 1; V(0) = 0; target = 1; paths = 8; seed = 42',
);
// solutions['method'] === 'automatic-euler-maruyama-sde-system'
// solutions['automaticVariables'] === ['X', 'V']
```

The generic system form uses independent Brownian components and the same
bounded defaults as the scalar form. Use
`analyzeNumericalSdeSystem()` when a covariance matrix, larger state set, or
other simulation control is required.

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
inversion sampler requires intensity × step size ≤ 50.

The generic `EquationAnalyzer::analyze()` entry point recognizes a compact
scalar jump-diffusion when the intensity is declared explicitly:

```php
$jump = (new EquationAnalyzer())->analyze(
    'dX = 0*dt + 0*dW + 1*dN; intensity(X) = 2; X(0) = 0; target = 1; seed = 42',
);
// solutions['method'] === 'automatic-compound-poisson-sde'
```

Repeat the derivative and state clauses for a coupled system:

```php
$system = (new EquationAnalyzer())->analyze(
    'dX = 0*dt + 0*dW + m*dN; dY = 0*dt + 0*dW + 2*m*dN; intensity(X) = 2; intensity(Y) = 1; X(0) = 0; Y(0) = 0; target = 1',
);
// solutions['method'] === 'automatic-compound-poisson-sde-system'
```

Each event mark `m` is seeded and uniform. Add `steps`, `paths`, and `seed`
clauses for bounded controls; use the explicit facades for correlated jump
measures or state-dependent intensities.

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

The generic `EquationAnalyzer::analyze()` entry point recognizes a fully
specified one-dimensional Dirichlet problem:

```php
use MathPHP\Explaining\EquationAnalyzer;

$analysis = (new EquationAnalyzer())->analyze(
    'u_t = 0.1*u_xx; u(x,0) = 1; u(0,t) = 1; u(1,t) = 1; x = {0,1}; t = {0,0.1}; spacePoints = 41; timeSteps = 100',
);
// solutions['method'] === 'automatic-bounded-parabolic-pde'
// solutions['automaticDomain'] === [0.0, 1.0, 0.0, 0.1]
```

The compact route requires explicit space/time domains and uses paired
Dirichlet values. Use `analyzeNumericalPde()` for Neumann, Robin, periodic,
non-default coordinates, or custom resolution.

The generic dispatcher also recognizes a fully specified bounded two-dimensional
elliptic Dirichlet problem:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_xx + u_yy = 0; u(0,y) = 0; u(1,y) = 0; u(x,0) = 0; u(x,1) = 0; x = {0,1}; y = {0,1}; firstPoints = 25; secondPoints = 25',
);
// solutions['method'] === 'automatic-bounded-elliptic-pde'
// solutions['automaticDomain'] === [0.0, 1.0, 0.0, 1.0]
```

The compact route selects the bounded Gauss–Seidel solver and returns the
finite grid, residual metrics, and retained field snapshots. It infers only
four Dirichlet edges; use `analyzeNumericalEllipticPde()` for Neumann, Robin,
periodic, mixed-derivative, or custom solver controls. Non-elliptic principal
parts and nonlinear spatial derivative terms remain explicitly `unsupported`
or `partial`, and a numerical convergence result is not a proof of uniqueness
or completeness.

The generic dispatcher also recognizes a bounded one-dimensional wave problem
with explicit displacement and velocity profiles:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_tt = c^2*u_xx; u(x,0) = 1; u_t(x,0) = 0; u(0,t) = 1; u(1,t) = 1; x = {0,1}; t = {0,0.1}; spacePoints = 41; timeSteps = 100',
    ['c' => 1],
);
// solutions['method'] === 'automatic-bounded-wave-pde'
// solutions['automaticDomain'] === [0.0, 1.0, 0.0, 0.1]
```

This compact route selects the CFL-controlled wave solver and returns
displacement snapshots, the effective step size, and stability metadata. It
infers only two Dirichlet edges; use `analyzeNumericalWavePde()` for Neumann,
Robin, periodic, custom resolution, or nonlinear operator controls. Higher-
dimensional wave systems remain explicit-facade-only.

The generic dispatcher also recognizes a bounded two-dimensional wave problem
with four edge values and explicit displacement/velocity fields:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_tt = c^2*(u_xx + u_yy); u(x,y,0) = 1; u_t(x,y,0) = 0; u(0,y,t) = 1; u(1,y,t) = 1; u(x,0,t) = 1; u(x,1,t) = 1; x = {0,1}; y = {0,1}; t = {0,0.1}; firstPoints = 25; secondPoints = 25; timeSteps = 100',
    ['c' => 1],
);
// solutions['method'] === 'automatic-bounded-wave-2d-pde'
// solutions['automaticDomain'] === [0.0, 1.0, 0.0, 1.0, 0.0, 0.1]
```

This compact route selects the 2D CFL-controlled wave solver and returns
volumetric field snapshots plus grid and stability metadata. It infers four
Dirichlet edges only; use `analyzeNumericalWavePde2D()` for Neumann, Robin,
periodic, custom resolution, or nonlinear operator controls.

The generic dispatcher also recognizes a bounded three-dimensional wave problem
with six face values and explicit displacement/velocity fields:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_tt = c^2*(u_xx + u_yy + u_zz); u(x,y,z,0) = 1; u_t(x,y,z,0) = 0; u(0,y,z,t) = 1; u(1,y,z,t) = 1; u(x,0,z,t) = 1; u(x,1,z,t) = 1; u(x,y,0,t) = 1; u(x,y,1,t) = 1; x = {0,1}; y = {0,1}; z = {0,1}; t = {0,0.01}; firstPoints = 15; secondPoints = 15; thirdPoints = 15; timeSteps = 100',
    ['c' => 1],
);
// solutions['method'] === 'automatic-bounded-wave-3d-pde'
// solutions['automaticDomain'] === [0.0, 1.0, 0.0, 1.0, 0.0, 1.0, 0.0, 0.01]
```

This compact route selects the resource-capped 3D CFL-controlled solver and
returns volumetric snapshots plus grid and stability metadata. It infers six
Dirichlet faces only; use `analyzeNumericalWavePde3D()` for Neumann, Robin,
periodic, custom resolution, or nonlinear operator controls.

The generic dispatcher also recognizes a bounded coupled one-dimensional wave
system:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_tt = 0.1*u_xx + v; v_tt = 0.1*v_xx - u; u(x,0) = 0; v(x,0) = 0; u_t(x,0) = 0; v_t(x,0) = 0; u(0,t) = 0; u(1,t) = 0; v(0,t) = 0; v(1,t) = 0; x = {0,1}; t = {0,0.01}; spacePoints = 41; timeSteps = 100',
);
// solutions['method'] === 'automatic-bounded-coupled-wave-pde'
// solutions['automaticVariables'] === ['u', 'v']
```

This compact route selects the shared-grid coupled wave solver and returns
per-field snapshots, operator modes, and stability metadata. It infers paired
Dirichlet endpoints only; use `analyzeNumericalCoupledWavePde()` for mixed,
Robin, periodic, custom resolution, or higher-dimensional coupled systems.

The generic dispatcher also recognizes a bounded coupled two-dimensional wave
system with four faces per field:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_tt = 0.05*(u_xx + u_yy) + v; v_tt = 0.05*(v_xx + v_yy) - u; u(x,y,0) = 0; v(x,y,0) = 0; u_t(x,y,0) = 0; v_t(x,y,0) = 0; u(0,y,t) = 0; u(1,y,t) = 0; u(x,0,t) = 0; u(x,1,t) = 0; v(0,y,t) = 0; v(1,y,t) = 0; v(x,0,t) = 0; v(x,1,t) = 0; x = {0,1}; y = {0,1}; t = {0,0.01}; firstPoints = 25; secondPoints = 25; timeSteps = 100',
);
// solutions['method'] === 'automatic-bounded-coupled-wave-2d-pde'
// solutions['automaticVariables'] === ['u', 'v']
```

This compact route selects the shared-grid 2D coupled solver and returns
per-field volumetric snapshots, operator modes, and stability metadata. It
infers four Dirichlet faces per field only; use
`analyzeNumericalCoupledWavePde2D()` for mixed faces, Robin/periodic
conditions, custom resolution, or nonlinear operator controls.

The generic dispatcher also recognizes a bounded coupled three-dimensional wave
system with six faces per field:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'u_tt = 0.03*(u_xx + u_yy + u_zz) + v; v_tt = 0.03*(v_xx + v_yy + v_zz) - u; u(x,y,z,0) = 0; v(x,y,z,0) = 0; u_t(x,y,z,0) = 0; v_t(x,y,z,0) = 0; u(0,y,z,t) = 0; u(1,y,z,t) = 0; u(x,0,z,t) = 0; u(x,1,z,t) = 0; u(x,y,0,t) = 0; u(x,y,1,t) = 0; v(0,y,z,t) = 0; v(1,y,z,t) = 0; v(x,0,z,t) = 0; v(x,1,z,t) = 0; v(x,y,0,t) = 0; v(x,y,1,t) = 0; x = {0,1}; y = {0,1}; z = {0,1}; t = {0,0.01}; firstPoints = 15; secondPoints = 15; thirdPoints = 15; timeSteps = 100',
);
// solutions['method'] === 'automatic-bounded-coupled-wave-3d-pde'
// solutions['automaticVariables'] === ['u', 'v']
```

This compact route selects the shared-grid 3D coupled solver and returns
per-field volumetric snapshots, operator modes, and stability metadata. It
infers six Dirichlet faces per field only; use
`analyzeNumericalCoupledWavePde3D()` for mixed faces, Robin/periodic
conditions, custom resolution, or nonlinear operator controls.

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

## Coupled one-dimensional wave systems

`NumericalCoupledWavePdeAnalyzer` covers bounded systems of second-time-
derivative equations on one shared spatial grid:

```text
u_tt = c^2*u_xx + v
v_tt = c^2*v_xx - u
```

Provide displacement and velocity maps for every field, plus left/right edge
expressions. Values may couple all fields; spatial first, second, and pure
third-derivative aliases are evaluated on centered stencils. The centered
leapfrog update uses the supplied initial velocity on its first step, retains
synchronized per-field snapshots, and reports a conservative CFL guard:

```php
use MathPHP\Explaining\NumericalCoupledWavePdeAnalyzer;

$analysis = (new NumericalCoupledWavePdeAnalyzer())->analyze(
    'u_tt = 0.1*u_xx + v; v_tt = 0.1*v_xx - u',
    ['u', 'v'],
    ['u' => 'sin(pi*x)', 'v' => '0'],
    ['u' => '0', 'v' => '1'],
    ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'],
    spacePoints: 41,
    timeSteps: 100,
);
```

Use the optional edge-first `boundaryConditions` map for independent
Dirichlet, Neumann, Robin, or paired-periodic conditions. `solution['operatorModes']`
identifies affine versus directly evaluated nonlinear spatial operators, and
the visual payload is `pde-system-wave`. This is a bounded explicit numerical
approximation; higher-dimensional coupled waves, nonlocal boundaries, and
symbolic/global wave-system solutions remain outside this contract.

## Coupled two-dimensional wave systems

`NumericalCoupledWavePde2DAnalyzer` extends the coupled wave contract to a
bounded rectangle with four typed edges. Each equation has one second-time
derivative and can couple all field values and spatial operators:

```text
u_tt = c^2*(u_xx + u_yy) + v
v_tt = c^2*(v_xx + v_yy) - u
```

Provide displacement and velocity maps plus one expression for each field on
the left, right, bottom, and top edges:

```php
use MathPHP\Explaining\NumericalCoupledWavePde2DAnalyzer;

$analysis = (new NumericalCoupledWavePde2DAnalyzer())->analyze(
    'u_tt = 0.05*(u_xx + u_yy) + v; v_tt = 0.05*(v_xx + v_yy) - u',
    ['u', 'v'],
    ['u' => 'sin(pi*x)*sin(pi*y)', 'v' => '0'],
    ['u' => '0', 'v' => '1'],
    ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'],
    firstPoints: 25,
    secondPoints: 25,
    timeSteps: 100,
);
```

The solver retains synchronized two-dimensional field snapshots, applies a
centered leapfrog update with initial velocities, and exposes affine versus
directly evaluated nonlinear operator modes plus a conservative CFL guard.
Dirichlet, Neumann, Robin, and paired-periodic edges are supported through the
edge-first `boundaryConditions` map. The visual payload is
`pde-system-wave-2d`; nonlocal boundaries and symbolic/global solutions remain
outside this bounded explicit contract.

## Coupled three-dimensional wave systems

`NumericalCoupledWavePde3DAnalyzer` extends the same contract to several fields
on a shared rectangular box with six typed faces:

```text
u_tt = c^2*(u_xx + u_yy + u_zz) + v
v_tt = c^2*(v_xx + v_yy + v_zz) - u
```

Every field receives an initial displacement and velocity plus left/right,
bottom/top, and front/back expressions:

```php
use MathPHP\Explaining\NumericalCoupledWavePde3DAnalyzer;

$analysis = (new NumericalCoupledWavePde3DAnalyzer())->analyze(
    'u_tt = 0.03*(u_xx + u_yy + u_zz) + v; v_tt = 0.03*(v_xx + v_yy + v_zz) - u',
    ['u', 'v'],
    ['u' => 'sin(pi*x)*sin(pi*y)*sin(pi*z)', 'v' => '0'],
    ['u' => '0', 'v' => '1'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    firstPoints: 15,
    secondPoints: 15,
    thirdPoints: 15,
    timeSteps: 100,
);
```

The explicit centered update supports coupled first derivatives, pure second
derivatives, and centered mixed `u_xy`, `u_xz`, and `u_yz` operators. A shared
CFL guard chooses bounded substeps and records affine versus directly evaluated
nonlinear operator modes. Dirichlet, Neumann, Robin, and paired-periodic faces
are normalized per field; periodic faces must be supplied as matching pairs.
Synchronized 3D snapshots are retained with `complete: false`, and the visual
payload is `pde-system-wave-3d`. This remains a finite approximation for the
declared bounded system: unsupported syntax, singular evaluations, resource
caps, nonlocal boundaries, and symbolic/global solutions are reported as
`unsupported` or `partial` rather than treated as universal coverage.

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
above, the analyzer derives a Cauchy root bound, uses sampled bisection for
real roots, and also records Durand–Kerner convergence metadata for the full
complex spectrum; these roots remain numerical approximations rather than
symbolic proof objects.

For degree three and above, `solutions['complexRoots']` also contains
Durand–Kerner approximations with separate `real`, `imaginary`, and
`formatted` fields. These are numerical approximations, not proof objects;
inspect the convergence metadata before presenting them as final values.

Rational equalities are solved by cross-multiplying normalized polynomial
numerators and denominators. Linear and quadratic cases use direct roots;
higher-degree cases use the bounded polynomial root iteration and expose
`rootConverged` and `complete` metadata. The analyzer preserves the original
domain: denominator zeros are returned in `solutions['excludedValues']` and are
never reintroduced as roots after cancellation:

```php
$analysis = (new RationalEquationAnalyzer())->analyze('1 / x = 2');
// roots: [0.5], excludedValues: [0], complete: true

$cancelled = (new RationalEquationAnalyzer())->analyze('(x^2 - 1) / (x - 1) = 0');
// roots: [-1], excludedValues: [1]
```

`EquationAnalyzer::analyze()` dispatches these rational forms automatically.
Results whose higher-degree root iteration does not converge are returned as
`partial` rather than being presented as complete.

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
quadratic polynomials are certified with an exact sign chart over the supplied
domain. For degree three and above, the result is complete when the full
complex-root iteration converges and otherwise remains `partial`. The result
has `method: exact-polynomial-sign-chart`, `criticalRoots`, and a `complete`
flag. Rational-polynomial expressions use their dedicated exact sign-chart
route, retain denominator zeros as gaps, and expose `rootConverged` metadata
for higher-degree roots. Transcendental expressions use sampled intervals and
remain `partial` when undefined points or finite sampling prevent a proof.
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

For a polynomial or rational relation over all real values, use the global
facade instead of supplying finite bounds:

```php
$global = (new EquationAnalyzer())->analyzeGlobalInequality('x^2 >= 4', 'x');
// intervals: (-∞, -2] and [2, ∞); complete: true
```

Global mode rejects transcendental expressions rather than sampling an
unbounded domain.

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

The generic entry point accepts the same compact notation:

```php
$contact = (new EquationAnalyzer())->analyze(
    "x' = 1; 0 <= z ⟂ z + x >= 0; " .
    'x(0) = -1; z(0) = 1; t = {0,2}; steps = 80'
);
// solutions['method'] === 'automatic-numerical-complementarity'
```

Add an upper bound as `0 <= z <= 1 ⟂ g >= 0` for automatic mixed
complementarity dispatch. Use the explicit facade for custom tolerances,
mass matrices, or nonstandard active-set controls.

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

`NumericalNonsmoothNormalConeAnalyzer` covers bounded inclusions of the form
`0 ∈ F(z) + ∂φ(z) + N_K(z)` with centered finite-difference subgradients and
kink diagnostics. The generic entry point accepts:

```php
$normalCone = (new EquationAnalyzer())->analyze(
    'NC: F(x) = [x - 2]; potential = abs(x); x(0) = 0; ' .
    'x in [-1,1]; iterations = 100; stepSize = 0.5; tolerance = 1e-9'
);
// solutions['method'] === 'automatic-nonsmooth-normal-cone'
```

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

The generic entry point accepts a compact box VI:

```php
$vi = (new EquationAnalyzer())->analyze(
    'VI: F(x) = [x - 1]; x(0) = 0; x in [0,2]; ' .
    'iterations = 100; stepSize = 0.8; tolerance = 1e-9'
);
// solutions['method'] === 'automatic-box-variational-inequality'
```

Add `constraint = x <= 0.5` clauses for automatic generalized VI dispatch.

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

The generic dispatcher accepts a complete fixed-delay IVP with explicit
constant history:

```php
$analysis = (new EquationAnalyzer())->analyze(
    "y' = -y(t - 0.5); y(t) = 1; y(0) = 1",
);
// solutions['method'] === 'automatic-retarded-delay-ivp'
// solutions['automaticDelay'] === 0.5
```

The parser requires one positive numeric lag, a constant `y(t)` history, and a
numeric `y(t₀)` initial value. It rewrites `y(t)` to the current state and
`y(t - τ)` to the delayed state before running 128 bounded method-of-steps
Euler intervals over `[t₀, t₀ + 1]`. Use `analyzeNumericalDelayOde()` for
non-constant histories, custom domains, or resolution control.

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

The generic dispatcher accepts a complete five-clause form:

```php
$analysis = (new EquationAnalyzer())->analyze(
    "y' = yd; y(t) = 1; y(0) = 1; delay = 0.5; kernel(s) = 1",
);
// solutions['method'] === 'automatic-distributed-delay-ivp'
// solutions['automaticKernel'] === '1'
```

It requires a positive numeric delay, constant history, numeric initial state,
and one explicit lag-kernel expression. The automatic route uses 128
method-of-steps Euler intervals and 32 composite-trapezoid lag samples over
`[t₀, t₀ + 1]`; use `analyzeNumericalDistributedDelayOde()` for custom domains,
history, kernels, or resolution.

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

The generic dispatcher accepts a complete neutral-delay IVP:

```php
$analysis = (new EquationAnalyzer())->analyze(
    "y' = yd + 0.5*ydd; y(t) = 1; y'(t) = 0; y(0) = 1; delay = 0.5",
);
// solutions['method'] === 'automatic-neutral-delay-ivp'
// solutions['automaticDelay'] === 0.5
```

The input requires constant state and derivative histories, one numeric initial
state, and a positive fixed delay. The automatic route uses 128 bounded
method-of-steps Euler intervals over `[t₀, t₀ + 1]`, retaining delayed state
and slope samples; use `analyzeNumericalNeutralDelayOde()` for explicit control.

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

The generic dispatcher accepts a complete bounded-lag IVP:

```php
$analysis = (new EquationAnalyzer())->analyze(
    "y' = yd; y(t) = 1; y(0) = 1; delay(t,y) = 0.25 + 0.05*y; maximumDelay = 1",
);
// solutions['method'] === 'automatic-state-dependent-delay-ivp'
// solutions['automaticMaximumDelay'] === 1.0
```

The input requires a positive lag expression, a numeric maximum-delay bound,
constant history, and one numeric initial state. The automatic route uses 128
method-of-steps Euler intervals over `[t₀, t₀ + 1]`; invalid or out-of-bound
lags remain `unsupported` or `partial` rather than being silently clipped.
Use `analyzeNumericalStateDependentDelayOde()` for explicit control.

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

The generic dispatcher accepts a complete compact scalar IVP as well:

```php
$analysis = (new EquationAnalyzer())->analyze('D^0.5 y = 1; y(0) = 0');
// solutions['method'] === 'automatic-caputo-fractional-ivp'
// solutions['automaticOrder'] === 0.5
```

`D^α y` and `Caputo^α y` forms require a numeric order strictly between zero
and one plus one numeric initial condition. The automatic route uses the finite
interval `[t₀, t₀ + 1]` and 128 memory steps, preserving
`complete: false`; use `analyzeNumericalFractionalOde()` for explicit domain,
resolution, or known-parameter control.

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
Orders may stay in `0 < α < 1` for diffusion memory or `1 < α ≤ 2` for wave
memory. Wave mode adds the initial velocity:

```php
use MathPHP\Explaining\EquationAnalyzer;

$wave = (new EquationAnalyzer())->analyzeNumericalVariableOrderFractionalWaveOde(
    '0',
    '1.5 + 0.1*y',
    0,
    2,                    // initial velocity
    targetIndependent: 1,
    steps: 200,
);
```

The order must not cross `α = 1` during a run; such a trajectory is reported
`partial`. Symbolic fractional solutions remain unsupported.

The generic `EquationAnalyzer` entry point recognizes the same variable-order
syntax without requiring the explicit facade. Parenthesized order expressions
may depend on the independent coordinate and latest state, with optional
finite domains and step counts:

```php
use MathPHP\Explaining\EquationAnalyzer;

$diffusion = (new EquationAnalyzer())->analyze(
    'D^(0.5 + 0.1*t) y = 1; y(0) = 0; t = {0,0.2}; steps = 32'
);
// solutions['method'] === 'automatic-variable-order-fractional-ivp'

$wave = (new EquationAnalyzer())->analyze(
    "D^(1.5 + 0.05*t) y = 0; y(0) = 0; y'(0) = 1; t = {0,0.2}; steps = 32"
);
// solutions['method'] === 'automatic-variable-order-fractional-wave-ivp'
```

The compact route returns a bounded numerical approximation with order
history; it does not claim a symbolic solution or global stability. Use the
explicit variable-order facades for custom options and higher-resolution
studies.

Coupled systems can assign independent orders with `order[x] = ...` and
`order[y] = ...`. Components with `1 < α ≤ 2` also require a matching numeric
velocity clause; the result records those components in `waveComponents`:

```php
$mixed = (new EquationAnalyzer())->analyze(
    "D^alpha x = -x; D^alpha y = x; x(0) = 1; y(0) = 0; " .
    "order[x] = 0.5; order[y] = 1.5; x'(0) = 0; y'(0) = 1; " .
    't = {0,0.2}; steps = 32'
);
// solutions['method'] === 'automatic-caputo-fractional-mixed-wave-system'
// solutions['waveComponents'] === ['y']
```

Orders may be numeric or Core expressions such as `0.5 + 0.1*t`. This is a
bounded numerical memory approximation; use `analyzeNumericalFractionalOdeSystem()`
for custom order maps, evaluation options, and resolution.

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

The generic dispatcher supports a shared-order compact system as well:

```php
$analysis = (new EquationAnalyzer())->analyze(
    'D^alpha x = -x; D^alpha y = x; x(0) = 1; y(0) = 0; order = 0.5',
);
// solutions['method'] === 'automatic-caputo-fractional-system'
// solutions['automaticOrder'] === 0.5
```

The automatic diffusion form requires one shared numeric order strictly between
zero and one and one numeric initial value per component. It uses 128 bounded
memory steps over `[t₀, t₀ + 1]`. Shared-order wave systems use the same
dispatcher with `1 < α ≤ 2` and one initial velocity per component:

```php
$wave = (new EquationAnalyzer())->analyze(
    "D^alpha x = -x; D^alpha y = x; x(0) = 1; y(0) = 0; x'(0) = 0; y'(0) = 1; order = 1.5",
);
// solutions['method'] === 'automatic-caputo-fractional-wave-system'
// solutions['automaticInitialVelocity'] === ['x' => 0.0, 'y' => 1.0]
```

Wave and diffusion dispatch both use 128 bounded memory steps over `[t₀,
t₀ + 1]`; use `analyzeNumericalFractionalOdeSystem()` for mixed orders,
custom coordinates, or resolution control.

```php
$mixed = (new NumericalFractionalOdeSystemAnalyzer())->analyze(
    "x' = 1; y' = 2",
    ['x', 'y'],
    ['x' => 0.5, 'y' => '0.6 + 0.1*t'],
    initial: ['x' => 0, 'y' => 0],
);
```

Each component retains its own order history and memory weights. The bounded
explicit approximation supports either diffusion memory `0 < α_i < 1` or wave
memory `1 < α_i ≤ 2`. Wave components require a finite initial-velocity map:

```php
use MathPHP\Explaining\EquationAnalyzer;

$waveSystem = (new EquationAnalyzer())->analyzeNumericalFractionalWaveOdeSystem(
    "x' = y; y' = -x",
    ['x', 'y'],
    1.5,
    ['x' => 0, 'y' => 1],
    initial: ['x' => 1, 'y' => 0],
    targetIndependent: 1,
    steps: 200,
);
```

Mixed component orders are allowed as long as an order does not cross the
first-order boundary during integration. The result identifies wave components
and retains their velocity contribution; symbolic fractional solutions remain
unsupported.

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

The generic dispatcher accepts the canonical one-dimensional compact form as
well:

```php
$field = (new EquationAnalyzer())->analyze(
    'D^0.5_t u = 0.1*u_xx; u(x,0) = 0; u(0,t) = 0; u(1,t) = 0; ' .
    'x = {0,1}; t = {0,0.2}; spacePoints = 32; timeSteps = 16'
);
// solutions['method'] === 'automatic-caputo-fractional-pde'
```

Orders `1 < α ≤ 2` use the wave solver and require an initial velocity clause
such as `u_t(x,0) = 1`, returning
`automatic-caputo-fractional-wave-pde`. The compact route assumes Dirichlet
endpoints and a canonical `κ*u_xx` operator; use the explicit facade for mixed
or periodic boundaries, nonlocal spatial order, or custom evaluation options.

Variable-order fields use a parenthesized order expression:

```php
$variable = (new EquationAnalyzer())->analyze(
    'D^(0.5 + 0.1*t)_t u = 0.1*u_xx; u(x,0) = 0; ' .
    'u(0,t) = 0; u(1,t) = 0; x = {0,1}; t = {0,0.2}; ' .
    'spacePoints = 32; timeSteps = 16'
);
// solutions['method'] === 'automatic-variable-order-fractional-pde'
```

The order is evaluated at each grid point and time step. Orders may remain in
diffusion mode (`0 < α < 1`) or wave mode (`1 < α ≤ 2`), but a run that crosses
`α = 1` is reported partial. The result retains order history and temporal
mode; use `analyzeNumericalVariableOrderFractionalPde()` for mixed boundaries,
nonlocal operators, or custom evaluation options.

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

The generic dispatcher also recognizes the compact rectangular form with a
constant order, a canonical five-point Laplacian, four Dirichlet edges, and
independent grid controls:

```php
$field2d = (new EquationAnalyzer())->analyze(
    'D^0.5_t u = 0.1*(u_xx + u_yy); u(x,y,0) = 0; ' .
    'u(0,y,t) = 0; u(1,y,t) = 0; u(x,0,t) = 0; u(x,1,t) = 0; ' .
    'x = {0,1}; y = {0,1}; t = {0,0.2}; firstPoints = 25; secondPoints = 25'
);
// solutions['method'] === 'automatic-caputo-fractional-2d-pde'
```

For `1 < α ≤ 2`, add `u_t(x,y,0) = velocity` to select
`automatic-caputo-fractional-wave-2d-pde`. The compact route is deliberately
limited to rectangular Dirichlet edges; use the explicit analyzer below for
mixed or periodic boundaries, nonlocal spatial operators, variable order, or
custom evaluation options.

Variable-order 2D fields use a parenthesized order expression:

```php
$variable2d = (new EquationAnalyzer())->analyze(
    'D^(0.5 + 0.1*t)_t u = 0.1*(u_xx + u_yy); u(x,y,0) = 0; ' .
    'u(0,y,t) = 0; u(1,y,t) = 0; u(x,0,t) = 0; u(x,1,t) = 0; ' .
    'x = {0,1}; y = {0,1}; t = {0,0.2}; firstPoints = 25; secondPoints = 25'
);
// solutions['method'] === 'automatic-variable-order-fractional-2d-pde'
```

Add `u_t(x,y,0) = velocity` when the order expression enters wave memory
(`1 < α ≤ 2`); the method becomes
`automatic-variable-order-fractional-wave-2d-pde`.

The same compact dispatcher supports bounded three-dimensional fields with a
canonical Laplacian, six Dirichlet faces, and `thirdPoints`:

```php
$volume = (new EquationAnalyzer())->analyze(
    'D^0.5_t u = 0.1*(u_xx + u_yy + u_zz); u(x,y,z,0) = 0; ' .
    'u(0,y,z,t) = 0; u(1,y,z,t) = 0; u(x,0,z,t) = 0; u(x,1,z,t) = 0; ' .
    'u(x,y,0,t) = 0; u(x,y,1,t) = 0; x = {0,1}; y = {0,1}; z = {0,1}; ' .
    't = {0,0.2}; firstPoints = 15; secondPoints = 15; thirdPoints = 15'
);
// solutions['method'] === 'automatic-caputo-fractional-3d-pde'
```

For `1 < α ≤ 2`, add `u_t(x,y,z,0) = velocity` to select
`automatic-caputo-fractional-wave-3d-pde`. The compact route is intentionally
limited to rectangular Dirichlet faces and canonical Laplacians; use the
explicit 3D analyzer for mixed or periodic faces, nonlocal operators, or
custom evaluation options.

Variable-order 3D fields use the same parenthesized order form and six face
clauses:

```php
$variableVolume = (new EquationAnalyzer())->analyze(
    'D^(0.5 + 0.1*t)_t u = 0.1*(u_xx + u_yy + u_zz); u(x,y,z,0) = 0; ' .
    'u(0,y,z,t) = 0; u(1,y,z,t) = 0; u(x,0,z,t) = 0; u(x,1,z,t) = 0; ' .
    'u(x,y,0,t) = 0; u(x,y,1,t) = 0; x = {0,1}; y = {0,1}; z = {0,1}; ' .
    't = {0,0.2}; firstPoints = 15; secondPoints = 15; thirdPoints = 15'
);
// solutions['method'] === 'automatic-variable-order-fractional-3d-pde'
```

Add `u_t(x,y,z,0) = velocity` for variable-order wave memory. Mixed or
periodic faces and nonlocal operators remain available through the explicit
3D analyzers.

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
five-point Laplacian. Fractional wave systems, three-dimensional nonlocal
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
forcing history. Diffusion mode keeps every evaluated order strictly within
`0 < α < 1`; wave mode is documented below for `1 < α ≤ 2`. The implementation
is an explicit bounded approximation, not a symbolic fractional PDE solver. For constant-order nonlocal spatial kernels,
use the `spatialOrder` option on the fractional 1D, 2D, or 3D analyzers.

The variable-order 1D analyzer also accepts `spatialOrder` between `0` and `2`,
so the temporal order field and bounded symmetric nonlocal kernel can be used
together. `spatialOrder: 2.0` preserves the local centered derivative; the
result records `operatorMode` and the variable-order history together.

For variable-order waves, use `1 < α(x,t,u) ≤ 2` and provide an initial
velocity expression:

```php
use MathPHP\Explaining\NumericalVariableOrderFractionalWavePdeAnalyzer;

$wave = (new NumericalVariableOrderFractionalWavePdeAnalyzer())->analyze(
    '0',
    '1.5 + 0.05*t',
    0.1,
    'x*(1-x)',
    '1',
    '0', '0',
    spacePoints: 41,
    timeSteps: 100,
);
```

Wave nodes retain the velocity contribution and are identified by
`temporalMode: variable-wave-memory`; the order must not cross `α = 1` during
integration. Local and bounded nonlocal spatial operators use the same
`spatialOrder` range. Dedicated 2D and 3D variable-order wave facades are
documented below.

The solver retains every spatial forcing field used by the Caputo memory
quadrature, reports the fractional diffusion stability number, and marks runs
that exceed its explicit guard as `partial`. Fractional wave systems,
three-dimensional nonlocal kernels, unbounded or singular spatial kernels, dimensions beyond the bounded 3D
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
and `pde-heatmap` snapshots. Coupled fields use the dedicated system analyzer
below.

## Two-dimensional Caputo fractional waves

`NumericalFractionalWavePde2DAnalyzer` extends the Caputo wave contract to a
bounded rectangle with four typed edges:
`D_t^α u = c²(u_xx + u_yy) + s(x,y,t,u)`, `1 < α ≤ 2`.

```php
use MathPHP\Explaining\NumericalFractionalWavePde2DAnalyzer;

$field = (new NumericalFractionalWavePde2DAnalyzer())->analyze(
    '0',
    1.5,                 // Caputo wave order
    0.1,                 // wave speed c
    'x*(1-x)*y*(1-y)',   // initial displacement
    '0',                 // initial velocity
    '0', '0', '0', '0',  // left/right/bottom/top
    firstPoints: 25,
    secondPoints: 25,
    timeSteps: 100,
);
```

The result retains the initial-velocity contribution, every two-dimensional
forcing field, and `pde-heatmap-2d` snapshots. Dirichlet, Neumann, Robin, and
paired-periodic edges are supported. Set `spatialOrder` below `2` for the
bounded symmetric nonlocal kernel; `spatialOrder: 2.0` keeps the local five-point
Laplacian.

## Coupled one-dimensional Caputo fractional waves

`NumericalCoupledFractionalWavePdeAnalyzer` supports several fields on one
shared bounded grid. Each equation may reference every field and its centered
spatial derivatives, while each component can use a different constant order:

```text
D_t^α u = 0.02*u_xx + v
D_t^β v = 0.02*v_xx - u
```

```php
use MathPHP\Explaining\NumericalCoupledFractionalWavePdeAnalyzer;

$system = (new NumericalCoupledFractionalWavePdeAnalyzer())->analyze(
    'D_t^alpha u = 0.02*u_xx + v; D_t^alpha v = 0.02*v_xx - u',
    ['u', 'v'],
    1.6,
    ['u' => 'sin(pi*x)', 'v' => '0'],
    ['u' => '0', 'v' => '1'],
    ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'],
    spacePoints: 41,
    timeSteps: 100,
);
```

Pass an order map such as `['u' => 0.7, 'v' => 1.8]` for mixed diffusion and
wave memory. Components with `1 < α ≤ 2` require an initial velocity; all
forcing histories, temporal modes, operator modes, and synchronized snapshots
are retained. Typed Dirichlet, Neumann, Robin, and paired-periodic edges plus
bounded nonlocal spatial order are supported. The visual payload is
`pde-system-fractional-wave`.

## Coupled two-dimensional Caputo fractional waves

`NumericalCoupledFractionalWavePde2DAnalyzer` extends the coupled memory
contract to rectangular fields with four typed edges. Equations may couple all
fields and use centered `x`, `y`, and mixed `xy` spatial operators:

```text
D_t^α u = 0.02*(u_xx + u_yy) + v
D_t^α v = 0.02*(v_xx + v_yy) - u
```

```php
use MathPHP\Explaining\NumericalCoupledFractionalWavePde2DAnalyzer;

$system = (new NumericalCoupledFractionalWavePde2DAnalyzer())->analyze(
    'D_t^alpha u = 0.02*(u_xx + u_yy) + v; D_t^alpha v = 0.02*(v_xx + v_yy) - u',
    ['u', 'v'],
    1.6,
    ['u' => 'sin(pi*x)*sin(pi*y)', 'v' => '0'],
    ['u' => '0', 'v' => '1'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    firstPoints: 25,
    secondPoints: 25,
    timeSteps: 100,
);
```

Use an order map for mixed diffusion and wave memory, and set `spatialOrder`
below `2` for the bounded symmetric nonlocal operator. The result retains
per-field forcing histories, temporal/operator modes, synchronized snapshots,
and a `pde-system-fractional-wave-2d` visual. Symbolic solutions, unbounded
domains, and arbitrary higher-dimensional/general-order systems remain outside
this focused contract.

## Coupled three-dimensional Caputo fractional waves

`NumericalCoupledFractionalWavePde3DAnalyzer` extends the coupled memory
contract to a bounded box with six typed faces. Each field may use a diffusion
order (`0 < α < 1`) or wave order (`1 < α ≤ 2`), and equations may couple
fields through `x`, `y`, `z`, mixed `xy`/`xz`/`yz`, or nonlocal spatial
operators:

```text
D_t^α u = 0.01*(u_xx + u_yy + u_zz) + v
D_t^α v = 0.01*(v_xx + v_yy + v_zz) - u
```

```php
use MathPHP\Explaining\NumericalCoupledFractionalWavePde3DAnalyzer;

$volume = (new NumericalCoupledFractionalWavePde3DAnalyzer())->analyze(
    'D_t^alpha u = 0.01*(u_xx + u_yy + u_zz) + v; D_t^alpha v = 0.01*(v_xx + v_yy + v_zz) - u',
    ['u', 'v'],
    ['u' => 0.7, 'v' => 1.8],
    ['u' => 'sin(pi*x)*sin(pi*y)*sin(pi*z)', 'v' => '0'],
    ['v' => '0'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    ['u' => '0', 'v' => '0'], ['u' => '0', 'v' => '0'],
    firstPoints: 15,
    secondPoints: 15,
    thirdPoints: 15,
    timeSteps: 100,
);
```

The same API is available through `EquationAnalyzer`. Six faces support
Dirichlet, Neumann, Robin, and paired-periodic conditions; `spatialOrder < 2`
selects a bounded symmetric nonlocal volume operator. Results retain per-field
Caputo forcing histories, mixed temporal/operator modes, and synchronized
snapshots with a `pde-system-fractional-wave-3d` visual payload.

## Three-dimensional Caputo fractional waves

`NumericalFractionalWavePde3DAnalyzer` extends the same Caputo wave memory rule
to a bounded rectangular box with six typed faces:
`D_t^α u = c²(u_xx + u_yy + u_zz) + s(x,y,z,t,u)`, `1 < α ≤ 2`.

```php
use MathPHP\Explaining\NumericalFractionalWavePde3DAnalyzer;

$volume = (new NumericalFractionalWavePde3DAnalyzer())->analyze(
    '0',
    1.5,                         // Caputo wave order
    0.1,                         // wave speed c
    'x*(1-x)*y*(1-y)*z*(1-z)',   // initial displacement
    '0',                         // initial velocity
    '0', '0', '0', '0', '0', '0',
    firstPoints: 9,
    secondPoints: 9,
    thirdPoints: 9,
    timeSteps: 20,
);
```

The six faces accept Dirichlet, Neumann, Robin, or paired-periodic conditions.
The result retains initial-velocity and volume-forcing histories and exposes
`pde-heatmap-3d` snapshots. Set `spatialOrder` below `2` for the bounded
symmetric nonlocal volume kernel; `spatialOrder: 2.0` keeps the local seven-point
Laplacian. Fractional wave systems and symbolic fractional solutions remain
outside this focused contract.

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
Both order and operator histories remain in the solution metadata. For wave
memory with `1 < α ≤ 2`, use
`NumericalVariableOrderFractionalWavePde2DAnalyzer` with an initial velocity:

```php
$wave = (new NumericalVariableOrderFractionalWavePde2DAnalyzer())->analyze(
    '0', '1.5 + 0.05*t', 0.1,
    'x*(1-x)*y*(1-y)', '1', '0', '0', '0', '0',
    firstPoints: 17, secondPoints: 17, timeSteps: 30,
);
```

The order mode is retained in `temporalMode` and cannot cross `α = 1` during
the run. Symbolic fractional solutions remain outside this focused contract.

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
diffusion order must remain strictly within `0 < α < 1`. Pass `spatialOrder`
between `0` and `2` to combine the variable temporal order with a bounded
symmetric nonlocal volume kernel; `spatialOrder: 2.0` preserves the local
seven-point operator. For wave memory with `1 < α ≤ 2`, use
`NumericalVariableOrderFractionalWavePde3DAnalyzer` and provide an initial
velocity field. The solution records `temporalMode`, the combined operator
mode, and both histories, while nonlocal runs use a resource-aware grid cap.
This remains an explicit bounded approximation: unbounded or singular kernels
and symbolic fractional solutions are outside the contract.

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

For constant-coefficient linear equations of order three through 32, use the
bounded symbolic higher-order analyzer:

```php
use MathPHP\Explaining\HigherOrderOdeAnalyzer;

$analysis = (new HigherOrderOdeAnalyzer())->analyze(
    "y''' - 6*y'' + 11*y' - 6*y = 0",
);
// solution['basis'] contains independent exponential terms.
// solution['rootConverged'] and solution['complete'] describe confidence.
```

The generic `EquationAnalyzer` dispatches the same notation with method
`automatic-higher-order-ode`. Characteristic roots are numerical for these
higher degrees; conjugate pairs are rendered as real sine/cosine terms. A
non-resonant constant right-hand forcing term is supported through a constant
particular solution. Variable-coefficient, nonlinear, singular, or resonant
forcing equations are deliberately returned as `partial`/`unsupported`, and
initial-value problems should use `analyzeNumericalHigherOrderOde()` when a
bounded trajectory is wanted.
