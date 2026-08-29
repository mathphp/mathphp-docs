# PHP API and integration

The supported surface is deliberately small:

- `MathPHP\Math`
- `EvaluationOptions` and `ResourceLimits`
- `FunctionDefinition` and `FunctionRegistry`
- `MathException` and its phase subclasses
- `SourceSpan`

```php
use MathPHP\Exception\MathException;
use MathPHP\Math;

try {
    $result = Math::evaluate('2 * (3 + 4)');
} catch (MathException $error) {
    // Send $error->errorCode() and $error->span() to your UI.
}
```

Use `Math::evaluateWithObserver()` when a separately distributed layer needs
evaluation events. The [Explaining add-on](addons/explaining-steps.md) uses
this seam without changing Core semantics.
