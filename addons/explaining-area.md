# Explaining area under a curve

`AreaAnalyzer` makes interval, sampling, and approximation metadata visible:

```php
$analysis = (new AreaAnalyzer())->analyze('x^2', 'x', 0, 1);
```

Use the model for a shaded-area lesson and provide a textual summary for users
who cannot see the chart. Preserve endpoints in exports.
