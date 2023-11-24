<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.9-dev
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

namespace Orm;

/**
 * Invalid content exception, thrown when type conversion is not possible.
 */
class InvalidContentType extends \UnexpectedValueException {}

/**
 * Typing observer.
 *
 * Runs on load or save, and ensures the correct data type of your ORM object properties.
 */
class Observer_Typing
{
	/**
	 * @var  array  types of events to act on and whether they are pre- or post-database
	 */
	public static $events = array(
		'before_save'  => 'before',
		'after_save'   => 'after',
		'after_load'   => 'after',
	);

	/**
	 * @var  array  db type mappings
	 */
	public static $type_mappings = array(
		'tinyint' => 'int',
		'smallint' => 'int',
		'mediumint' => 'int',
		'bigint' => 'int',
		'integer' => 'int',
		'double' => 'float',
		'decimal' => 'float',
		'tinytext' => 'text',
		'mediumtext' => 'text',
		'longtext' => 'text',
		'boolean' => 'bool',
		'time_unix' => 'time',
		'time_mysql' => 'time',
		'datetime' => 'time',
		'date' => 'time',
	);

	/**
	 * @var  array  db data types with the method(s) to use, optionally pre- or post-database
	 */
	public static $type_methods = array(
		'varchar' => array(
			'before' => 'Orm\\Observer_Typing::type_string',
		),
		'int' => array(
			'before' => 'Orm\\Observer_Typing::type_integer',
			'after' => 'Orm\\Observer_Typing::type_integer',
		),
		'float' => array(
			'before' => 'Orm\\Observer_Typing::type_float_before',
			'after' => 'Orm\\Observer_Typing::type_float_after',
		),
		'text' => array(
			'before' => 'Orm\\Observer_Typing::type_string',
		),
		'set' => array(
			'before' => 'Orm\\Observer_Typing::type_set_before',
			'after' => 'Orm\\Observer_Typing::type_set_after',
		),
		'enum' => array(
			'before' => 'Orm\\Observer_Typing::type_set_before',
		),
		'bool' => array(
			'before' => 'Orm\\Observer_Typing::type_bool_to_int',
			'after'  => 'Orm\\Observer_Typing::type_bool_from_int',
		),
		'serialize' => array(
			'before' => 'Orm\\Observer_Typing::type_serialize',
			'after'  => 'Orm\\Observer_Typing::type_unserialize',
		),
		'encrypt' => array(
			'before' => 'Orm\\Observer_Typing::type_encrypt',
			'after'  => 'Orm\\Observer_Typing::type_decrypt',
		),
		'json' => array(
			'before' => 'Orm\\Observer_Typing::type_json_encode',
			'after'  => 'Orm\\Observer_Typing::type_json_decode',
		),
		'time' => array(
			'before' => 'Orm\\Observer_Typing::type_time_encode',
			'after'  => 'Orm\\Observer_Typing::type_time_decode',
		),
	);

	/**
	 * @var  array  regexes for db types with the method(s) to use, optionally pre- or post-database
	 */
	public static $regex_methods = array(
		'/^decimal:([0-9])/uiD' => array(
			'before' => 'Orm\\Observer_Typing::type_decimal_before',
			'after' => 'Orm\\Observer_Typing::type_decimal_after',
		),
	);

	/**
	 */
	public static $use_locale = true;

	/**
	 * Make sure the orm config is loaded
	 */
	public static function _init()
	{
		\Config::load('orm', true);

		static::$use_locale = \Config::get('orm.use_locale', static::$use_locale);
	}

	/**
	 * Get notified of an event
	 *
	 * @param  Model   $instance
	 * @param  string  $event
	 */
	public static function orm_notify(Model $instance, $event)
	{
		// if we don't serve this event, bail out immediately
		if (array_key_exists($event, static::$events))
		{
			// get the event type of the event that triggered us
			$event_type = static::$events[$event];

			// fetch the model's properties
			$properties = $instance->properties();

			// and check if we need to do any datatype conversions
			foreach ($properties as $p => $settings)
			{
				// the property is part of the primary key, skip it
				if (in_array($p, $instance->primary_key()))
				{
					continue;
				}

				$instance->{$p} = static::typecast($p, $instance->{$p}, $settings, $event_type);
			}
		}
	}

