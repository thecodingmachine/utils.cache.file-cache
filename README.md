[![Latest Stable Version](https://poser.pugx.org/mouf/utils.cache.file-cache/v/stable.svg)](https://packagist.org/packages/mouf/utils.cache.file-cache)
[![Total Downloads](https://poser.pugx.org/mouf/utils.cache.file-cache/downloads.svg)](https://packagist.org/packages/mouf/utils.cache.file-cache)
[![Latest Unstable Version](https://poser.pugx.org/mouf/utils.cache.file-cache/v/unstable.svg)](https://packagist.org/packages/mouf/utils.cache.file-cache)
[![License](https://poser.pugx.org/mouf/utils.cache.file-cache/license.svg)](https://packagist.org/packages/mouf/utils.cache.file-cache)
[![Build Status](https://travis-ci.org/thecodingmachine/utils.cache.file-cache.svg)](https://travis-ci.org/thecodingmachine/utils.cache.file-cache)

Mouf's file cache service
=========================

This package contains 2 implementations of Mouf's CacheInterface that stores the cache in files on the server's hard drive.

- `FileCache` is a service that stores cache keys in files. The value is *serialized*.
- `PhpFileCache` is a **more efficient** service that stores cache keys in executable PHP files. You should prefer
  this implementation unless you have security concerns about `var_export`ing your cache keys.

To learn more about the cache interface, please see the [cache system documentation](http://mouf-php.com/packages/mouf/utils.cache.cache-interface).
