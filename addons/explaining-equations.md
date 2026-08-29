# Explaining equations

`EquationAnalyzer` turns an equation into a structured explanation: original
text, known values, unknown symbols, and solving status. It deliberately keeps
partial solutions honest when a closed form is unavailable.

```php
$analysis = (new EquationAnalyzer())->analyze('x^2 + 1 = 5', ['x' => 2]);
$json = $analysis->toArray();
```

Render the model as a prompt, a hint, or an audit record. Pair it with the
[translation layer](explaining-translations.md) for learner-facing copy.
