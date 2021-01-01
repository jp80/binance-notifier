# binance-notifier
A simple read-only bot for Binance API which announces large volumes and changes in price.

This is a PHP console app.

Requires: larislackers/php-binance

Requires: espeak binary in your $PATH

Currently this is hard coded for XRP/USDT pair, but you should be able to fork this repo and alter it for your own uses.

It appears that the script "bot.php" will run even without an API key/secret. I guess all we're doing so far is retrieving publicly available information. Expected the php-binance class to generate an exception due to missing credentials, alas it doesn't.

TODO: Add a setup.sh to get dependencies. These are currently included in the repo but will be removed in future.

TODO: Add configuration and log files.

TODO: Add trading features.


If this is any use to you or you have any suggestions, please let me know. Would be interesting to see people fork this repo on GitHub so I can see where other people take it.
