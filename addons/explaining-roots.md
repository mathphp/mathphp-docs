# Explaining roots

`RootAnalyzer` uses bisection and reports the expression, variable, original
bracket, convergence history, and approximate root:

```php
$analysis = (new RootAnalyzer())->analyze('x^2 - 2', 'x', 0, 2, iterations: 20);

$analysis->status; // solved
$analysis->root;   // approximately 1.4142135623731
$analysis->iterations; // midpoint evidence for each step
```

Always label the result as approximate and retain the original bracket so users
can understand convergence. The analyzer requires a finite, increasing
interval whose endpoint values have opposite signs (an endpoint at zero is
valid).

## Partial results are explicit

When the endpoints do not bracket a root, or an interior midpoint is undefined
or non-finite, the analyzer returns `status: partial` and `root: null` rather
than inventing a root. Any successful iterations remain available for a
diagnostic view; an undefined midpoint is not added to the history:

```php
$analysis = (new RootAnalyzer())->analyze('1/x', 'x', -1, 1);

$analysis->status; // partial
$analysis->root;   // null
```

Render the steps and convergence visual alongside the status, and explain that
the interval must be split or the expression/domain changed before a root can
be certified.
