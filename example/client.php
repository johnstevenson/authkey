<?php

  chdir(__DIR__);
  ini_set('default_charset', 'UTF-8');
  ini_set('display_errors', '1');

  # bootstrap for the example directory
  require('bootstrap.php');

  # get the url of the server script
  $url = getServerUrl();

  # get passed in proxy - only for demo (fiddler - 127.0.0.1:8888)
  $proxy = !empty($_POST['proxy']) ? $_POST['proxy'] : '';


  # set up our account details
  $account = array(
    'id' => 'client-demo',
    'key' => 'U7ZPJyFAX8Gr3Hm2DFrSQy3x1I3nLdNT2U1c+ToE5Vk=',
  );

  # create a Client instance
  $Client = new AuthKey\Transport\Client($account);

  $Client->setCurlOption(CURLOPT_PROXY, $proxy);
  //$Client->setStrictMode(true);

  # write some data to send...
  $data = array('msg' => 'Hello World');

  # ...and send it
  $result = $Client->send('POST', $url, $data);


  echo '<form method="POST">';
  echo '<p>';
  echo '<input type="submit" value="Run Example"> &nbsp;&nbsp;Last run: ' . date(DATE_RFC822);
  echo '</p><p>';
  echo 'Proxy:&nbsp;&nbsp;';
  echo "<input type='text' name='proxy' value='$proxy' />";
  echo '</p>';
  echo '</form>';
  echo '<pre>';

  echo '<b>return:</b> ';
  echo $result ? 'true' : 'false';
  echo '<br /><br />';

  echo '<b>error:</b> ' . $Client->error;
  echo '<br /><br />';

  echo '<b>output:</b> ' . $Client->output;
  echo '<br /><br />';

  echo '<hr />';
  echo '<br /><br />';
  echo '<b>Client object public properties:</b> ';
  echo '<br /><br />';
  echo getPublicProperties($Client);


function getServerUrl()
{
  $path = dirname($_SERVER['PHP_SELF']) . '/server.php';
  $scheme = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
  return $scheme . '://' . $_SERVER['HTTP_HOST'] . $path;
}


function getPublicProperties($class)
{
  $public = get_object_vars($class);
  ksort($public);
  return str_ireplace('stdClass', get_class($class), print_r((object) $public, 1));
}
