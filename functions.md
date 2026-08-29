# Functions and variables

Variables are passed per evaluation. Built-ins are allowlisted and validated
for arity and domain before they execute.

```php
Math::evaluate('sqrt(area) + max(minimum, 10)', [
    'area' => 81,
    'minimum' => 12,
]);
```

The default catalogue includes `abs`, `sqrt`, `sin`, `cos`, `tan`, `log`,
`log10`, `exp`, `min`, and `max`. Names are case-sensitive and never resolve
against PHP's global function table.

Custom functions use an explicit name, arity, and callback through
`FunctionDefinition` and `FunctionRegistry`.

Next: [errors and source spans](errors.md).
