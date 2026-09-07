# Explaining matrices

`MatrixAnalyzer` validates nested numeric arrays and returns dimensions, rows,
columns, and values for a stable explanation model.

```php
$analysis = (new MatrixAnalyzer())->analyze([[1, 2], [3, 4]]);
```

Ragged rows and non-numeric entries fail early. Feed the result to a heatmap or
the private visuals renderer; keep the raw matrix available for accessible text.

## Matrix equations

For a structured numeric equation `A·x = b`, use the bounded Gaussian
elimination analyzer:

```php
use MathPHP\Explaining\EquationAnalyzer;

$analysis = (new EquationAnalyzer())->analyzeMatrixEquation(
    [[2, 1, -1], [-3, -1, 2], [-2, 1, 2]],
    [8, -11, -3],
);
// $analysis->solution === [2.0, 3.0, -1.0]
```

Square systems from 1×1 through 32×32 are validated before partial-pivot
elimination. A unique system returns `solved` and a numeric vector; singular
or inconsistent systems return `partial` with rank, consistency, augmented
matrix, and learner-facing elimination steps. This API requires the matrix and
right-hand vector explicitly because those shapes cannot be inferred safely
from an arbitrary equation string.
