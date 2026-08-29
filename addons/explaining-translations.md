# Explaining: translations and observers

Choose a locale at the presentation edge:

```php
$translator = Translations::create('da');
$explanation = (new Explainer($translator))->explain('(5*2)*2');
```

English and Danish catalogues are included. Add a custom catalogue without
forking the evaluator, and attach observers to your UI, API, or audit log while
Core remains the source of truth.
