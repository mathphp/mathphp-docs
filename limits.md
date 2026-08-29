# Resource limits

Keep untrusted expressions bounded with `EvaluationOptions` and
`ResourceLimits`:

```php
$options = new EvaluationOptions(
    limits: new ResourceLimits(
        maxExpressionLength: 256,
        maxNestingDepth: 32,
        maxExponentMagnitude: 8,
    ),
);

Math::evaluate($input, options: $options);
```

Limits cover expression length, nesting depth, function arguments, exponent
magnitude, and factorial input. They fail closed with ordinary source-aware
exceptions before work can grow without bound.

Next: [PHP API and integration](php-api.md).

## Selecting safe defaults

Start with conservative limits for public forms, then measure real workloads.
`maxExpressionLength` protects request size, `maxNestingDepth` protects the
parser stack, and exponent/factorial limits prevent unexpectedly huge values.
Function argument limits protect custom registries as well as built-ins.

Do not use limits as a substitute for request timeouts or authentication. They
are one layer in a defense-in-depth boundary and should be recorded alongside
the expression version in audit logs.
