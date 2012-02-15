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

class Observer_Self
{
	public static function orm_notify(Model $instance, $event)
	{
		if (method_exists($instance, $method = '_event_'.$event))
		{
			call_user_func(array($instance, $method));
		}
	}
}