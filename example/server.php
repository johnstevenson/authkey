<?php

  chdir(__DIR__);
  ini_set('default_charset', 'UTF-8');

  # we don't want any PHP errors being output
  ini_set('display_errors', '0');

  # so we will log them. Exceptions will be logged as well
  ini_set('log_errors', '1');
  ini_set('error_log', 'server-errors.log');

  # bootstrap for the example directory
  require('bootstrap.php');


  $handlers = array(
    'authorize' => 'authorize',
    'process' => 'process',
  );


  $options = array(
    'public' => false,
    //'strict' => true,
  );

  $Server = new AuthKey\Transport\Server($handlers, $options);

  try
  {
    $Server->receive();
  }
  catch (Exception $e)
  {
    error_log($e);
  }


function authorize(AuthKey\Transport\Server $Server)
{

  /*
    The client's accountId is in $Server->accountId. Note that this may
    be an empty string ('') if you allow requests to public resources
    (by setting the 'public' option to true), in which case Auth-Key headers
    will have been sent.

    On success set $Server->accountKey to the client's accountKey (or
    leave empty in the case of public resources) and return true.

    On error return either an array containing the error message:

      $res = array(
        'errorResponse' => 400,
        'errorMsg' => 'resource not found',
        'errorCode' => 'InvalidRequest'
        ... plus any addition info
      );

    or null/false, which will create a default error message:

      $res = array(
        'errorResponse' => 403,
        'errorMsg' => 'The AccountId you provided does not exist in our records',
        'errorCode' => 'InvalidAccountId',
      );

  */

  $res = false;

  if ($Server->accountId === 'client-demo')
  {
    $Server->accountKey = 'U7ZPJyFAX8Gr3Hm2DFrSQy3x1I3nLdNT2U1c+ToE5Vk=';
    //$Server->setRequired('content-type');
    $res = true;
  }

  return $res;

}


function process(AuthKey\Transport\Server $Server)
{

  $Server->setXHeaderOut('content-type', 'text/html');
  $Server->reply('Reply from server [' . date(DATE_RFC822) . ']');

}

