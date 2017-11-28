# WordPress Translation Cache

The WordPress gettext implementation is very slow. It uses objects that
cannot be cached into memory without reinstanting them.

pomodoro stores all seen translations as a PHP hashtable (array), that can be
subsequently stored into a file as PHP code and levereged via OPcache when loaded.

Moreover, pomodoro does lazyloading for strings that are only encountered in output.
It does not preload everything it can for a domain until there's no other choice.

## But does it really work?

We think so :)

Example on a vanilla `ru_RU` locale WordPress site:

Before:

![Before](https://raw.githubusercontent.com/pressjitsu/pomodoro/master/before.png)

After:

![After](https://raw.githubusercontent.com/pressjitsu/pomodoro/master/after.png)

## Installation

Drop pomodoro.php into mu-plugins and enjoy the added speed :)

Requires PHP7+. The more plugins you have the better the performance gains.

## Support

Let us know how it goes at support@pressjitsu.com ;)


## License

GPLv3
