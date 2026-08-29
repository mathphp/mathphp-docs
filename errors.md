# Errors and source spans

Expression failures are `MathException` instances with a stable code and an
exact `SourceSpan`. This lets a form or editor underline the offending text.

```php
try {
    Math::evaluate('10 / 0');
} catch (MathException $error) {
    $code = $error->errorCode(); // eval.division_by_zero
    $start = $error->span()->start;
    $end = $error->span()->end;
}
```

Common codes include `lex.malformed_number`, `parse.unexpected_token`,
`eval.division_by_zero`, and `eval.integer_overflow`. Lexical, parse, and
evaluation subclasses preserve the phase for logging and UI decisions.

Next: [resource limits](limits.md).

## Handling phases differently

Lex errors mean the input cannot be tokenized; parse errors mean the token
sequence cannot form an expression; evaluation errors mean the expression is
valid but its values are not. Log the phase for operators, show the span to
users, and avoid exposing internal class names in API responses.

```php
$span = $error->span();
$response = [
    'code' => $error->errorCode(),
    'message' => $error->getMessage(),
    'start' => $span->start,
    'end' => $span->end,
];
```

The source span uses the original expression offsets, so a client can underline
the exact token even when whitespace or parentheses are present.
