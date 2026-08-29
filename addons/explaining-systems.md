# Explaining linear systems

`SystemAnalyzer` keeps equations and unknowns together so a UI can explain
substitution or elimination without reparsing strings.

```php
$analysis = (new SystemAnalyzer())->analyze([
    '2*x + 3*y = 8',
    'x - y = 1',
]);
```

The returned array is safe to serialize. Use explicit status text for unique,
underdetermined, or inconsistent systems and link the original equations in a
reviewable transcript.
