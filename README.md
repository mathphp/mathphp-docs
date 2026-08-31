# MathPHP documentation

User-facing documentation for the [MathPHP](https://github.com/mathphp/mathphp)
core library and its optional extensions.

For maintainers, [RELEASING.md](RELEASING.md) defines the package tag order,
validation gates, and production verification checklist.

This repository is intentionally separate from the implementation and the
normative specification. Use the feature guides below for focused examples,
then consult [REFERENCE.md](REFERENCE.md) for the complete reference.

## Core guides

- [Getting started](getting-started.md)
- [Grammar and precedence](grammar.md)
- [Functions and variables](functions.md)
- [Errors and source spans](errors.md)
- [Resource limits](limits.md)
- [PHP API and integration](php-api.md)

## Private add-on guides

- [Explaining: step-by-step evaluation](addons/explaining-steps.md)
- [Explaining: translations and observers](addons/explaining-translations.md)
- [Visuals: plots and sampling](addons/visuals-plots.md)
- [Visuals: equations, matrices, and calculus](addons/visuals-analysis.md)
- [Explaining: equations](addons/explaining-equations.md)
- [Explaining: linear systems](addons/explaining-systems.md)
- [Explaining: matrices](addons/explaining-matrices.md)
- [Explaining: calculus](addons/explaining-calculus.md)
- [Explaining: areas](addons/explaining-area.md)
- [Explaining: roots](addons/explaining-roots.md)
- [Explaining: statistics](addons/explaining-statistics.md)
- [Visuals: rendering pipeline](addons/visuals-rendering.md)
- [Units: quantities and dimensional arithmetic](addons/units.md)

## Reading paths

For a first integration, follow Getting started → Grammar → Errors → PHP API.
For an educational product, add Explaining steps → translations → analyzers.
For a charting product, add Visuals plots → rendering → analysis models.
For measurement-aware products, add the Units guide before the explaining or visual layer.

The website playground exposes the same API shapes as these guides and is useful
for checking examples before writing application code.

Documentation CI checks every relative Markdown link without requiring network
access; external links remain informational and are not treated as release
gates.

- Website: https://mathphp.diderichsen.com
- Specs: https://github.com/mathphp/mathphp-specs
- Private explaining add-on: https://github.com/mathphp/mathphp-explaining
- Private visuals add-on: https://github.com/mathphp/mathphp-visuals
- Private units add-on: https://github.com/mathphp/mathphp-units
