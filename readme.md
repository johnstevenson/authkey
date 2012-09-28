# Auth-Key

[![Build Status](https://secure.travis-ci.org/johnstevenson/authkey.png)](http://travis-ci.org/johnstevenson/authkey)

A PHP implementation of the [Auth-Key][Auth-Key] authentication scheme.



## About

Auth-Key is an API authentication scheme that enables a client to send its credentials to a service provider in a secure manner. The receiving server can then check these and either allow or deny access to its API. For more information, see the [specification][Auth-Key].

This library provides **client** and **server** implementations that are easy to set up and use, freeing you from the technicalities of HTTP headers and HMAC hashes. For example, setting up a client looks like this:

```php
<?php
  $credentials = array(
    'id' => 'MyAccountId',
    'key' => 'MyAccountKey',
  );

  $client = new AuthKey\Transport\Client($credentials);
  $client->send('POST', $url, $data);

  # now do something with $client->output
```

Setting up a server requires a little more work:

```php
<?php
  $handlers = array(
    'authorize' => new AuthorizeClass(), # we need a class to authorize the client
    'process' => new ProcessClass(),     # and a class to process successful requests
  );

  $server = new AuthKey\Transport\Server($handlers);
  $server->receive();
```

The library also allows you to build your own client-server implementations or extend the existing classes.

## Installation
The easiest way is [through composer][composer]. Just create a `composer.json` file and run `php composer.phar install` to install it:

```json
{
    "minimum-stability": "dev",
    "require": {
        "authkey/authkey": "1.0.*"
    }
}
```

Alternatively, you can [download][download] and extract it, or clone this repo.

## Usage
If you downloaded the library through [composer][composer] then you must add

```php
<?php
 require 'vendor/autoload.php';
```
somewhere in your bootstrap code, otherwise you must point a PSR-0 autoloader to the `src` directory so that the classes are automatically included. If you just want to have a quick play, point your browser to `example/client.php` and everything will run automatically.

Full usage [documentation][wiki] can be found in the wiki.

* **[Client usage][client]**
* **[Server usage][server]**
* **[Build your own][authkey]**
* **[Extend the library][extending]**

## License

Auth-Key is licensed under the MIT License - see the `LICENSE` file for details


  [Auth-Key]: https://github.com/johnstevenson/authkey/wiki/Auth-Key-Specification
  [composer]: http://getcomposer.org
  [download]: https://github.com/johnstevenson/authkey/downloads
  [wiki]:https://github.com/johnstevenson/authkey/wiki/Home
  [client]:https://github.com/johnstevenson/authkey/wiki/Client-usage
  [server]:https://github.com/johnstevenson/authkey/wiki/Server-usage
  [authkey]:https://github.com/johnstevenson/authkey/wiki/AuthKey-usage
  [extending]:https://github.com/johnstevenson/json-rpc/wiki/Extending


