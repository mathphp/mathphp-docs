# Getting started

Install the public core package:

```sh
composer require mathphp/mathphp:^0.3
```

Evaluate an expression through the single public facade:

```php
use MathPHP\Math;

$total = Math::evaluate(
    'subtotal * (1 + tax)',
    ['subtotal' => 42.5, 'tax' => 0.2],
);
```

The result is a finite `int` or `float`. Catch `MathPHP\Exception\MathException`
to expose stable error codes and source spans to your application.

Next: [grammar and precedence](grammar.md) or [the PHP API](php-api.md).

## Choose the boundary

Treat the expression as untrusted text. Pass application values as the second
argument, keep the result local, and return the error code—not a raw exception
trace—to the caller. The evaluator never reads globals and never calls `eval()`.

## A production-shaped adapter

```php
$payload = ['expression' => $request->input('formula'), 'variables' => $request->input('values', [])];
try {
    $value = Math::evaluate($payload['expression'], $payload['variables']);
    return ['ok' => true, 'value' => $value, 'type' => get_debug_type($value)];
} catch (MathException $error) {
    return ['ok' => false, 'code' => $error->errorCode(), 'span' => $error->span()->toArray()];
}
```

## What to read next

- Need to define accepted input? Start with [grammar](grammar.md).
- Need editor feedback? Read [errors](errors.md) and [limits](limits.md).
- Need a teaching UI? Add the private [Explaining package](addons/explaining-steps.md).
- Need dimensional calculations? Add the private [Units package](addons/units.md).

## Installing private add-ons

The add-ons are private VCS packages, so a consumer must declare an approved
Composer repository (or use the organization’s private Composer mirror) before
requiring one. The examples below use the current stable release lines
(`v0.3.2` for Core; `v0.3.3` for Units and Visuals; `v0.24.1` for Explaining); use
`dev-main` only when developing against unreleased changes.

```sh
composer config repositories.mathphp-units vcs https://github.com/mathphp/mathphp-units.git
composer require mathphp/mathphp-units:^0.3
```

For the teaching layer, declare both repositories because Explaining uses
Visuals at runtime:

```sh
composer config repositories.mathphp-visuals vcs https://github.com/mathphp/mathphp-visuals.git
composer config repositories.mathphp-explaining vcs https://github.com/mathphp/mathphp-explaining.git
composer require mathphp/mathphp-visuals:^0.3 mathphp/mathphp-explaining:^0.23
```

Authenticate through your normal Composer/Git policy. The MathPHP website and
packages do not issue tokens, grant access, process payment, or revoke access.
