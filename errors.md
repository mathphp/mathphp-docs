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
