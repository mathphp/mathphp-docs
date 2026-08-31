# Visuals: plots and sampling

`mathphp/mathphp-visuals` turns formulas into renderer-neutral models:

```php
$plot = (new Plotter())->plot('sin(x)', 'x', 0, 6.28);
```

The model contains samples, domain metadata, labels, and enough information to
render with SVG, Canvas, or a chart library. An accessible SVG and image-ready
data URI fallback are available when a full renderer is not.

If evaluation fails at a sample (for example, `1 / x` at `x = 0`), the point is
kept with `y: null`. The SVG fallback splits the line at that gap instead of
connecting across a discontinuity. Treat null points as undefined data in every
custom renderer.

See the [package repository](https://github.com/mathphp/mathphp-visuals).

## Plot model fields

Plot output is intentionally renderer-neutral. Your adapter can use:

- expression and variable names;
- domain and sampling configuration;
- ordered finite points;
- explicit `y: null` gaps for undefined or non-finite samples;
- labels and axis metadata; and
- SVG/image fallback output when a richer renderer is unavailable.

This separation lets you switch chart libraries without changing numerical
semantics or API payloads.
