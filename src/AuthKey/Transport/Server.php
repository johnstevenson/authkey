<?php
namespace AuthKey\Transport;

use AuthKey\Auth;
use AuthKey\Utils;


class Server
{

  public $accountId = '';
  public $accountKey = '';

  /** @var Auth */
  private $Auth;
  private $handlers = array();
  private $options = array();
  private $required = array();
  private $stage = 0;


  /**
  * Initializes the component.
  *
  * Handlers:
  *
  *   'authorize'             - function authorize(Server $Server)
  *                             Required if using receive() method
  *                             Sets the accountKey for the client
  *
  *   'process'               - function process(Server $Server)
  *                             Optional if using receive() method
  *                             Processes the request if set, otherwise
  *                             execution is returned to the caller
  *
  * Options are:
  *
  *   'public'                - bool allow unsigned requests for public resources [false]
  *   'strict'                - bool responses must be signed [false]
  *
  *   'auth' (array)          - array of AuthKey config settings comprising:
  *       name:                 - string the name of the header ['Auth-Key']
  *       scheme:               - string the name of the scheme ['MAC']
  *       xname:                - string the x-headers prefix name ['mac']
  *       interval:             - integer time value in seconds [600]
  *
  * @param array $handlers
  * @param array $options
  */
  public function __construct(array $handlers, array $options = array())
  {

    ini_set('display_errors', '0');
    header('HTTP/1.1 500 Internal Server Error', true, 500);

    $this->handlers = $handlers;

    $this->initOptions();

    if ($options)
    {
      Utils::config($this->options, $options);
    }

    $this->Auth = new Auth($this->options['auth']);

  }


  /**
  * Returns the value of a request xheader or an empty string.
  *
  * Accepts $name as either prefixed(x-nnn-keyname) or unprefixed (keyname)
  *
  * @param string $name Can be the prefixed or unprefixed name
  * @return string
  */
  public function getRequestXHeader($name)
  {
    return $this->Auth->getXHeader($name);
  }


  /**
  * Sets whether responses should be signed
  *
  * @param bool $value
  */
  public function setStrictMode($value)
  {
    Utils::setOption($this->options, '', 'strict', $value);
  }


  /**
  * Sets an xheader to be sent with the response.
  *
  * @param string $name Must be the unprefixed name
  * @param string $value
  */
  public function setXHeaderOut($name, $value)
  {
    Utils::setXHeader($this->options, $name, $value);
  }


  public function receive()
  {

    $this->checkStage(1);

    # read headers for accountId - will error exit on failure
    $this->receiveSetClientAccountId();

    # call authorize handler - will error exit on failure
    $this->authorizeClient();


    # account values can be empty if public is true (already checked)
    if ($this->accountId && $this->accountKey)
    {
      # will error exit on failure
      $this->receiveCheckRequest();
    }

    # if we have a handler registered we call it then exit...

    if (!empty($this->handlers['process']))
    {
      return Utils::callHandler($this->handlers, 'process', array($this));
    }
    else
    {
      # ... otherwise execution returns for the caller to handle
      return;
    }

  }


  /**
  * Checks the incoming headers for a signed request and returns
  * the client accountId.
  *
  * Note that if the "public" option is true an empty value
  * may be returned, which signifies an unsigned request.
  *
  * @return string The client accountId
  */
  public function receiveSetClientAccountId()
  {

    $this->checkStage(2);

    if (!$this->Auth->fromRequest($_SERVER, $this->options['public']))
    {
      $this->replyError($this->Auth);
    }

    $this->accountId = $this->Auth->accountId;

  }


  /**
  * Checks the request signature using $this->accountKey.
  * Note that if this fails an error response will be sent and
  * the script will finish.
  *
  * @return bool True only
  */
  public function receiveCheckRequest()
  {

    $this->checkStage(3);

    if (!$this->Auth->check($this->required, $this->accountKey))
    {
      $this->replyError($this->Auth);
    }

    return true;

  }


  /**
  * The optional headers array can be used to set the HTTP status header for the response.
  * This is only required when a status code other than 200, 400, 403 or 500 must be returned.
  * The value is passed to the PHP header() function.
  * For example:
  *   HTTP/1.1 409 Conflict
  *
  * Or if sending a redirect:
  *   Location: http://www.example.com/
  *
  *
  * @param string $content
  * @param array $headers
  */
  public function reply($content, $headers = array())
  {

    $responseCode = 200;
    $headers = (array) $headers;
    $headers = $this->getUniqueHeaders($headers, $responseCode);

    $authHdrs = array();
    $strict = Utils::get($this->options, 'strict');

    if ($this->Auth->accountId && ($this->options['xheaders'] || $strict))
    {

      $account = array(
        'id' => $this->Auth->accountId,
        'key' => $this->Auth->accountKey,
      );

      $authHdrs = $this->Auth->forResponse($account, $this->options['xheaders']);

      if (!$authHdrs)
      {
        $this->replyError($this->Auth);
      }

    }

    $headers = array_merge($headers, $authHdrs);

    $this->output($responseCode, $content, $headers);

  }


