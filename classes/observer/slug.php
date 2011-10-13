<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.1
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Orm;

class Observer_Slug extends Observer
{

	/**
	 * @var  mixed  Source property or array of properties, which is/are used to create the slug
	 */
	public static $source = 'title';
	
	/**
	 * @var  string  Slug property
	 */
	public static $property = 'slug';

	/**
	 * Creates a unique slug and adds it to the object
	 *
	 * @param   Model  The object
	 * @return  void
	 */
	public function before_insert(Model $obj)
	{
		$properties = (array) static::$source;
		$source;
		
		foreach ($properties as $property)
		{
			$source .= '-'.$obj->{$property};
		}
	
		$slug  = \Inflector::friendly_title(substr($source, 1), '-', true);
		$same  = $obj->find()->where(static::$property, 'like', $slug.'%')->get();

		if ( ! empty($same))
		{
			$max = -1;
			
			foreach ($same as $record)
			{
				if (preg_match('/^'.$slug.'(?:-([0-9]+))?$/', $record->{static::$property}, $matches))
				{
				     $index = (int) $matches[1];
				     $max < $index and $max = $index;
				}
			}
			
			$max < 0 or $slug .= '-'.($max + 1);
		}
			
		$obj->{static::$property} = $slug;
	}
}
