# Tiny Calculator

A fast and tiny calculator library to calc the value with support of most operator and function ability.

# Price Eequation

Price equations allow you to automatically update your price with the price fluctuations.

## Using

```php
<?php
include "calc.php";
$calc = calc::create();
$calc->addFunc("stephin", function() {
	return rand(1,10);
});
echo $calc->calc('stephin() + 1+2*3/4');
```

## Goal

### Price equations for Bitcoin advertisements

For most Bitcoin traders, adjusting the price of advertisements by setting a margin works well enough. But if you are a high volume trader the price equation feature allows you more control over your price. This guide explains the fundamentals on how you can use complex price equations to your advantage to beat the competition.

## What is a price equation?

Price equations allow you to automatically update your price with the price fluctuations of Bitcoin. They work by taking price information that we automatically fetch from market data sources (Other Bitcoin exchanges) and updating the price of your advertisement as soon as new price information is fetched. To create a custom price equation you select your market data sources and combine or modify them using operators.

The default price equation we use for advertisements uses a special market data source and a simple multiplication operator to place a margin on top of the market data price.

---------

# Max Base

My nickname is Max, Programming language developer, Full-stack programmer. I love computer scientists, researchers, and compilers.

## Asrez Team

A team includes some programmer, developer, designer, researcher(s) especially Max Base.

[Asrez Team](https://www.asrez.com/)
