<?php
namespace AuthTests;

use \AuthKey\Auth;
use \AuthKey\Utils;


class Base extends \PHPUnit_Framework_TestCase
{

  public $config = array();

  public function setUp()
  {

    $this->config = array(
      'name' => '',
      'scheme' => '',
      'xname' => '',
      'interval' => '',
    );

  }

}

