<?php

namespace Orm;

/**
 * Allows revisions of database entries to be kept when updates are made.
 * 
 * @author Steve "Uru" West <uruwolf@gmail.com>
 */
class Model_Temporal extends Model
{

	protected static $_temporal_cached = array();
	private static $_default_mysql_timestamp = true;
	private static $_default_timestamp_field = 'temporal';
	private static $_pk_check_disabled = array();

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
			//Load up the info
			$properties = static::$_temporal;
		}

		// cache the properties for next usage
		static::$_temporal_cached[$class] = $properties;

		return static::$_temporal_cached[$class];
	}

	/**
	 * Fetches a soft delete property description array, or specific data from it.
	 * Stolen from parent class.
	 *
	 * @param   string  property or property.key
	 * @param   mixed   return value when key not present
	 * @return  mixed
	 */
	public static function temporal_property($key, $default = null)
	{
		$class = get_called_class();

		// If already determined
		if (!array_key_exists($class, static::$_temporal_cached))
		{
			static::temporal_properties();
		}

		return \Arr::get(static::$_temporal_cached[$class], $key, $default);
	}

	/**
	 * Finds a specific revision for the given ID. If a timestamp is specified 
	 * the revision returned will reflect the entity's state at that given time.
	 * 
	 * @param type $id
	 * @param int $timestamp
	 * @return type
	 */
	public static function find_revision($id, $timestamp = null)
	{
		if ($timestamp == null)
			$timestamp = 0;

		return static::find(array($id, $timestamp));
	}

	/**
	 * Overrides the save method to allow temporal models to be 
	 * @param type $cascade
	 * @param type $use_transaction
	 * @return type
	 */
	public function save($cascade = null, $use_transaction = false)
	{
		//Load temporal properties.
		$timestamp_field = static::temporal_property('timestamp_name', self::$_default_timestamp_field);
		$mysql_timestamp = static::temporal_property('mysql_timestamp', self::$_default_mysql_timestamp);

		//If this is new then just call the parent and let everything happen as normal
		if ($this->is_new())
		{
			return parent::save($cascade, $use_transaction);
		}
		//If this is an update then set a new PK, save and then insert a new row
		else
		{
			$diff = $this->get_diff();

			if (count($diff[0]) > 0)
			{
				//Take a copy before resetting
				$newModel = clone $this;

				//Reset the current model and update the timestamp
				$this->reset();

				self::disable_primary_key_check();
				$this->{$timestamp_field} = $mysql_timestamp ? \Date::forge()->format('mysql') : \Date::forge()->get_timestamp();
				self::enable_primary_key_check();

				parent::save();

				//Construct a copy of this model and save that with a 0 timestamp
				$newModel->id = $this->id; //TODO: unhardcode this
				$newModel->{$timestamp_field} = 0;

				return $newModel->save();
			}
		}
	}

	/**
	 * Overrides the parent primary_key method to allow primaray key enforcement
	 * to be turned off when updating a temporal model.
	 */
	public static function primary_key()
	{
		$class = get_called_class();

		if (static::get_primary_key_status())
		{
			return static::$_primary_key;
		}

		return array();
	}

	/**
	 * Disables PK checking
	 */
	private static function disable_primary_key_check()
	{
		$class = get_called_class();
		self::$_pk_check_disabled[$class] = false;
	}

	/**
	 * Enables PK checking
	 */
	private static function enable_primary_key_check()
	{
		$class = get_called_class();
		self::$_pk_check_disabled[$class] = true;
	}

	/**
	 * Returns true if the PK checking should be performed. Defaults to true
	 */
	private static function get_primary_key_status()
	{
		$class = get_called_class();
		return \Arr::get(self::$_pk_check_disabled, $class, true);
	}

}