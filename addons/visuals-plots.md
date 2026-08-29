# Visuals: plots and sampling

`mathphp/mathphp-visuals` turns formulas into renderer-neutral models:

```php
$plot = (new Plotter())->plot('sin(x)', 'x', 0, 6.28);
```

The model contains samples, domain metadata, labels, and enough information to
render with SVG, Canvas, or a chart library. An accessible SVG and image-ready
data URI fallback are available when a full renderer is not.

See the [package repository](https://github.com/mathphp/mathphp-visuals).
