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