	/**
	 * Typecast a single column value based on the model properties for that column
	 *
	 * @param  string  $column	name of the column
	 * @param  string  $value	value
	 * @param  string  $settings	column settings from the model
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  mixed
	 */
	public static function typecast($column, $value, $settings, $event_type	= 'before')
	{
		 // only on before_save, check if null is allowed
		if ($value === null)
		{
			 // only on before_save
			if ($event_type == 'before')
			{
				if (array_key_exists('null', $settings) and $settings['null'] === false)
				{
					// if a default is defined, use that instead
					if (array_key_exists('default', $settings))
					{
						$value = $settings['default'];
					}
					else
					{
						throw new InvalidContentType('The property "'.$column.'" cannot be NULL.');
					}
				}
			}
		}

		 // still null? then let the DB deal with it
		if ($value === null)
		{
			return $value;
		}

		// no datatype given
		if (empty($settings['data_type']))
		{
			return $value;
		}

		// get the data type for this column
		$data_type = $settings['data_type'];

		// is this a base data type?
		if ( ! isset(static::$type_methods[$data_type]))
		{
			// no, can we map it to one?
			if (isset(static::$type_mappings[$data_type]))
			{
				// yes, so swap it for a base data type
				$data_type = static::$type_mappings[$data_type];
			}
			else
			{
				// can't be mapped, check the regexes
				foreach (static::$regex_methods as $match => $methods)
				{
					// fetch the method
					$method = ! empty($methods[$event_type]) ? $methods[$event_type] : false;

					if ($method)
					{
						if (preg_match_all($match, $data_type, $matches) > 0)
						{
							$value = call_user_func($method, $value, $settings, $matches);
						}
					}
				}
				return $value;
			}
		}

		// fetch the method
		$method = ! empty(static::$type_methods[$data_type][$event_type]) ? static::$type_methods[$data_type][$event_type] : false;

		// if one was found, call it
		if ($method)
		{
			$value = call_user_func($method, $value, $settings);
		}

		return $value;
	}

	/**
	 * Casts to string when necessary and checks if within max length
	 *
	 * @param   mixed  value to typecast
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  string
	 */
	public static function type_string($var, array $settings)
	{
		if (is_array($var) or (is_object($var) and ! method_exists($var, '__toString')))
		{
			throw new InvalidContentType('Array or object could not be converted to varchar.');
		}

		$var = strval($var);

		if (array_key_exists('character_maximum_length', $settings))
		{
			$length  = intval($settings['character_maximum_length']);
			if ($length > 0 and mb_strlen($var) > $length)
			{
				$var = mb_substr($var, 0, $length);
			}
		}

		return $var;
	}

	/**
	 * Casts to int when necessary and checks if within max values
	 *
	 * @param   mixed  value to typecast
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  int
	 */
	public static function type_integer($var, array $settings)
	{
		if (is_array($var) or is_object($var))
		{
			throw new InvalidContentType('Array or object could not be converted to integer.');
		}

		if ((array_key_exists('min', $settings) and $var < intval($settings['min']))
			or (array_key_exists('max', $settings) and $var > intval($settings['max'])))
		{
			throw new InvalidContentType('Integer value outside of range: '.$var);
		}

		return intval($var);
	}

	/**
	 * Casts float to string when necessary
	 *
	 * @param   mixed  value to typecast
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  float
	 */
	public static function type_float_before($var, $settings = null)
	{
		if (is_array($var) or is_object($var))
		{
			throw new InvalidContentType('Array or object could not be converted to float.');
		}

		// do we need to do locale conversion?
		if (is_string($var) and static::$use_locale)
		{
			$locale_info = localeconv();
			$var = str_replace($locale_info["thousands_sep"], "", $var);
			$var = str_replace($locale_info["decimal_point"], ".", $var);
		}

		// was a specific float format specified?
		if (isset($settings['db_decimals']))
		{
			return sprintf('%.'.$settings['db_decimals'].'F', round((float) $var, $settings['db_decimals']));
		}
		if (isset($settings['data_type']) and strpos($settings['data_type'], 'decimal:') === 0)
		{
			$decimal = explode(':', $settings['data_type']);
			return sprintf('%.'.$decimal[1].'F', round((float) $var, $decimal[1]));
		}

		return $var;
	}

	/**
	 * Casts to float when necessary
	 *
	 * @param   mixed  value to typecast
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  float
	 */
	public static function type_float_after($var)
	{
		if (is_array($var) or is_object($var))
		{
			throw new InvalidContentType('Array or object could not be converted to float.');
		}

		return floatval($var);
	}

