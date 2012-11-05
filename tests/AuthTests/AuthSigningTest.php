<?php

use \AuthKey\Auth;


class AuthSigningTest extends \AuthTests\Base
{

  public $accountId = '';
  public $accountKey = '';
  public $path = '';
  public $query = '';
  public $urlBase = '';
  public $xheaders = array();
  public $refClass = null;

  public function setUp()
  {

    $this->accountId = 'example-id';
    $this->accountKey = 'U7ZPJyFAX8Gr3Hm2DFrSQy3x1I3nLdNT2U1c+ToE5Vk=';

    $this->urlBase = 'http://example.com';
    $this->path = '/api';
    $this->query = '';
    $this->xheaders['username'] = 'fred';
    $this->xheaders['content-type'] = 'application/json';

  }


  /**
  * Checks SigningKey created correctly
  *
  *
  */
  public function testSigningKey()
  {

    $timestamp = time();

    $key = $this->getSigningKey($timestamp);

    $auth = new Auth();
    $method = $this->getAuthPrivate($auth, 'getSigningKey', false);

    $this->assertEquals($key,
      $method->invoke($auth, $this->accountKey, $timestamp));

  }


  /**
  * Checks StringToSign is created correctly for a request
  *
  *
  */
  public function testStringToSignRequest()
  {

    $auth = new Auth();
    $this->forRequest($auth, 'GET');

    $timestamp = $this->getAuthPrivate($auth, 'timestamp');
    $requestId = $auth->requestId;
    $method = $this->getAuthPrivate($auth, 'getStringToSign', false);

    $str = "GET<LF>/api<LF><LF>x-mac-content-type:application/json<LF>x-mac-username:fred<LF>MAC<LF>{$timestamp}<LF>{$requestId}";

    $this->assertEquals($this->formatStr($str), $method->invoke($auth));

  }


  /**
  * Checks StringToSign is created correctly for a response
  *
  * We use a non-default scheme: SecretKey
  *
  */
  public function testStringToSignResponse()
  {

    $this->config['scheme'] = 'SecretKey';

    $auth = new Auth($this->config);

    $account = $this->getAccount();
    $xheaders = array('content-type' => 'application/json');
    $auth->forResponse($account, $xheaders);

    $timestamp = $this->getAuthPrivate($auth, 'timestamp');
    $requestId = $auth->requestId;
    $method = $this->getAuthPrivate($auth, 'getStringToSign', false);

    $str = "<LF><LF><LF>x-mac-content-type:application/json<LF>SecretKey<LF>{$timestamp}<LF>{$requestId}";

    $this->assertEquals($this->formatStr($str), $method->invoke($auth));

  }


  /**
  * Check that the signature is created correctly
  *
  */
  public function testSignature()
  {

    $auth = new Auth();
    $this->forRequest($auth, 'GET');

    $timestamp = $this->getAuthPrivate($auth, 'timestamp');
    $requestId = $auth->requestId;
    $signature = $this->getAuthPrivate($auth, 'signature');

    $key = $this->getSigningKey($timestamp);
    $str = "GET<LF>/api<LF><LF>x-mac-content-type:application/json<LF>x-mac-username:fred<LF>MAC<LF>{$timestamp}<LF>{$requestId}";

    $expects = $this->getSignature($this->formatStr($str), $key);

    $this->assertEquals($expects, $signature);

  }


  /**
  * Check that the Auth-Key header is created correctly
  *
  */
  public function testAuthHeader()
  {

    $auth = new Auth();
    $this->forRequest($auth, 'GET');

    $timestamp = $this->getAuthPrivate($auth, 'timestamp');
    $requestId = $auth->requestId;

    $key = $this->getSigningKey($timestamp);
    $str = "GET<LF>/api<LF><LF>x-mac-content-type:application/json<LF>x-mac-username:fred<LF>MAC<LF>{$timestamp}<LF>{$requestId}";

    $signature = $this->getSignature($this->formatStr($str), $key);

    $expects = Auth::DEF_NAME . ': ' . Auth::DEF_SCHEME . ' ' . "{$timestamp}:{$this->accountId}:{$requestId}:{$signature}";

    $this->assertEquals($expects, $auth->authHeader);

  }



  private function getSigningKey($timestamp)
  {

    # SigningKey = HASH-SHA-256( AccountKey + Timestamp )
    return hash('sha256', $this->accountKey . $timestamp, true);

  }


  private function getSignature($strToSign, $signingKey)
  {

    # Signature = Base64( HMAC-SHA-256( StringToSign, SigningKey ) )
    return base64_encode(hash_hmac('sha256', $strToSign, $signingKey, true));

  }


  private function forRequest($auth, $method)
  {

    $account = $this->getAccount();
    $url = $this->getUrl();
    return $auth->forRequest($account, $method, $url, $this->xheaders);

  }

  private function getUrl()
  {
    return $this->urlBase . $this->path . $this->query;
  }

  private function getAccount()
  {

    return array(
      'id' => $this->accountId,
      'key' => $this->accountKey
      );

  }

  private function getAuthPrivate($auth, $name, $property = true)
  {

    if (!$this->refClass)
    {
      $this->refClass = new \ReflectionClass($auth);
    }

    if ($property)
    {
      $private = $this->refClass->getProperty($name);
      $private->setAccessible(true);
      return $private->getValue($auth);
    }
    else
    {
      $private = $this->refClass->getMethod($name);
      $private->setAccessible(true);
      return $private;
    }

  }

  private function formatStr($str)
  {
    return str_replace('<LF>', "\n", $str);
  }

}

