# Auth-Key

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

The library also allows you to build your own client-server implementations using the base `AuthKey` class or extend the existing classes.

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
Otherwise you must point a PSR-0 autoloader to the `src` directory. Full usage [documentation][wiki] can be found in the wiki:

* [Client usage][client]
* [Server usage][server]
* [Build your own][authkey]
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
  [authkey]:https://github.com/johnstevenson/authkey/wiki/AuthKey-Usage
  [extending]:https://github.com/johnstevenson/authkey/wiki/Extending