	/**
	 * Decimal pre-treater, converts a decimal representation to a float
	 *
	 * @param   mixed  value to typecast
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  float
	 */
	public static function type_decimal_before($var, $settings = null)
	{
		if (is_array($var) or is_object($var))
		{
			throw new InvalidContentType('Array or object could not be converted to decimal.');
		}

		return static::type_float_before($var, $settings);
	}

	/**
	 * Decimal post-treater, converts any number to a decimal representation
	 *
	 * @param   mixed  value to typecast
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  float
	 */
	public static function type_decimal_after($var, array $settings, array $matches)
	{
		if (is_array($var) or is_object($var))
		{
			throw new InvalidContentType('Array or object could not be converted to decimal.');
		}

		if ( ! is_numeric($var))
		{
			throw new InvalidContentType('Value '.$var.' is not numeric and can not be converted to decimal.');
		}

		$dec = empty($matches[1][0]) ? 2 : $matches[1][0];

		// do we need to do locale aware conversion?
		if (static::$use_locale)
		{
			return sprintf("%.".$dec."f", round(static::type_float_after($var), $dec));
		}

		return sprintf("%.".$dec."F", round(static::type_float_after($var), $dec));
	}

	/**
	 * Value pre-treater, deals with array values, and handles the enum type
	 *
	 * @param   mixed  value
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  string
	 */
	public static function type_set_before($var, array $settings)
	{
		$var    = is_array($var) ? implode(',', $var) : strval($var);
		$values = array_filter(explode(',', trim($var)));

		if ($settings['data_type'] == 'enum' and count($values) > 1)
		{
			throw new InvalidContentType('Enum cannot have more than 1 value.');
		}

		foreach ($values as $val)
		{
			if ( ! in_array($val, $settings['options']))
			{
				throw new InvalidContentType('Invalid value given for '.ucfirst($settings['data_type']).
					', value "'.$var.'" not in available options: "'.implode(', ', $settings['options']).'".');
			}
		}

		return $var;
	}

	/**
	 * Value post-treater, converts a comma-delimited string into an array
	 *
	 * @param   mixed  value
	 *
	 * @return  array
	 */
	public static function type_set_after($var)
	{
		return explode(',', $var);
	}

	/**
	 * Converts boolean input to 1 or 0 for the DB
	 *
	 * @param   bool  value
	 *
	 * @return  int
	 */
	public static function type_bool_to_int($var)
	{
		return $var ? 1 : 0;
	}

	/**
	 * Converts DB bool values to PHP bool value
	 *
	 * @param   bool  value
	 *
	 * @return  int
	 */
	public static function type_bool_from_int($var)
	{
		return $var == '1' ? true : false;
	}

	/**
	 * Returns the serialized input
	 *
	 * @param   mixed  value
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  string
	 */
	public static function type_serialize($var, array $settings)
	{
		$var = serialize($var);

		if (array_key_exists('character_maximum_length', $settings))
		{
			$length  = intval($settings['character_maximum_length']);
			if ($length > 0 and strlen($var) > $length)
			{
				throw new InvalidContentType('Value could not be serialized, result exceeds max string length for field.');
			}
		}

		return $var;
	}

	/**
	 * Unserializes the input
	 *
	 * @param   string  value
	 *
	 * @return  mixed
	 */
	public static function type_unserialize($var)
	{
		return empty($var) ? array() : unserialize($var);
	}

	/**
	 * Returns the encrypted input
	 *
	 * @param   mixed  value
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  string
	 */
	public static function type_encrypt($var, array $settings)
	{
		// make the variable serialized, we need to be able to encrypt any variable type
		$var = static::type_serialize($var, $settings);

		// and encrypt it
		if (array_key_exists('encryption_key', $settings))
		{
			$var = \Crypt::encode($var, $settings['encryption_key']);
		}
		else
		{
			$var = \Crypt::encode($var);
		}

		// do a length check if needed
		if (array_key_exists('character_maximum_length', $settings))
		{
			$length  = intval($settings['character_maximum_length']);
			if ($length > 0 and strlen($var) > $length)
			{
				throw new InvalidContentType('Value could not be encrypted, result exceeds max string length for field.');
			}
		}

		return $var;
	}

