# Functions and variables

Variables are passed per evaluation. Built-ins are allowlisted and validated
for arity and domain before they execute.

```php
Math::evaluate('sqrt(area) + max(minimum, 10)', [
    'area' => 81,
    'minimum' => 12,
]);
```

The default catalogue includes `abs`, `sqrt`, `sin`, `cos`, `tan`, `log`,
`log10`, `exp`, `min`, and `max`. Names are case-sensitive and never resolve
against PHP's global function table.

Custom functions use an explicit name, arity, and callback through
`FunctionDefinition` and `FunctionRegistry`.

Next: [errors and source spans](errors.md).

## Function policy

The registry is intentionally explicit. A function definition names its public
identifier, accepted arity, and callback; this makes review and auditing easier
than exposing the PHP runtime. Domain checks happen before the callback runs.

```php
$registry = new FunctionRegistry();
$registry->register(new FunctionDefinition(
    name: 'clamp',
    minArguments: 3,
    maxArguments: 3,
    callback: static fn (float $value, float $min, float $max): float => max($min, min($max, $value)),
));
$value = Math::evaluate('clamp(score, 0, 100)', ['score' => 107], $registry);
```

Keep callbacks deterministic and side-effect free. If a function needs I/O,
resolve that value before evaluation and pass it as a variable instead.
