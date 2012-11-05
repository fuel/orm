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
	
	/**
	 * Default column name that contains the deleted timestamp
	 * @var string
	 */
	private static $_default_field_name = 'delete_at';
	/**
	 * Default value for if a mysql timestamp should be used.
	 * @var boolean
	 */
	private static $_default_mysql_timestamp = true;
	
	/**
	 * Contains cached soft delete properties.
	 * @var array
	 */
	protected static $_soft_delete_cached = array();
	
	/**
	 * Allows results to be filtered to hide soft deleted entries.
	 */
	protected static $_conditions = array(
		'where' => array(),
	);
	
	public static function _init()
	{
		
	}
	
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

		$properties = array();
		
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
	 * 
	 * @return this
	 */
	public function delete($cascade = null, $use_transaction = false)
	{
		$deletedColumn = static::soft_delete_property('deleted_field', self::$_default_field_name);
		$mysql_timestamp = static::soft_delete_property('mysql_timestamp', self::$_default_mysql_timestamp);
		
		$this->{$deletedColumn} = $mysql_timestamp ? \Date::forge()->format('mysql') : \Date::forge()->get_timestamp();
		
		$this->save();
		
		return $this;
	}
	
	public static function find($id = null, array $options = array())
	{
		//Make sure we are filtering out soft deleted items
		$deletedColumn = static::soft_delete_property('deleted_field', self::$_default_field_name);
		static::$_conditions['where'][] = array($deletedColumn, null);
		
		return parent::find($id, $options);
	}
}