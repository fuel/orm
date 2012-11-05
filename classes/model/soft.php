<?php

namespace Orm;

/**
 * Defines a model that can be "soft" deleted. A timestamp is used to indicate
 * that the data has been deleted but the data itself is not removed from the 
 * database.
 * 
 * @author Steve "Uru" West <uruwolf@gmail.com>
 */
class Model_Soft extends Model
{
	
	protected static $_soft_delete_cached = array();
	
	/**
	 * Gets the soft delete properties.
	 * Mostly stolen from the parent class properties() function
	 * 
	 * @return array
	 */
	public static function soft_delete_properties()
	{
		$class = get_called_class();

		// If already determined
		if (array_key_exists($class, static::$_soft_delete_cached))
		{
			return static::$_soft_delete_cached[$class];
		}

		// Try to grab the properties from the class...
		if (property_exists($class, '_soft_delete'))
		{
			//Load up the info
			$properties = static::$_soft_delete;
		}
		
		// cache the properties for next usage
		static::$_soft_delete_cached[$class] = $properties;

		return static::$_soft_delete_cached[$class];
	}
	
	/**
	 * Fetches a soft delete property description array, or specific data from it.
	 * Stolen from parent class.
	 *
	 * @param   string  property or property.key
	 * @param   mixed   return value when key not present
	 * @return  mixed
	 */
	public static function soft_delete_property($key, $default = null)
	{
		$class = get_called_class();

		// If already determined
		if ( ! array_key_exists($class, static::$_soft_delete_cached))
		{
			static::soft_delete_properties();
		}
		
		return \Arr::get(static::$_soft_delete_cached[$class], $key, $default);
	}

	/**
	 * Updates the defined deleted_field with a current timestamp rather than
	 * deleting.
	 */
	public function delete($cascade = null, $use_transaction = false)
	{
		$deletedColumn = static::soft_delete_property('deleted_field', 'deleted_at');
		$mysql_timestamp = static::soft_delete_property('mysql_timestamp', true);
		
		$this->{$deletedColumn} = $mysql_timestamp ? \Date::forge()->format('mysql') : \Date::forge()->get_timestamp();
		
		$this->save();
	}
}