	/**
	 * decrypt the input
	 *
	 * @param   string  value
	 *
	 * @return  mixed
	 */
	public static function type_decrypt($var)
	{
		// decrypt it
		if (array_key_exists('encryption_key', $settings))
		{
			$var = \Crypt::decode($var, $settings['encryption_key']);
		}
		else
		{
			$var = \Crypt::decode($var);
		}

		return $var;
	}

	/**
	 * JSON encodes the input
	 *
	 * @param   mixed  value
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  string
	 */
	public static function type_json_encode($var, array $settings)
	{
		$var = json_encode($var);

		if (array_key_exists('character_maximum_length', $settings))
		{
			$length  = intval($settings['character_maximum_length']);
			if ($length > 0 and strlen($var) > $length)
			{
				throw new InvalidContentType('Value could not be JSON encoded, exceeds max string length for field.');
			}
		}

		return $var;
	}

	/**
	 * Decodes the JSON
	 *
	 * @param   string  value
	 *
	 * @return  mixed
	 */
	public static function type_json_decode($var, $settings)
	{
		$assoc = false;
		if (array_key_exists('json_assoc', $settings))
		{
			$assoc = (bool) $settings['json_assoc'];
		}
		return json_decode($var, $assoc);
	}

	/**
	 * Takes a Date instance and transforms it into a DB timestamp
	 *
	 * @param   \Fuel\Core\Date  value
	 * @param   array  any options to be passed
	 *
	 * @throws  InvalidContentType
	 *
	 * @return  int|string
	 */
	public static function type_time_encode(\Fuel\Core\Date $var, array $settings)
	{
		if ( ! $var instanceof \Fuel\Core\Date)
		{
			throw new InvalidContentType('Value must be an instance of the Date class.');
		}

		// deal with datetime values
		elseif ($settings['data_type'] == 'datetime')
		{
			return $var->format('%Y-%m-%d %H:%M:%S');
		}

		// deal with date values
		elseif ($settings['data_type'] == 'date')
		{
			return $var->format('%Y-%m-%d');
		}

		// deal with time values
		elseif ($settings['data_type'] == 'time')
		{
			return $var->format('%H:%M:%S');
		}

		// deal with config defined timestamps
		elseif ($settings['data_type'] == 'time_mysql')
		{
			return $var->format('mysql');
		}

		// assume a timestamo is required
		return $var->get_timestamp();
	}

	/**
	 * Takes a DB timestamp and converts it into a Date object
	 *
	 * @param   string  value
	 * @param   array  any options to be passed
	 *
	 * @return  \Fuel\Core\Date
	 */
	public static function type_time_decode($var, array $settings)
	{
		// deal with a 'nulled' date, which according to some RDMBS is a valid enough to store?
		if ($var == '0000-00-00 00:00:00')
		{
			if (array_key_exists('null', $settings) and $settings['null'] === false)
			{
				throw new InvalidContentType('Value '.$var.' is not a valid date and can not be converted to a Date object.');
			}
			return null;
		}

		// deal with datetime values
		elseif ($settings['data_type'] == 'datetime')
		{
			try
			{
				$var = \Date::create_from_string($var, '%Y-%m-%d %H:%M:%S');
			}
			catch (\UnexpectedValueException $e)
			{
				throw new InvalidContentType('Value '.$var.' is not a valid datetime and can not be converted to a Date object.');
			}
		}

		// deal with date values
		elseif ($settings['data_type'] == 'date')
		{
			try
			{
				$var = \Date::create_from_string($var, '%Y-%m-%d');
			}
			catch (\UnexpectedValueException $e)
			{
				throw new InvalidContentType('Value '.$var.' is not a valid date and can not be converted to a Date object.');
			}
		}

		// deal with time values
		elseif ($settings['data_type'] == 'time')
		{
			try
			{
				$var = \Date::create_from_string($var, '%H:%M:%S');
			}
			catch (\UnexpectedValueException $e)
			{
				throw new InvalidContentType('Value '.$var.' is not a valid time and can not be converted to a Date object.');
			}
		}

		// deal with a configured datetime value
		elseif ($settings['data_type'] == 'time_mysql')
		{
			try
			{
				$var = \Date::create_from_string($var, 'mysql');
			}
			catch (\UnexpectedValueException $e)
			{
				throw new InvalidContentType('Value '.$var.' is not a valid mysql datetime and can not be converted to a Date object.');
			}
		}

		// else assume it is a numeric timestamp
		else
		{
			$var = \Date::forge($var);
		}

		return $var;
	}
}
