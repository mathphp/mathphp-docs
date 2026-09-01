# Functions and variables

Variables are passed per evaluation. Built-ins are allowlisted and validated
for arity and domain before they execute.

```php
Math::evaluate('sqrt(area) + log(minimum, 10)', [
    'area' => 81,
    'minimum' => 100,
]); // 11.0
```

The default catalogue includes exactly `abs`, `sqrt`, `sin`, `cos`, `exp`,
`ln`, `log`, `floor`, `ceil`, and `round`. `log(value, base)` takes exactly
two arguments; the other built-ins take one. Names are case-sensitive and
never resolve against PHP's global function table. Functions such as `tan`,
`log10`, `min`, and `max` are not built in, but an application can register a
reviewed deterministic callback for its own domain.

Custom functions use an explicit name, arity, and callback through
`FunctionDefinition` and `FunctionRegistry`.

Next: [errors and source spans](errors.md).

## Function policy

The registry is intentionally explicit. A function definition names its public
identifier, accepted arity, and callback; this makes review and auditing easier
than exposing the PHP runtime. Domain checks happen before the callback runs.

```php
$registry = FunctionRegistry::defaults()->with(new FunctionDefinition(
    name: 'clamp',
    minArguments: 3,
    maxArguments: 3,
    callback: static function (array $arguments): float {
        return max($arguments[1], min($arguments[2], $arguments[0]));
    },
));
$options = new EvaluationOptions(functions: $registry);
$value = Math::evaluate('clamp(score, 0, 100)', ['score' => 107], options: $options);
```

Keep callbacks deterministic and side-effect free. If a function needs I/O,
resolve that value before evaluation and pass it as a variable instead.

## Scope: expressions, not every equation

Core evaluates one expression to a finite integer or float. It does not parse
equalities, inequalities, systems, integrals, matrices, or arbitrary symbolic
identities. The private Explaining add-on adds bounded analyzers for selected
quadratic/linear equations, 2×2 systems, calculus, matrices, roots, areas, and
statistics; those analyzers return `solved`, `partial`, or `unsupported` when a
problem is outside their implemented form. Units adds a separate quantity
grammar, not general symbolic algebra.
