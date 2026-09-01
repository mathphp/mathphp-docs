# Grammar and precedence

MathPHP accepts numbers, ASCII variable names, `pi`, `e`, `tau`, and `phi`,
parentheses, unary signs, factorial, six binary operators, implicit products,
and explicitly registered functions.

| Operator | Meaning | Example |
| --- | --- | --- |
| `+`, `-` | Addition and subtraction | `18 - 4` |
| `*`, `/`, `%` | Product, quotient, remainder | `9 % 4` |
| implicit product | Product without `*` | `2x`, `2(x + 1)` |
| `^` | Right-associative exponentiation | `2^3^2` |
| `( )` | Grouping | `(subtotal + tax)` |

Exponentiation binds more tightly than a unary sign:

```text
2^3^2  = 512
-2^2   = -4
(-2)^2 = 4
-3!    = -6
```

Next: [functions and variables](functions.md).

## Token rules

Numbers may be integers or decimals; variable names use ASCII letters, digits,
and underscores but cannot begin with a digit. Whitespace is ignored between
tokens. A number, identifier, or grouped expression directly followed by
another factor is an implicit product; `xy` remains one atomic identifier,
while `x y` means `x * y`. An identifier followed by `(` is a function call,
including custom registered functions. There is no string interpolation,
property access, assignment, or implicit PHP function lookup.

## Parentheses are a contract

Prefer explicit grouping when an expression is user-authored. For example,
`price * (1 + tax)` communicates intent and produces a useful source span if
the grouped value is malformed. The AST preserves those boundaries for the
optional explaining package.

## Quick conformance table

| Input | Result or behavior |
| --- | --- |
| `1 + 2 * 3` | `7` |
| `(1 + 2) * 3` | `9` |
| `2^3^2` | `512` (right associative) |
| `2x + 1` with `x = 4` | `9` |
| `2(3 + 4)` | `14` |
| `1 / 0` | `eval.division_by_zero` |
| `unknown + 1` | unknown-variable error |
