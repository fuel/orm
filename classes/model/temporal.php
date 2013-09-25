<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.7
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2013 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Orm;

/**
 * Allows revisions of database entries to be kept when updates are made.
 *
 * @package Orm
 * @author  Fuel Development Team
 */
class Model_Temporal extends Model
{

	static $MAX_TIMESTAMP_MYSQL = '2038-01-18 22:14:08';
	static $MAX_TIMESTAMP_UNIX = '2147483647';

	/**
	 * Compound primary key that includes the start and end times is required
	 */
	protected static $_primary_key = array('id', 'temporal_start', 'temporal_end');

	/**
	 * Override to change default temporal paramaters
	 */
	protected static $_temporal = array();

	protected static $_temporal_default = array(
		'start_column' => 'temporal_start',
		'end_column' => 'temporal_end',
		'mysql_timestamp' => false,
	);

	/**
	 * Contains cached temporal properties.
	 */
	protected static $_temporal_cached = array();

	/**
	 * Gets the temporal properties.
	 * Mostly stolen from the parent class properties() function
	 *
	 * @return array
	 */
	public static function temporal_properties()
	{
		$class = get_called_class();

		// If already determined
		if (array_key_exists($class, static::$_temporal_cached))
		{
			return static::$_temporal_cached[$class];
		}

		$properties = array();

		// Try to grab the properties from the class...
		if (property_exists($class, '_temporal'))
		{
			// Load up the info
			$properties = static::$_temporal + static::$_temporal_default;

			// Ensure the correct max timestamp is set
			$properties['max_timestamp'] = ($properties['mysql_timestamp']) ?
				static::$MAX_TIMESTAMP_MYSQL :
				static::$MAX_TIMESTAMP_UNIX;
		}

		// cache the properties for next usage
		static::$_temporal_cached[$class] = $properties;

		return static::$_temporal_cached[$class];
	}

	public static function get_temporal_property($name)
	{
		return \Arr::get(static::temporal_properties(), $name);
	}

	public static function query($options = array())
	{
		$query = Query_Temporal::forge(
			get_called_class(),
			array(
				static::connection(),
				static::connection(true)
			),
			$options
		);

		$start_timestamp = static::get_temporal_property('start_column');
		$end_timestamp = static::get_temporal_property('start_column');

		$query->set_temporal_properties(null, $end_timestamp, $start_timestamp);

		return $query;
	}
}
