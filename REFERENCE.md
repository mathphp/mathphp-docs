# MathPHP

MathPHP is a small, dependency-free PHP library for safely evaluating scalar
mathematical expressions. It parses an explicit language into an immutable AST
and evaluates that AST; it never uses PHP `eval` or resolves expression names
to arbitrary PHP callables.

The v0.3 implementation targets PHP `^8.2` and is suitable for untrusted
expression text when its resource limits are kept at appropriate values.

> **Package identity:** this project intentionally retains the `MathPHP` name,
> `mathphp/mathphp` Composer coordinate, and `MathPHP\` namespace despite their
> confirmed brand and root-namespace collision with the established
> `markrogoyski/math-php` package. This is an explicit, documented decision and
> does not imply affiliation. See
> [the identity record](https://github.com/mathphp/mathphp-specs/blob/main/IDENTITY-GATE.md).

## Installation

Core `v0.3.0` is tagged. Install the stable `0.3` line from your configured
Composer source:

```console
composer require mathphp/mathphp:^0.3
```

For development in this checkout:

```console
composer install
composer quality
```

To use a local checkout while developing, declare it as a path repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../MathPHP"
        }
    ],
    "require": {
        "mathphp/mathphp": "@dev"
    }
}
```

The public Core release is tagged independently from the optional private
packages. Use a tag or commit for production; use `@dev` only for local
development.

## Quick start

```php
use MathPHP\Math;

Math::evaluate('2 + 3 * 4'); // 14
Math::evaluate('2^3^2'); // 512
Math::evaluate('-2^2'); // -4
Math::evaluate('sqrt(81) + 3!'); // 15.0

Math::evaluate(
    'gross * (1 - discount)',
    ['gross' => 125, 'discount' => 0.2],
); // 100.0
```

`Math::evaluate()` returns only a finite PHP `int` or `float`. Integer syntax
and exact integer arithmetic remain integers; decimal/scientific syntax and
floating-point operations remain floats. Division always returns a float.

## Website

The repository also includes a small companion site with an overview, reference
docs, and an interactive evaluator backed by this same package:

```console
php -S 127.0.0.1:8080 -t public
```

Open <http://127.0.0.1:8080/> after starting the local server.

## Optional private extensions

The core evaluator does not require teaching, presentation, or measurement
features. Three optional private packages extend it without changing the
public evaluator:

- `mathphp/mathphp-explaining` teaches the evaluation order with detailed,
  translatable steps and partial-result explanations.
- `mathphp/mathphp-visuals` turns formulas, plots, graphs, charts, and analysis
  data into renderer-neutral models with accessible SVG and image-ready data
  URIs.
- `mathphp/mathphp-units` adds quantities, dimensional arithmetic, compatible
  conversions, and stable unit errors while keeping Core scalar-only.

The explaining package also understands quantities when Units is installed.
Visuals can label quantities and axes, but does not infer or validate units.
Install only the add-ons your application needs.

## Supported public API

The supported v0.3 API consists of:

- `MathPHP\Math`;
- `MathPHP\Configuration\EvaluationOptions` and `ResourceLimits`;
- `MathPHP\Function\FunctionDefinition` and `FunctionRegistry`;
- `MathPHP\Exception\MathException` and its three phase subclasses; and
- `MathPHP\Source\SourceSpan`, returned by expression exceptions.

Classes and enums under `MathPHP\Ast`, `MathPHP\Parser`, and
`MathPHP\Evaluator` are implementation details rather than extension points.
The immutable options and registry are the supported way to customize an
evaluation.

The optional `Math::evaluateWithObserver()` method is the public instrumentation
seam for separately distributed tooling. Its observer may be passed directly as
the second argument when variables are not needed; the array-first form remains
available for variable-bound expressions. The step-by-step explanation layer is
maintained as the private `mathphp/mathphp-explaining` package and is not part
of this free package or its release archive.

## Language

