<?php
namespace AuthKey;

class Utils
{

  const SEP_CSV = ',';

  /**
  * Sets the value of multiple options in $storage
  *
  * @param array $storage The storage container
  * @param array $options Key-value options
  */
  public static function setAllOptions(array &$storage, array $options)
  {

    foreach ($options as $key => $value)
    {

      if (is_scalar($value))
      {
        static::setOption($storage, '', $key, $value);
      }
      elseif (is_array($value))
      {

        foreach ($value as $option => $val)
        {
          static::setOption($storage, $key, $option, $val);
        }

      }

    }

  }


  /**
  * Sets the value of multiple options in $storage
  *
  * @param array $storage The storage container
  * @param mixed $options String, denoting json config file, or Array of options
  */
  public static function config(array &$storage, $options)
  {

    if (is_string($options) && $options)
    {

      if (file_exists($options))
      {
        $json = file_get_contents($options);
        $options = @json_decode($json, true);
      }

    }

    if (is_array($options))
    {
      static::setAllOptions($storage, $options);
    }

  }


  /**
  * Sets the value of a single option in $storage
  *
  * @param array $storage The storage container
  * @param string $group The option array group, or ''
  * @param string $key The option key
  * @param mixed $value The option value. If null in a group, unsets the key
  */
  public static function setOption(array &$storage, $group, $key, $value = null)
  {

    if (isset($storage[$group]) && is_array($storage[$group]))
    {

      if (!$value)
      {

        if (isset($storage[$group][$key]))
        {
          unset($storage[$group][$key]);
        }

      }
      elseif (is_scalar($value))
      {
        $storage[$group][$key] = (string) $value;
      }

    }
    elseif (isset($storage[$key]) && is_scalar($storage[$key]))
    {
      $storage[$key] = $value;
    }

  }


  /**
  * Sets the name and value of an x-header in $storage.
  *
  * @param array $storage The storage container
  * @param mixed $name The unprefixed name of the x-header
  * @param mixed $value The value of the x-header, or null to unset it
  */
  public static function setXHeader(array &$storage, $name, $value)
  {
    static::setOption($storage, 'xheaders', strtolower($name), $value);
  }


  /**
  * Adds a new value, stored as a comma-separated string
  *
  * @param string $value The existing value
  * @param string $new The new value to add
  * @returns string The comma-separated value
  */
  public static function addCsv($value, $new)
  {

    if ($value)
    {
      $value .= static::SEP_CSV;
    }

    return $value . $new;

  }

  /**
  * Returns a property value from an array or object
  *
  * @param mixed $container Either an array or an object
  * @param mixed $key The property name
  * @param mixed $default The default value to use if key does not exist
  * @returns mixed The property, or default, value
  */
  public static function get($container, $key, $default = null)
  {

    if (is_array($container))
    {
      $value = isset($container[$key]) ? $container[$key] : $default;
    }
    elseif (is_object($container))
    {
      $value = isset($container->$key) ? $container->$key : $default;
    }
    else
    {
      $value = $default;
    }

    return $value;

  }


  /**
  * Calls a function stored in $storage
  *
  * @param array $storage The handler container
  * @param string $name The handler name
  * @param array $args The arguments to be passed in the function
  */
  public static function callHandler($storage, $name, array $args)
  {

    if (!empty($storage[$name]) && is_callable($storage[$name]))
    {
      return call_user_func_array($storage[$name], $args);
    }
    else
    {
      throw new \Exception('Handler invalid: ' . $name);
    }

  }

}


