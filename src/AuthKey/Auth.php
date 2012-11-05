<?php
namespace AuthKey;

class Auth
{

  public $method = '';
  public $path = '';
  public $query = '';
  public $accountId = '';
  public $accountKey = '';
  public $authHeader = '';
  public $requestId = '';

  public $errorCode = '';
  public $errorMsg = '';
  public $errorResponse = 0;
  public $errorTxt = '';

  private $xheaders = array();
  private $timestamp = 0;
  private $signature = '';

  private $config = array();
  private $prefix = '';

  const DEF_NAME = 'Auth-Key';
  const DEF_SCHEME = 'MAC';
  const DEF_XNAME = 'mac';
  const DEF_INTERVAL = 600;

  const ERR_INTERNAL = 'InternalError';
  const ERR_MISSING = 'MissingSecurityHeader';
  const ERR_INVALID = 'InvalidHeader';
  const ERR_TIME = 'RequestTimeTooSkewed';
  const ERR_SIGNATURE = 'SignatureDoesNotMatch';


  public function __construct($config = null)
  {
    $this->initConfig();
    $this->setConfig($config);
  }

  /**
  * Creates signed request headers for the client
  *
  * @param array $credentials Key-value (id, key)
  * @param string $method The request method (GET, POST etc)
  * @param string $url The complete url of the resource (http:://host/path?query)
  * @param array $xheaders Key-value of x-headers
  * @return mixed Array list of request headers or false
  */
  public function forRequest(array $credentials, $method, $url, array $xheaders)
  {

    $this->init(true);

    $this->method = strtoupper($method);

    $parts = parse_url($url);

    // check we have an absolute url
    if (!isset($parts['scheme']) || !isset($parts['host']))
    {
      $msg = 'Malformed url: ' . $url;
      $this->setError(self::ERR_INTERNAL, $msg);
      return false;
    }

    $this->path = Utils::get($parts, 'path', '/');
    $this->query = Utils::get($parts, 'query', '');
    $this->requestId = $this->getRequestId();

    return $this->commonFor($credentials, $xheaders);

  }


  /**
  * Creates signed response headers for the server
  *
  * @param array $credentials Key-value (id, key)
  * @param array $xheaders Key-value of x-headers
  * @return mixed Array list of response headers or false
  */
  public function forResponse(array $credentials, array $xheaders)
  {
    $this->init(false);
    return $this->commonFor($credentials, $xheaders);
  }


  /**
  * Validates incoming request headers and sets various properties
  *
  * Checks that we have an auth header, which is parsed to retrieve the
  * client's accountId. If optional is true, then requests to public
  * resources are allowed, otherwise the function will fail without
  * this value.
  *
  * The following properties are set:
  * - method, path, query, authHeader, accountId, signature, xheaders
  *   *
  *
  * @param array $server The server request headers ($_SERVER)
  * @param boolean $optional Whether an auth header is optional
  * @return boolean
  */
  public function fromRequest(array $server, $optional = false)
  {

    $this->init(true);

    $headerName = $this->makeHttpPrefix($this->config['name']);

    if (!$this->commonFrom($server, $headerName, true, $optional))
    {
      return $optional;
    }

    $this->method = Utils::get($server, 'REQUEST_METHOD', '');
    $this->path = Utils::get($server, 'REQUEST_URI', '');
    $this->query = Utils::get($server, 'QUERY_STRING', '');

    $prefix = $this->makeHttpPrefix($this->prefix);
    $this->getXHeaders($server, $prefix);

    return true;

  }


  /**
  * Validates incoming response headers and sets various properties
  *
  * Checks that we have an auth header, which is parsed to retrieve the
  * client's accountId. If optional is true, then the client allows
  * unsigned responses, otherwise the function will fail if the
  * response is unsigned.
  *
  * The following public properties are set:
  * - authHeader, accountId, signature, xheaders
  *
  *
  * @param array $headers The response headers
  * @param boolean $optional Whether an auth header is optional
  * @return boolean
  */
  public function fromResponse(array $headers, $optional = true)
  {

    $this->init(false);

    if (!$this->commonFrom($headers, $this->config['name'], false, $optional))
    {
      return $optional;
    }

    $this->getXHeaders($headers, $this->prefix);

    return true;

  }


