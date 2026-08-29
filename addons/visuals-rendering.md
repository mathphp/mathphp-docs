# Visuals rendering pipeline

Visuals separates representation from rendering. `Plotter` and analysis models
produce portable data; `VisualRepresentation` carries the kind and metadata;
`SvgRenderer` creates an embeddable fallback.

```php
$representation = new VisualRepresentation('line-plot', ['points' => $points]);
$svg = (new SvgRenderer())->render($representation);
```

Add a title, axis labels, and a text summary for accessibility. Serialize the
representation over JSON when the frontend is a separate service.

## Supported visual vocabulary

The private renderer includes helpers for formula cards, dependency graphs,
line plots, shaded areas, roots, calculus pairs, histograms, scatter plots,
matrices, linear systems, polar graphs, vector fields, and coordinate geometry.
Use the SVG fallback for email/PDF/server-rendered views, or pass the same model
to Canvas, WebGL, D3, Vega, or a native client renderer.
