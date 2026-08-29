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