  /**
  * Exits the process with an appropriate HTTP status code
  * and optional formatted error message.
  *
  * If $error is null, the process exits with:
  *   HTTP/1.1 500 Internal Server Error
  *
  * If $error is an integer the process exits using this as
  * the HTTP status code.
  *
  * If $error is an array it must include following keys:
  *
  *   'errorResponse'   - the HTTP status code [403]
  *   'errorCode'       - an error code ['AccessDenied']
  *   'errorMsg'        - an error message ['Access Denied']
  *
  *   ... plus any additional key/value info
  *
  * @param mixed $mixed Either null, an integer or an array
  */
  public function replyError($error = null)
  {
    $this->replyErrorWork($error);
  }


  /**
  * Sets a required x-header value
  *
  * Accepts a string containing either a single or a comma-separated list
  * of values, or an array. Values can be either prefixed or unprefixed.
  *
  * @param mixed $value String or Array
  */
  public function setRequired($value)
  {

    if (is_string($value))
    {
      $value = explode(',', $value);
    }

    if (is_array($value))
    {

      foreach ($value as $name)
      {
        $this->required[] = trim($name);
      }

    }
    else
    {
      throw new \Exception('Unexpected required type: ' . gettype($value));
    }

  }


  private function authorizeClient()
  {

    $res = Utils::callHandler($this->handlers, 'authorize', array($this));

    if ($res && is_bool($res))
    {
      return;
    }
    elseif (!$res)
    {

      $res = array(
        'errorResponse' => 403,
        'errorMsg' => 'The AccountId you provided does not exist in our records',
        'errorCode' => 'InvalidAccountId',
      );

    }
    elseif (!is_array($res))
    {
      throw new \Exception('Unexpected result type: ' . gettype($res));
    }

    $this->replyErrorWork($res);

  }


  private function replyErrorWork($param = null)
  {

    if ($param instanceof Auth)
    {
      $this->output($this->Auth->errorResponse, $this->Auth->getError());
    }

    if (!$param)
    {
      $this->output(500);
    }
    elseif (is_int($param))
    {
      $this->output($param);
    }
    elseif (!is_array($param))
    {
      $this->output(500);
    }

    if (empty($param['errorResponse']) || empty($param['errorCode']) || empty($param['errorMsg']))
    {
      $this->output(500);
    }

    $this->output($param['errorResponse'], $this->Auth->getError($param));

  }


  private function output($responseCode, $content = '', array $headers = array())
  {

    while (@ob_end_clean()) {}

    if (headers_sent($filename, $linenum))
    {
      // we can't throw an exception because we may already be responding
      // to one, so we just write to the error log
      error_log("Headers already sent in {$filename} on line {$linenum}");
      exit;
    }

    switch ($responseCode)
    {

      case 0; # means we have a status header
        break;

      case 200:
        header('HTTP/1.1 200 OK', true, $responseCode);
        break;

      case 400:
        header('HTTP/1.1 400 Bad Request', true, $responseCode);
        break;

      case 403:
        header('HTTP/1.1 403 Forbidden', true, $responseCode);
        break;

      case 404:
        header('HTTP/1.1 404 Not Found', true, $responseCode);
        header('Status: 404 Not Found', true, $responseCode);
        break;

      case 500:
        header('HTTP/1.1 500 Internal Server Error', true, $responseCode);
        break;

      default:
        header('HTTP/1.1 ' . $responseCode, true, $responseCode);

    }

    foreach ($headers as $header)
    {
      header($header);
    }

    header('Connection: close', true);

    if ($content)
    {
      echo $content;
      flush();
    }

    exit;

  }


  private function getUniqueHeaders(array $headers, &$responseCode)
  {

    $unique = array();
    $statusHeader = '';

    foreach ($headers as $header)
    {

      $ar = explode(':', $header, 2);
      $key = trim($ar[0]);

      if (stripos($key, 'HTTP/1') === 0 ||
        stripos($key, 'status') === 0 ||
        stripos($key, 'location') === 0)
      {
        $statusHeader = $header;
      }
      else
      {
        $unique[strtolower($key)] = $header;
      }

    }

    $headers = array();

    if ($statusHeader)
    {
      $headers[] = $statusHeader;
    }

    foreach ($unique as $key => $header)
    {
      $headers[] = $header;
    }

    $responseCode = empty($statusHeader) ? 200 : 0;
    return $headers;

  }


  private function initOptions()
  {

    $this->options = array(

      'public' => false,
      'strict' => false,
      'auth' => array(),
      'xheaders' => array(),

    );

  }


  private function checkStage($stage)
  {

    ++ $this->stage;

    if (!$this->handlers)
    {
      -- $stage;
    }

    if ($this->stage !== $stage)
    {
      throw new \Exception('Function called out of sequence');
    }

  }

}
