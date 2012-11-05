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
	private static $_default_field_name = 'deleted_at';

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
		if (!array_key_exists($class, static::$_soft_delete_cached))
		{
			static::soft_delete_properties();
		}

		return \Arr::get(static::$_soft_delete_cached[$class], $key, $default);
	}

	/**
	 * Do some php magic to allow static::find_deleted() to work
	 * 
	 * @param type $method
	 * @param type $args
	 */
	public static function __callStatic($method, $args)
	{
		if (strpos($method, 'find_deleted') === 0)
		{
			$tempArgs = $args;
			
			$findType = count($tempArgs) > 0 ? array_pop($tempArgs) : 'all';
			$options = count($tempArgs) > 0 ? array_pop($tempArgs) : array();
			
			return static::deleted($findType, $options);
		}

		parent::__callStatic($method, $args);
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

		if ($use_transaction)
		{
			$db = \Database_Connection::instance(static::connection());
			$db->start_transaction();
		}

		$this->observe('before_delete');
		
		$this->{$deletedColumn} = $mysql_timestamp ? \Date::forge()->format('mysql') : \Date::forge()->get_timestamp();

		$this->save();
		
		$this->observe('after_delete');

		$use_transaction and $db->commit_transaction();

		return $this;
	}
	
	/**
	 * Allows a soft deleted entry to be restored.
	 */
	public function restore()
	{
		$deletedColumn = static::soft_delete_property('deleted_field', self::$_default_field_name);
		$this->{$deletedColumn} = null;
		$this->save();

		return $this;
	}
	
	/**
	 * Alias of restore()
	 */
	public function undelete()
	{
		return $this->restore();
	}

	/**
	 * Overrides the find method to allow soft deleted items to be filtered out.
	 */
	public static function find($id = null, array $options = array())
	{
		//Make sure we are filtering out soft deleted items
		$deletedColumn = static::soft_delete_property('deleted_field', self::$_default_field_name);
		$options['where'][] = array($deletedColumn, null);

		return parent::find($id, $options);
	}

	/**
	 * Alisas of find() but selects only deleted entries rather than non-deleted
	 * ones.
	 */
	public static function deleted($id = null, array $options = array())
	{
		//Make sure we are filtering out soft deleted items
		$deletedColumn = static::soft_delete_property('deleted_field', self::$_default_field_name);
		$options['where'][] = array($deletedColumn, 'IS NOT', null);

		return parent::find($id, $options);
	}

}
