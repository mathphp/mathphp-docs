# Explaining: translations and observers

Choose a locale at the presentation edge:

```php
$translator = Translations::create('da');
$explanation = (new Explainer($translator))->explain('(5*2)*2');
```

English and Danish catalogues are included. Add a custom catalogue without
forking the evaluator, and attach observers to your UI, API, or audit log while
Core remains the source of truth.

## Translation workflow

1. Evaluate with Core and keep the numeric result language-neutral.
2. Select a translator at the request or user-preference boundary.
3. Render the step key and interpolation values in your own component.
4. Add a catalogue entry when your product needs a different tone or language.

Observers can capture the same events for analytics, audit trails, or a “show
your work” mode without changing the evaluator’s return value.