  /**
  * Checks a signed request is valid
  *
  * Checks required x-headers, that the timestamp is within the
  * $interval and that the signature matches
  *
  * @param array $required The required x-headers
  * @param string $accountKey
  * @param integer $interval The specific value in seconds, or the default/config value if 0
  * @returns bool
  */
  public function check(array $required, $accountKey, $interval = 0)
  {

    if (!$this->authHeader)
    {
      $msg = 'Cannot check empty auth header';
      $this->setError(self::ERR_INTERNAL, $msg);

      return false;
    }

    $res = false;

    if ($this->checkRequired($required))
    {
      $res = $this->checkTime($interval) && $this->checkSignature($accountKey);
    }

    return $res;

  }


  /**
  * Returns an x-header value if one has been sent or set, or false
  *
  * @param string $key The x-header name, either prefixed or not
  * @returns mixed The x-header value or false
  */
  public function getXHeader($key)
  {

    // check if key is prefixed
    if (stripos($key, $this->prefix) !== 0)
    {
      $key = $this->prefix . $key;
    }

    $key = strtolower($key);

    if (isset($this->xheaders[$key]))
    {
      return $this->unfold($this->xheaders[$key]);
    }

  }


  /**
  * Returns an array of x-headers, either with or without the prefix
  *
  * @param bool $prefixed Whether to include the prefix
  * @returns array Key-value x-headers
  */
  public function getAllXHeaders($prefixed = false)
  {

    $out = array();
    $len = strlen($this->prefix);

    foreach ($this->xheaders as $key => $value)
    {

      if (!$prefixed)
      {
        $key = substr($key, $len);
      }

      $out[$key] = $value;

    }

    return $out;

  }


  /**
  * Returns an json-formatted string of either a passed-in error
  * or the internal error values
  *
  * @param mixed $error Array or null
  * @returns string json-encoded error
  */
  public function getError($error = array())
  {

    $error = (array) $error;

    // if our passed error array has the required values, we use it
    if (isset($error['errorCode']) && isset($error['errorMsg']))
    {
      $errorCode = $error['errorCode'];
      $errorMsg = $error['errorMsg'];
    }
    else
    {
      $errorCode = $this->errorCode;
      $errorMsg = $this->errorMsg;
    }

    // error array may contain other values, so we unset the ones we don't need
    unset($error['errorResponse']);
    unset($error['errorCode']);
    unset($error['errorMsg']);

    // create our error...
    $ar = array(
      'code' => $errorCode ?: 'AccessDenied',
      'message' => $errorMsg ?: 'Access Denied',
    );

    // ... adding the path
    if ($this->path)
    {
      $ar['resource'] = $this->path;
    }

    // ... and the query
    if ($this->query)
    {
      $ar['query'] = $this->query;
    }

    // return merged in other values
    return json_encode(array_merge($ar, $error));

  }


  /**
  * Sets all properties (except config and prefix) to their default value.
  *
  *
  * @param bool $start Whether we are starting a new sequence
  */
  private function init($start)
  {

    $this->method = '';
    $this->path = '';
    $this->query = '';
    $this->accountId = '';
    $this->accountKey = '';
    $this->authHeader = '';
    $this->errorCode = '';
    $this->errorMsg = '';
    $this->errorResponse = 0;
    $this->xheaders = array();
    $this->timestamp = 0;
    $this->signature = '';

    if ($start)
    {
      $this->requestId = '';
    }

  }


  private function commonFor(array $credentials, array $xheaders)
  {

    if (!$this->checkCredentials($credentials))
    {
      return;
    }

    foreach ($xheaders as $key => $value)
    {
      $this->setXHeader($key, $value);
    }

    $this->timestamp = time();

    return $this->getRequestHeaders();

  }


  private function commonFrom(array $headers, $name, $server, $optional)
  {

    $headerError = $this->config['name'] . ' header';

    if (!isset($headers[$name]))
    {

      if (!$optional)
      {
        $msg = $headerError . ' is missing';
        $this->setError(self::ERR_MISSING, $msg);
      }

      return false;

    }
    else
    {
      $auth = trim($headers[$name]);
      $this->authHeader = $auth;
    }

    if (strpos($auth, $this->config['scheme'] . ' ') === 0)
    {
      $auth = ltrim(substr($auth, strlen($this->config['scheme'])));
    }
    else
    {
      $msg = $headerError . ' malformed, missing scheme: ' . $this->config['scheme'];
      $this->setError(self::ERR_INVALID, $msg);

      return false;
    }

    $count = 4;
    $parts = explode(':', $auth);

    if (count($parts) !== $count)
    {
      $msg = $headerError . ' malformed: not enough elements';
      $this->setError(self::ERR_INVALID, $msg);

      return false;
    }

    $missing = array('Timestamp', 'AccountId', 'RequestId', 'Signature');

    for ($i = 0; $i < $count; ++ $i)
    {

      if (!$parts[$i])
      {
        $msg = $headerError . ' malformed: ' . $missing[$i] . ' is missing';
        $this->setError(self::ERR_INVALID, $msg);

        return false;
      }

    }

    $this->timestamp = $parts[0];
    $this->accountId = $parts[1];

    if ($server)
    {
      $this->requestId = $parts[2];
    }

    $this->signature = $parts[3];

    return true;

  }


