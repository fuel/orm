<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Fuel
 * @version		1.1
 * @author		Fuel Development Team
 * @license		MIT License
 * @copyright	2010 - 2011 Fuel Development Team
 * @link		http://fuelphp.com
 */

namespace Orm;

class Observer_Slug extends Observer {

	/**
	 * @var string Source property, which is used to create the slug
	 */
	public static $source = 'title';
	
	/**
	 * @var string Slug property
	 */
	public static $property = 'slug';

	/**
	 * Creates a unique slug and adds it to the object
	 *
	 * @param  Model The object
	 * @return void
	 */
	public function before_insert(Model $obj)
	{
		$class = get_class($obj);
		$slug  = \Inflector::friendly_title($obj->{static::$source}, '-', true);
		$same  = $class::find()->where(static::$property, 'like', $slug.'%')->get();

		if ( ! empty($same))
		{
			$max = 0;
			
			foreach ($same as $record)
			{
				$index = (int) preg_replace('/^[^\n]+-([0-9]+)$/', '\1', $record->{static::$property});
				$max < $index and $max = $index;
			}
			
			$slug .= '-'.($max + 1);
		}
			
		$obj->{static::$property} = $slug;
	}
}
