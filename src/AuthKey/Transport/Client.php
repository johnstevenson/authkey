<?php
namespace AuthKey\Transport;

use AuthKey\Auth;
use AuthKey\Utils;


class Client
{

  public $errorCode = '';
  public $errorMsg = '';
  public $error = '';

  public $statusCode = 0;

  public $headers = array();
  public $xheaders = array();
  public $output = '';
  public $unsigned = false;

  protected $account = array();
  private $options = array();

  const ERR_INTERNAL = 'InternalError';
  const ERR_REQUEST = 'RequestError';
  const ERR_RESPONSE = 'ResponseError';


  /**
  * Initializes the component.
  *
  * Options are:
  *
  *   'strict'                - bool responses must be signed [false]
  *                             can also be set with setOption('strict', value)
  *
  *   'auth' (array)          - array of AuthKey config settings comprising:
  *
  *       name:               - string the name of the header ['Auth-Key']
  *       scheme:             - string the name of the scheme ['MAC']
  *       xname:              - string the x-headers prefix name ['mac']
  *       interval:           - integer time value in seconds [600]
  *
  *   'curl'                  - array key-value curl options
  *                             can also be set with setCurlOption(option, value)
  *
  *   'headers'               - array key-value headers to send
  *                             can also be set with setHeader(name, value)
  *
  *   'xheaders'              - array key-value xheaders to send
  *                             can also be set with setXHeader(name, value)
  *
  * @param array $account
  * @param array $options
  * @return Client
  */
  public function __construct(array $account, $options = array())
  {

    $this->account = $account;

    $this->initOptions();

    if ($options)
    {
      $this->config($options);
    }

  }


  public function send($method, $url, $content = '')
  {

    $this->init();

    if (!$this->checkContent($content, $errorMsg))
    {
      $this->setError(static::ERR_INTERNAL, $errorMsg);
      return false;
    }

    $Auth = new Auth($this->options['auth']);

    $headers = $Auth->forRequest(
      $this->account,
      $method,
      $url,
      $this->options['xheaders']
    );

    if (!$headers)
    {
      $this->setError(static::ERR_INTERNAL, $Auth->errorMsg);
      return false;
    }

    if (!$this->responseGet($method, $url, $headers, $content))
    {
      return false;
    }

    if ($this->statusCode !== 200)
    {
      $this->setError(static::ERR_REQUEST, 'Unexpected status code ' . $this->statusCode);
      return false;
    }

    if ($res = $this->checkResponse($Auth))
    {
      $this->xheaders = $Auth->getAllXHeaders();
    }

    $this->unsigned = empty($Auth->authHeader);

    return $res;

  }


  public function setCurlOption($curlOption, $value)
  {
    Utils::setOption($this->options, 'curl', $curlOption, $value);
  }


  public function setHeader($name, $value)
  {
    Utils::setOption($this->options, 'headers', $name, $value);
  }


  public function setOption($name, $value)
  {
    Utils::setOption($this->options, '', $name, $value);
  }


  public function setXHeader($name, $value)
  {
    Utils::setXHeader($this->options, $name, $value);
  }


  public function config($options)
  {
    Utils::config($this->options, $options);
  }


  protected function setError($code, $msg)
  {

    $this->errorCode = $code;
    $this->errorMsg = $msg;
    $this->error = $this->errorCode . ': ' . $this->errorMsg;

  }


  private function responseGet($method, $url, $headers, &$content)
  {

    $ch = curl_init($url);

    // set redirects here so user options can change them
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

    // pull in user options
    foreach ($this->options['curl'] as $option => $value)
    {
      curl_setopt($ch, $option, $value);
    }

    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ($method === 'POST')
    {

      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

      if (empty($this->options['headers']['Expect']))
      {
        $headers[] = 'Expect:';
      }

    }

    $ar = array();

    foreach($this->options['headers'] as $name => $value)
    {
      $ar[] = $name . ': ' . $value;
    }

    $headers = array_merge($headers, $ar);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!$response = curl_exec($ch))
    {
      $error = '(curl errno '. curl_errno($ch) . ') ' . curl_error($ch);
      $this->setError(static::ERR_INTERNAL, "Failed to open {$url}: {$error}");
      return;
    }

    $info = curl_getinfo($ch);
    $this->responseProcess($info, $response);

    return true;

  }


  private function checkContent(&$content, &$errorMsg)
  {

    $errorMsg = '';

    if (is_array($content))
    {

      if (count($content) === count($content, COUNT_RECURSIVE))
      {

        $res = false;

        // not nested, check for a string key - only one is needed
        foreach ($content as $key => $value)
        {

          if (is_string($key))
          {
            $res = true;
            break;
          }

        }

        if (!$res)
        {
          $errorMsg = 'numerically indexed array';
        }

      }
      else
      {
        $errorMsg = 'nested array';
      }

    }
    elseif (!is_string($content))
    {
      $errorMsg = gettype($content);
    }

    if ($errorMsg)
    {
      $errorMsg = 'Invalid content: ' . $errorMsg;
    }

    return empty($errorMsg);

  }


  private function responseProcess($info, $response)
  {

    // status code
    $this->statusCode = $info['http_code'];

    // headers
    $lines = explode("\r\n", substr($response, 0, $info['header_size']));
    $this->headers = array();

    foreach ($lines as $value)
    {

      $parts = explode(':', $value, 2);

      if (count($parts) === 2)
      {
        $key = trim($parts[0]);
        $this->headers[$key] = trim($parts[1]);
      }

    }

    // output
    if (strlen($response) > $info['header_size'])
    {
      $this->output = substr($response, $info['header_size']);
    }

  }


  private function init()
  {

    $this->statusCode = 0;
    $this->error = '';
    $this->headers = array();
    $this->xheaders = array();
    $this->output = '';
    $this->unsigned = false;

  }


  private function initOptions()
  {

    $this->options = array(

      'strict' => false,
      'auth' => array(),
      'curl' => array(),
      'headers' => array(),
      'xheaders' => array(),

    );

  }


  private function checkResponse(Auth $Auth)
  {

    if ($Auth->fromResponse($this->headers, !$this->options['strict']))
    {

      if (!$Auth->authHeader)
      {
        return true;
      }

      // we don't need a required value for the Client
      if ($Auth->check(array(), Utils::get($this->account, 'key')))
      {
        return true;
      }
      else
      {
        $this->setError(static::ERR_RESPONSE, $Auth->errorTxt);
        return false;
      }

    }
    else
    {
      $this->setError(static::ERR_RESPONSE, $Auth->errorTxt);
      return false;
    }

  }


}
