# StockFighter PHP Solutions

**SPOILER ALERT** These are my **solutions** for StockFighter. Feel free to take a look at them and offer
critique/suggestions/advice/whatever, but just be aware that **if you do not want to see spoilers for
StockFighter, do not look at this code.**

I wrote a nifty PHP library that I use in this code, and that can be found (with no spoilers!) 
[here.](https://github.com/sammarks/stockfighter-php)

## Installation

To install my solution set (and use them toward your own StockFighter account), you'll need to follow
these steps:

```
composer install # Install composer dependencies.
cp config/api.json config/api.local.json # Create your configuration file.
```

Then, add your API key to `config/api.local.json`. Once you've done that, you should be ready to
launch the application:

```
chmod a+x ./stockfighter
./stockfighter
```

The help screen will show you how to use each level command. Each level has arguments for the items that
change (account number, stock, venue, etc).

## Level Comments

My solutions for each level certainly aren't the best (no one is perfect, of course!), so here are my
comments on my implementation for each level:

### Chock a Block

On this level, I tried to first brute-force it and order 1000 shares at a time, spreading them apart
by 10 seconds or something like that. Of course, that didn't work because I had no idea what the price
was when I was making the purchase.

I ended up adopting an approach that monitored the WebSocket connection for the current quote for the
stock in question, and then bought once it went below a specific threshold (the last purchase price
of the stock at the beginning of execution plus $1.00 padding). That seemed to work for my case,
though it failed the first time around (I was initially using the ask price instead of the last price,
so that might have something to do with it). If it doesn't work for you the first time, try, try again!

I'll also clean up the code for this level once I use it in some other levels :)