  private function checkCredentials(array $credentials)
  {

    $this->accountId = Utils::get($credentials, 'id');
    $this->accountKey = Utils::get($credentials, 'key');

    if (!$res = $this->accountId && $this->accountKey)
    {
      $msg = 'Account details missing.';
      $this->setError(self::ERR_INTERNAL, $msg);
    }

    return $res;

  }


  private function setXHeader($key, $value, $prefix = '')
  {

    $prefix = $prefix ?: $this->prefix;
    $pattern = '/^' . $prefix . '/i';

    $key = preg_replace($pattern, '', $key);

    if (strpos($prefix, 'HTTP') === 0)
    {
      // check if we have extra underscored parts from server conversion
      $key = strtr($key, '_', '-');
    }
    else
    {
      // make sure we only have alpha-numeric chars, and replace with a hyphen
      $key = preg_replace('/[^a-z0-9]/i', '-', $key);
    }

    $value = trim($value);

    if ($key && $value)
    {
      $cleanKey = strtolower($this->prefix . $key);
      $this->xheaders[$cleanKey] = $value;
    }

  }


  /**
  * Returns an array of headers
  * @returns array
  */
  private function getRequestHeaders()
  {

    $this->setAuthHeader();

    $out = array($this->authHeader);
    $this->addXHeaders($out, false);

    return $out;

  }


  /**
  * Writes the auth header
  *
  */
  private function setAuthHeader()
  {

    $this->signature = $this->sign();

    $str = $this->config['name'] . ': ' . $this->config['scheme'] . ' ';
    $str .= "{$this->timestamp}:{$this->accountId}:{$this->requestId}:{$this->signature}";

    $this->authHeader = $str;

  }


  /**
  * Signs the headers
  *
  * @returns string The signature
  */
  private function sign()
  {

    $strToSign = $this->getStringToSign();
    $signingKey = $this->getSigningKey($this->accountKey, $this->timestamp);
    return base64_encode(hash_hmac('sha256', $strToSign, $signingKey, true));

  }


  private function getStringToSign()
  {

    $subject = array();
    $subject[] = $this->method;
    $subject[] = $this->path;
    $subject[] = $this->query;
    $this->addXHeaders($subject, true);
    $subject[] = $this->config['scheme'];
    $subject[] = $this->timestamp;
    $subject[] = $this->requestId;

    return implode("\n", $subject);

  }


  private function getSigningKey($accountKey, $timestamp)
  {
    return hash('sha256', $accountKey . $timestamp, true);
  }


  /**
  * Adds x-headers to an existing array for output
  *
  * The x-headers key-values are concatenated.
  * $sort is set when we are signing are request, in
  * which case the header values are "unfolded" and sorted
  *
  * @param mixed $out
  * @param mixed $signing Whether to sort and format the headers for signing
  */
  private function addXHeaders(array &$out, $signing)
  {

    $formatted = array();

    foreach ($this->xheaders as $key => $value)
    {

      if ($signing)
      {
        $formatted[] = $key . ':' . $this->unfold($value);
      }
      else
      {
        $out[] = $key . ': ' . $value;
      }

    }

    if ($signing)
    {

      sort($formatted);

      foreach ($formatted as $header)
      {
        $out[] = $header;
      }

    }

  }


  /**
  * Expands long header values by removing new lines and tabs
  *
  * @param string $value The header value
  * @returns string The formatted expanded header value
  */
  private function unfold($value)
  {

    $value = preg_replace('/[\r\n\t]+/', ' ', $value);

    return preg_replace('/\s{2,}/', ' ', $value);

  }


  /**
  * Checks that required x-headers have been received
  *
  * @param array $required List of x-headers, either prefixed or not
  * @returns bool
  */
  private function checkRequired(array $required)
  {

    foreach ($required as $name)
    {

      if (!$this->getXHeader($name))
      {
        $msg = 'Required x-header is missing: ' . $name;
        $this->setError(self::ERR_MISSING, $msg);

        return false;
      }

    }

    return true;

  }


