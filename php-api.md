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
evaluation events. Pass the observer as the second argument when no variables
are needed, or pass `variables` followed by the observer. The
[Explaining add-on](addons/explaining-steps.md) uses this seam without
changing Core semantics:

```php
$result = Math::evaluateWithObserver('2 * (3 + 4)', $observer);
$result = Math::evaluateWithObserver('gross * tax', ['gross' => 42, 'tax' => 0.2], $observer);
```

## Compatibility promise

The facade and exception contracts are the supported integration surface. Keep
parser internals out of application code so upgrades can remain drop-in. Pin a
compatible Composer constraint, run the core test suite in CI, and serialize
only documented values (`result`, `type`, error `code`, and `span`).

## Add-on composition

Install Core first, then load private packages in the application that needs
them. Explaining consumes observer events and emits lesson models; Units
evaluates a separate quantity grammar; Visuals consumes analysis/plot models
and emits renderer-neutral representations. Neither add-on changes the scalar
result returned by Core.

When Units is present, pass `Quantity::toArray()` through your API. Its
`value` is the normalized base value, while `displayValue` is the number in the
selected display unit; `formatted` is the human-readable label. Explaining and
Visuals preserve those fields when they serialize quantities.
