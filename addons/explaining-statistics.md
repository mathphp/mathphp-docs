# Explaining statistics

`StatisticsAnalyzer` keeps a numeric sample close to its summary and histogram
bin choice:

```php
$analysis = (new StatisticsAnalyzer())->analyze([2, 3, 3, 4, 8], 4);
```

Expose the sample, count, spread, and bins together. The visuals package can
turn the same model into a histogram without changing the calculations.
