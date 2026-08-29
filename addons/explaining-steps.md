# Explaining: step-by-step evaluation

`mathphp/mathphp-explaining` adds a teaching layer to the same deterministic
evaluation. For `(5*2)*2`, it emits ordered steps:

1. Multiply `5` by `2` → `10`.
2. Multiply the partial result `10` by `2` → `20`.

Each step can include the operation, operands, partial result, translation key,
and exact source span. Render it as cards, narration, an audit log, or a
guided lesson. See the [package repository](https://github.com/mathphp/mathphp-explaining).
