# Getting started

Install the public core package:

```sh
composer require mathphp/mathphp
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
