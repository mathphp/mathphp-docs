# Visuals: equations, matrices, and calculus

The visuals add-on provides structured models for equation analysis, linear
systems, matrices, derivatives, antiderivatives, areas, roots, and statistics.
Your frontend receives knowns, unknowns, constraints, intervals, samples, and
result metadata instead of parsing display strings.

This keeps numerical semantics in the library and presentation decisions in
your application. Models can be serialized through an API or rendered through
your own component system.

## Feature map

| Model | Useful for |
| --- | --- |
| Equation analysis | knowns, unknowns, and partial solving |
| System analysis | two-equation linear systems |
| Matrix analysis | dimensions, operations, and summaries |
| Calculus analysis | derivatives and antiderivatives |
| Area analysis | bounded integrals and interval metadata |
| Root analysis | bracketed root-finding and convergence details |
| Statistics analysis | summaries and distribution-ready values |

Pair these models with the [plots guide](visuals-plots.md) when the result also
needs a chart or visual fallback.
