# Visuals: plots and sampling

`mathphp/mathphp-visuals` turns formulas into renderer-neutral models:

```php
$plot = (new Plotter())->plot('sin(x)', 'x', 0, 6.28);
```

The model contains samples, domain metadata, labels, and enough information to
render with SVG, Canvas, or a chart library. An accessible SVG and image-ready
data URI fallback are available when a full renderer is not.

See the [package repository](https://github.com/mathphp/mathphp-visuals).

## Plot model fields

Plot output is intentionally renderer-neutral. Your adapter can use:

- expression and variable names;
- domain and sampling configuration;
- ordered finite points;
- labels and axis metadata; and
- SVG/image fallback output when a richer renderer is unavailable.

This separation lets you switch chart libraries without changing numerical
semantics or API payloads.
