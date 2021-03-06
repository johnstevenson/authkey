# AuthKey

[![Build Status](https://secure.travis-ci.org/johnstevenson/authkey.png)](http://travis-ci.org/johnstevenson/authkey)

A PHP implementation of the [Auth-Key][Auth-Key] authentication scheme.

## Contents
* [About](#About)
* [Installation](#Installation)
* [Usage](#Usage)
* [Example](#Example)
* [License](#License)


<a name="About"></a>
## About

AuthKey is an API authentication framework that sends client credentials with each request, using a special HTTP header:

`Auth-Key: MAC 1348748096:client-id:IjVWyeKKg5wJf+O5SwqAL5Zg9nJdEW5lmcZSZvGvcLU=`.

The receiving server can then check this and either allow or deny access to its API. For more information, see the [specification][Auth-Key].

This library provides **client** and **server** implementations that are easy to set up and use, freeing you from the technicalities of HTTP headers and HMAC hashes. For example, setting up a client looks like this:

```php
<?php
  # your credentials are supplied by the service provider
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
  $helpers = new HelpersClass();

  $handlers = array(
    'authorize' => $helpers->authorize, # we need a function to authorize clients
    'process' => $helpers->process,     # and a function to process successful requests
  );

  $server = new AuthKey\Transport\Server($handlers);
  $server->receive();
```

The library also allows you to build your own client-server implementations using the base `AuthKey\Auth` class or extend the existing classes.

<a name="Installation"></a>
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

<a name="Usage"></a>
## Usage
If you downloaded the library through [composer][composer] then you must add the following somewhere in your bootstrap code:

```php
<?php
 require 'vendor/autoload.php';
```
Otherwise you must point a PSR-0 autoloader to the `src` directory. Full usage [documentation][wiki] can be found in the Wiki:

* [Client usage][client]
* [Server usage][server]
* [Auth base class][authbase]
* [Extend the library][extending]

<a name="Example"></a>
## Example
The quickest way to get the library up and running locally is to point your browser to `example/client.php` and everything will load automatically. You can then experiment with the code as you read the documentation.

<a name="License"></a>
## License

Auth-Key is licensed under the MIT License - see the `LICENSE` file for details


  [Auth-Key]: https://github.com/johnstevenson/authkey/wiki/Auth-Key-Specification
  [composer]: http://getcomposer.org
  [download]: https://github.com/johnstevenson/authkey/downloads
  [wiki]:https://github.com/johnstevenson/authkey/wiki/Home
  [client]:https://github.com/johnstevenson/authkey/wiki/Client-Usage
  [server]:https://github.com/johnstevenson/authkey/wiki/Server-Usage
  [authbase]:https://github.com/johnstevenson/authkey/wiki/Auth-Base-Class
  [extending]:https://github.com/johnstevenson/authkey/wiki/Extending


