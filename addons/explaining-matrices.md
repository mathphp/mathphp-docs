# Explaining matrices

`MatrixAnalyzer` validates nested numeric arrays and returns dimensions, rows,
columns, and values for a stable explanation model.

```php
$analysis = (new MatrixAnalyzer())->analyze([[1, 2], [3, 4]]);
```

Ragged rows and non-numeric entries fail early. Feed the result to a heatmap or
the private visuals renderer; keep the raw matrix available for accessible text.
