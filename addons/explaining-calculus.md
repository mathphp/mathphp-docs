# Explaining calculus

`CalculusAnalyzer` represents derivative and antiderivative requests with the
expression, variable, operation, and result metadata intact.

```php
$analysis = (new CalculusAnalyzer())->analyze('x^2 + 3*x', 'derivative', 'x');
```

For sampled curves, label numerical approximations and show the variable and
operation beside the formula. See [plots](visuals-plots.md) for presentation.
