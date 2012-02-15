<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Fuel
 * @version		1.0
 * @author		Fuel Development Team
 * @license		MIT License
 * @copyright	2010 - 2012 Fuel Development Team
 * @link		http://fuelphp.com
 */

namespace Orm;

class Observer_UpdatedAt extends Observer
{
	/**
	 * @var  bool  set true to use mySQL timestamp instead of UNIX timestamp
	 */
	public static $mysql_timestamp = false;

	/**
	 * @var  string  property to set the timestamp on
	 */
	public static $property = 'updated_at';

	protected $_mysql_timestamp;
	protected $_property;

	public function __construct($class)
	{
		$props = $class::observers(get_class($this));
		$this->_mysql_timestamp  = isset($props['mysql_timestamp']) ? $props['mysql_timestamp'] : static::$mysql_timestamp;
		$this->_property         = isset($props['property']) ? $props['property'] : static::$property;
	}

	public function before_save(Model $obj)
	{
		if ($obj->is_new() or $obj->is_changed())
		{
			$obj->{$this->_property} = $this->_mysql_timestamp ? \Date::time()->format('mysql') : \Date::time()->get_timestamp();
		}
	}
}