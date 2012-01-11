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

abstract class Observer
{
	protected static $_instances = array();

	public static function orm_notify($instance, $event)
	{
		$model_class = get_class($instance);
		if (method_exists(static::instance($model_class), $event))
		{
			static::instance($model_class)->{$event}($instance);
		}
	}

	public static function instance($model_class)
	{
		$observer = get_called_class();
		if (empty(static::$_instances[$observer][$model_class]))
		{
			static::$_instances[$observer][$model_class] = new static($model_class);
		}

		return static::$_instances[$observer][$model_class];
	}
}