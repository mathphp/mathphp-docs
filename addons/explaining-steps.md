# Explaining: step-by-step evaluation

`mathphp/mathphp-explaining` adds a teaching layer to the same deterministic
evaluation. For `(5*2)*2`, it emits ordered steps:

1. Multiply `5` by `2` → `10`.
2. Multiply the partial result `10` by `2` → `20`.

Each step can include the operation, operands, partial result, translation key,
and exact source span. Render it as cards, narration, an audit log, or a
guided lesson. See the [package repository](https://github.com/mathphp/mathphp-explaining).

## Step anatomy

Every `Step` is designed for a UI, not just a log line:

- the original source span and operation label;
- child steps and dependency order;
- rendered operands and partial result;
- a stable translation key; and
- the final value and numeric type.

That means a React component, a server-rendered PHP view, and an accessibility
narrator can all consume the same explanation object.

## Other analyzers in this package

The package also exposes focused analyzers for equations, linear systems,
matrices, calculus, areas, roots, and statistics. Start with this page when you
want a lesson for a scalar expression; use the [analysis guide](visuals-analysis.md)
for a structured model of a richer mathematical object.
