# Explaining area under a curve

`AreaAnalyzer` makes interval, sampling, and approximation metadata visible.
It uses Simpson's rule and keeps the sampled points in a renderer-neutral visual
model:

```php
$analysis = (new AreaAnalyzer())->analyze('x^2', 'x', 0, 1, samples: 21);

$analysis->status; // solved
$analysis->area;   // approximately 0.333333333333
$analysis->toArray(); // domain, status, area, steps, and visual data
```

`samples` is clamped to a safe range and made odd so Simpson's rule has the
required partition. The returned `area` is a signed estimate; it is not a
claim of symbolic integration.

## Partial results are explicit

If any sample is undefined or non-finite, the analyzer does not replace it with
zero and does not report a biased numeric area. It returns `status: partial`,
`area: null`, and keeps the gap in the visual data as a point whose `y` value is
`null`:

```php
$analysis = (new AreaAnalyzer())->analyze('1/x', 'x', -1, 1, samples: 11);

$analysis->status; // partial
$analysis->area;   // null
```

Use the steps and null points to explain why the interval needs to be split or
reframed. Preserve the domain, sample count, endpoints, and null gaps in
exports, and provide a textual summary for users who cannot see the chart.
