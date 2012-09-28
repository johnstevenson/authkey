<?php

use \AuthKey\Auth;


class AuthConfigTest extends \AuthTests\Base
{

  /**
  * Checks that the default values are set
  *
  */
  public function testConfigDefault()
  {

    $this->config['name'] = 'Auth-Key';
    $this->config['scheme'] = 'MAC';
    $this->config['xname'] = 'mac';
    $this->config['interval'] = 600;

    $auth = new Auth();
    $this->assertAttributeEquals($this->config, 'config', $auth);

  }

  /**
  * Checks that changed config values are set
  *
  */
  public function testConfigOther()
  {

    $this->config['name'] = 'Authorization';
    $this->config['scheme'] = 'MS';
    $this->config['xname'] = 'ms';
    $this->config['interval'] = 300;

    $auth = new Auth($this->config);
    $this->assertAttributeEquals($this->config, 'config', $auth);

  }


  /**
  * Checks that supplying a single config value only updates this
  *  value and not the other empty ones in the config array
  *
  */
  public function testConfigSingle()
  {

    $this->config['xname'] = 'ms';

    $auth = new Auth($this->config);

    $this->config['name'] = 'Auth-Key';
    $this->config['scheme'] = 'MAC';
    $this->config['interval'] = 600;

    $this->assertAttributeEquals($this->config, 'config', $auth);

  }


  /**
  * Checks that config xname sets the prefix correctly
  *
  */
  public function testConfigPrefix()
  {

    $this->config['xname'] = 'ms';

    $auth = new Auth($this->config);

    $this->assertAttributeEquals('x-ms-', 'prefix', $auth);

  }


  /**
  * Checks that config xname with a full prefix is set correctly
  *
  */
  public function testConfigPrefixFull()
  {

    $this->config['xname'] = 'x-fred-';

    $auth = new Auth($this->config);

    $this->assertAttributeEquals('x-fred-', 'prefix', $auth);

  }



}