  /**
  * Checks that the header is within $interval tolerance
  *
  * @param integer $interval The interval in seconds to check
  * @returns bool
  */
  private function checkTime($interval)
  {

    $interval = $interval ?: $this->config['interval'];
    $delta = abs(time() - $this->timestamp);
    $res = $delta <= $interval;

    if (!$res)
    {
      $msg = 'Time too skewed. Host time is: ' . date(DATE_RFC822, $now);
      $this->setError(self::ERR_TIME, $msg);
    }

    return $res;

  }


  /**
  * Checks that the received signature is valid
  *
  * The headers are signed using the shared accountKey
  * and checked against the received signature
  *
  * @param string $accountKey The shared key
  * @returns bool
  */
  private function checkSignature($accountKey)
  {

    $this->accountKey = $accountKey;
    $sig = $this->sign();

    $res = $sig === $this->signature;

    if (!$res)
    {
      $msg = 'Signature does not match';
      $this->setError(self::ERR_SIGNATURE, $msg);
    }

    return $res;

  }


  /**
  * Extracts x-headers from HTTP headers
  *
  * For request headers the prefix must be HTTP_ plus formatted prefix,
  * for response headers it is just the prefix
  *
  * @param array $headers Either request or response headers
  * @param string $prefix Relevant prefix for header type
  */
  private function getXHeaders(array $headers, $prefix)
  {

    foreach ($headers as $key => $value)
    {

      if (stripos($key, $prefix) === 0)
      {
        $this->setXHeader($key, $value, $prefix);
      }

    }

  }


  /**
  * Sets errorCode, errorMsg and errorResponse values
  *
  * @param string $code The error code
  * @param string $msg The error message
  */
  private function setError($code, $msg)
  {

    $this->errorCode = $code ?: static::ERR_INTERNAL;
    $this->errorMsg = $msg;
    $this->errorTxt = $this->errorCode . ' - ' . $this->errorMsg;

    switch ($code)
    {

      case self::ERR_MISSING:
        // no break
      case self::ERR_INVALID:
        $this->errorResponse = 400;
        break;

      case self::ERR_TIME:
        // no break
      case self::ERR_SIGNATURE:
        $this->errorResponse = 403;
        break;

      default:
        $this->errorResponse = 500;

    }

  }


  /**
  * Sets the default config values
  *
  */
  private function initConfig()
  {

    $this->config = array(
      'name' => static::DEF_NAME,
      'scheme' => static::DEF_SCHEME,
      'xname' => static::DEF_XNAME,
      'interval' => static::DEF_INTERVAL,
    );

    $this->setPrefix();

  }


  private function setConfig($config)
  {

    if (!$config)
    {
      $config = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'config.json';
    }

    Utils::config($this->config, $config);

    $this->checkConfig();

  }


  /**
  * Checks prefix and interval values
  *
  */
  private function checkConfig()
  {

    $this->config['name'] = $this->config['name'] ?: static::DEF_NAME;
    $this->config['scheme'] = $this->config['scheme'] ?: static::DEF_SCHEME;
    $this->config['xname'] = $this->config['xname'] ?: static::DEF_XNAME;

    # check for valid interval value
    if (!is_integer($this->config['interval']) || $this->config['interval'] <= 0)
    {
      $this->config['interval'] = static::DEF_INTERVAL;
    }

    # check and format prefix
    $this->setPrefix();

  }


  /**
  * Sets prefix from config xname
  *
  */
  private function setPrefix()
  {

    if (!$this->config['xname'])
    {
      $this->config['xname'] = static::DEF_XNAME;
    }

    # check and format prefix, by deconstructing and reconstructing it

    # remove any initial x and hyphens
    $prefix = preg_replace('/^x(-){1,}/i', '', $this->config['xname']);

    # remove any trailing hyphen and subsequent characters
    if ($len = strpos($prefix, '-'))
    {
      $prefix = substr($prefix, 0, $len);
    }

    $prefix = $prefix ?: static::DEF_XNAME;
    $this->prefix = 'x-' . strtolower($prefix) . '-';

  }


  /**
  * Adds an HTTP_ prefix to a value, replacing hyphens with underscores
  *
  * Used when parsing HTTP_ $_SERVER values
  *
  * @param string $value
  * @returns string Uppercased formatted value
  */
  private function makeHttpPrefix($value)
  {
    return 'HTTP_' . strtoupper(strtr($value, '-', '_'));
  }


  /**
  * Creates a "unique" requestId
  *
  */
  private function getRequestId()
  {

    $serverId = '';

    if (isset($_SERVER))
    {
      $serverId = Utils::get($_SERVER, 'SERVER_ADDR');
    }

    if (!$serverId)
    {
      $serverId = mt_rand();
    }

    $str = $serverId . uniqid(mt_rand(), true);
    return base64_encode(sha1($str, true));

  }


}
