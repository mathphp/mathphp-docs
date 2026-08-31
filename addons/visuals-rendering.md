# Visuals rendering pipeline

Visuals separates representation from rendering. `Plotter` and analysis models
produce portable data; `VisualRepresentation` carries the kind and metadata;
`SvgRenderer` creates an embeddable fallback.

```php
$plot = (new Plotter())->plot('sin(x)', 'x', 0, 6.28);

$model = $plot->toArray(); // kind, metadata, points, SVG, and SVG data URI
$svg = $plot->svg;         // use as an <img> source or trusted inline markup
```

Add a title, axis labels, and a text summary for accessibility. Serialize the
representation over JSON when the frontend is a separate service.

Undefined samples are part of the representation contract. Line plots split SVG
paths at `y: null`; area fallbacks omit shaded fill across a gap and label the
condition for assistive technology. Do not interpolate or silently replace null
samples with zero in a custom renderer.

## Supported visual vocabulary

The private renderer includes helpers for formula cards, dependency graphs,
line plots, shaded areas, roots, calculus pairs, histograms, scatter plots,
matrices, linear systems, polar graphs, vector fields, and coordinate geometry.
Use the SVG fallback for email/PDF/server-rendered views, or pass the same model
to Canvas, WebGL, D3, Vega, or a native client renderer.
