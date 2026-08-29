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

## Compatibility promise

The facade and exception contracts are the supported integration surface. Keep
parser internals out of application code so upgrades can remain drop-in. Pin a
compatible Composer constraint, run the core test suite in CI, and serialize
only documented values (`result`, `type`, error `code`, and `span`).

## Add-on composition

Install Core first, then load private packages in the application that needs
them. Explaining consumes observer events and emits lesson models; Visuals
consumes analysis/plot models and emits renderer-neutral representations. Neither
package changes the numerical result returned by Core.