The numeric, security, and error invariants build on the
[normative v0.1 contract](https://github.com/mathphp/mathphp-specs/blob/main/V0.1-CONTRACT.md);
the v0.3 grammar additions are documented here. The supported surface is:

- integers, decimals, and scientific notation;
- ASCII variable names and the reserved constants `pi`, `e`, `tau`, and `phi`,
  including Unicode aliases `π`, `τ`, and `φ`;
- grouping with parentheses;
- binary `+`, `-`, `*`, `/`, `%`, and `^`;
- Unicode aliases `×`, `÷`, and `−` for multiplication, division, and
  subtraction;
- superscript single-digit powers and the square-root prefix `√`;
- unary `+` and `-`;
- one postfix factorial per postfix expression; and
- calls to explicitly registered functions.

Exponentiation is right-associative and binds more tightly than a unary sign:

| Expression | Result |
|---|---:|
| `2^3^2` | `512` |
| `-2^2` | `-4` |
| `2^-2` | `0.25` |
| `(-2)^2` | `4` |
| `-3!` | `-6` |

The default function allowlist is:

| Function | Arity | Domain/result |
|---|---:|---|
| `abs(x)` | 1 | finite; preserves integer/float type |
| `sqrt(x)` | 1 | `x >= 0`; float |
| `cbrt(x)` | 1 | finite real cube root; float |
| `sin(x)`, `cos(x)` | 1 | float |
| `tan(x)` | 1 | finite where cosine is non-zero; float |
| `sec(x)` | 1 | finite where cosine is non-zero; float |
| `csc(x)` | 1 | finite where sine is non-zero; float |
| `cot(x)` | 1 | finite where sine is non-zero; float |
| `asin(x)`, `acos(x)` | 1 | `-1 <= x <= 1`; float |
| `atan(x)` | 1 | float |
| `sinh(x)`, `cosh(x)`, `tanh(x)` | 1 | float |
| `asinh(x)` | 1 | float |
| `acosh(x)` | 1 | `x >= 1`; float |
| `atanh(x)` | 1 | `-1 < x < 1`; float |
| `exp(x)` | 1 | finite result; float |
| `ln(x)` | 1 | `x > 0`; float |
| `log(x, base)` | 2 | `x > 0`, `base > 0`, `base != 1`; float |
| `log10(x)` | 1 | `x > 0`; float |
| `hypot(x, y)` | 2 | finite Euclidean norm; float |
| `sign(x)` | 1 | `-1`, `0`, or `1`; int |
| `min(...)`, `max(...)` | 1–16 | finite aggregate; preserves numeric type |
| `floor(x)`, `ceil(x)`, `round(x)` | 1 | float |

Names are case-sensitive. Function names are never looked up in PHP's global
function table.

## Variables and constants

Variables are a per-call map of ASCII identifier names to finite `int|float`
values:

```php
use MathPHP\Math;

$area = Math::evaluate('pi * radius^2', ['radius' => 3]);
// $area is approximately 28.274333882308138
```

`pi`, `e`, `tau`, and `phi` are reserved and cannot be overridden. Numeric strings, booleans,
NaN, and Infinity are rejected rather than coerced.

## Resource limits

Every evaluation uses immutable limits. The defaults are:

| Safeguard | Default |
|---|---:|
| expression length | 4,096 bytes |
| non-EOF tokens | 1,024 |
| open parentheses/calls | 64 |
| AST depth | 128 |
| arguments per call | 16 |
| factorial operand | 20 on 64-bit PHP; 12 on 32-bit PHP |
| absolute exponent | 1,024 |

Use named arguments to lower or deliberately raise a limit:

```php
use MathPHP\Configuration\EvaluationOptions;
use MathPHP\Configuration\ResourceLimits;
use MathPHP\Math;

$limits = new ResourceLimits(
    maxExpressionLength: 256,
    maxExponentMagnitude: 8,
);
$options = new EvaluationOptions(limits: $limits);

Math::evaluate('2^8', options: $options); // 256
```

Limit failures are source-aware exceptions with stable `limit.*` error codes.
Host applications should choose tighter limits when their input policy permits.

## Custom functions

Custom functions are explicit immutable definitions. Their closures are trusted
host code and must be pure, side-effect-free, and return a finite
`int|float`.

```php
use MathPHP\Configuration\EvaluationOptions;
use MathPHP\Function\FunctionDefinition;
use MathPHP\Function\FunctionRegistry;
use MathPHP\Math;

$triple = new FunctionDefinition(
    'triple',
    1,
    1,
    static fn (array $arguments): int|float => $arguments[0] * 3,
);

$functions = FunctionRegistry::defaults()->with($triple);
$options = new EvaluationOptions(functions: $functions);

Math::evaluate('triple(7)', options: $options); // 21
```

Built-ins and the constant names `pi` and `e` cannot be overridden. Registries,
options, and evaluation environments are isolated; a function or variable
provided to one call does not leak into another.

## Errors

All expression failures extend `MathPHP\Exception\MathException` and belong to
one phase:

- `LexicalException` for malformed numbers, unknown bytes, and lexer limits;
- `ParseException` for invalid syntax and parser limits; or
- `EvaluationException` for unknown names, domain errors, numeric failures, and
  evaluation limits.

Each exception exposes a stable machine-readable code and a zero-based,
end-exclusive byte span:

```php
use MathPHP\Exception\MathException;
use MathPHP\Math;

try {
    Math::evaluate('10 / 0');
} catch (MathException $error) {
    $error->errorCode(); // "eval.division_by_zero"
    $error->span()->start; // 3
    $error->span()->end; // 4
}
```

Invalid host API inputs, such as a non-numeric variable or invalid limit, throw
`InvalidArgumentException` because they have no expression source location.
The exhaustive code and span contract is in
[section 7 of the v0.1 contract](https://github.com/mathphp/mathphp-specs/blob/main/V0.1-CONTRACT.md#7-errors-and-source-spans).

## Explicit limitations

The Core `v0.3` language intentionally does not support:

- assignment, comparisons, or logical operators;
- symbolic algebra, simplification, or derivatives;
- units, currency, matrices, complex numbers, or formatting;
- arbitrary precision; or
- arbitrary PHP functions.

Inputs such as `3!!`, `1 < 2`, and `sqrt 4` are rejected. Standard implicit
multiplication is accepted for `2pi`, `2(x + 1)`, and `(x + 1)(x - 1)`;
identifiers remain atomic, so `xy` is a single variable rather than `x * y`.
Integer overflow is an error rather than a silent promotion to a lossy float.

## Compatibility and verification

The supported Composer range is PHP `^8.2` (`>=8.2.0,<9.0.0`) with no runtime
dependencies. The full suite is verified locally on every installed supported
runtime:

| PHP family | Local runtime | Status |
|---|---:|---|
| 8.2 | 8.2.30 | verified |
| 8.3 | unavailable locally | remote CI required before release |
| 8.4 | 8.4.16 | verified |
| 8.5 | 8.5.5 | verified |

Run the local CI-equivalent aggregate with:

```console
composer quality
composer audit --locked
```

It covers strict manifest validation, optimized strict PSR-4 generation, syntax
linting, PHPUnit, PHPStan at maximum level against PHP 8.2, and PSR-12.
The requirement-to-source/test evidence and exact local verification record are
in [the v0.1 traceability audit](https://github.com/mathphp/mathphp-specs/blob/main/V0.1-TRACEABILITY.md).

## Roadmap

- `0.1.0`: the secure scalar evaluator defined by the v0.1 contract.
- `0.2.0`: only features accepted through a separate contract; no deferred
  feature is implicitly promised.
- `1.0.0`: a stable API after real-world use and compatibility review.

The project follows [Semantic Versioning](https://semver.org/) for planned
releases and records notable changes in [CHANGELOG.md](CHANGELOG.md).

## License

[MIT license for the public core](https://github.com/mathphp/mathphp/blob/main/LICENSE)
