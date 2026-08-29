# Explaining roots

`RootAnalyzer` reports the expression, variable, bracket, and approximate root.

```php
$analysis = (new RootAnalyzer())->analyze('x^2 - 2', 'x', 0, 2);
```

Always label the result as approximate and retain the bracket so users can
understand convergence and diagnose invalid intervals.
