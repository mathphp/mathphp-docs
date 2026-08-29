# Grammar and precedence

MathPHP accepts numbers, ASCII variable names, `pi` and `e`, parentheses,
unary signs, factorial, six binary operators, and explicitly registered
functions.

| Operator | Meaning | Example |
| --- | --- | --- |
| `+`, `-` | Addition and subtraction | `18 - 4` |
| `*`, `/`, `%` | Product, quotient, remainder | `9 % 4` |
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

Numbers may be integers or decimals; variable names use ASCII letters,
digits, and underscores but cannot begin with a digit. Whitespace is ignored
between tokens. There is no string interpolation, property access, assignment,
or implicit PHP function lookup.

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
| `1 / 0` | `eval.division_by_zero` |
| `unknown + 1` | unknown-variable error |
