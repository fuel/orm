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

class Observer_CreatedAt extends Observer
{
	/**
	 * @var  bool  set true to use mySQL timestamp instead of UNIX timestamp
	 */
	public static $mysql_timestamp = false;

	/**
	 * @var  string  property to set the timestamp on
	 */
	public static $property = 'created_at';

	protected $_mysql_timestamp;
	protected $_property;

	public function __construct($class)
	{
		$props = $class::observers(get_class($this));
		$this->_mysql_timestamp  = isset($props['mysql_timestamp']) ? $props['mysql_timestamp'] : static::$mysql_timestamp;
		$this->_property         = isset($props['property']) ? $props['property'] : static::$property;
	}

	public function before_insert(Model $obj)
	{
		$obj->{$this->_property} = $this->_mysql_timestamp ? \Date::time()->format('mysql') : \Date::time()->get_timestamp();
	}
}