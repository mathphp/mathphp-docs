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
