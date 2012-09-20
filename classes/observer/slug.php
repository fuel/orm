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

	protected $_source;
	protected $_property;

	public function __construct($class)
	{
		$props = $class::observers(get_class($this));
		$this->_source    = isset($props['source']) ? $props['source'] : static::$source;
		$this->_property  = isset($props['property']) ? $props['property'] : static::$property;
	}

	/**
	 * Creates a unique slug and adds it to the object
	 *
	 * @param   Model  The object
	 * @return  void
	 */
	public function before_insert(Model $obj)
	{
		// determine the slug
		$properties = (array) $this->_source;
		$source = '';
		foreach ($properties as $property)
		{
			$source .= '-'.$obj->{$property};
		}
		$slug = \Inflector::friendly_title(substr($source, 1), '-', true);

		// do we have records with this slug?
		$same = $obj->query()->where($this->_property, 'like', $slug.'%')->get();

		// make sure our slug is unique
		if ( ! empty($same))
		{
			$max = -1;

			foreach ($same as $record)
			{
				if (preg_match('/^'.$slug.'(?:-([0-9]+))?$/', $record->{$this->_property}, $matches))
				{
					$index = isset($matches[1]) ? (int) $matches[1] : 0;
					$max < $index and $max = $index;
				}
			}

			$max < 0 or $slug .= '-'.($max + 1);
		}

		$obj->{$this->_property} = $slug;
	}

	/**
	 * Creates a new unique slug and update the object
	 *
	 * @param   Model  The object
	 * @return  void
	 */
	public function before_update(Model $obj)
	{
		// determine the slug
		$properties = (array) $this->_source;
		$source = '';
		foreach ($properties as $property)
		{
			$source .= '-'.$obj->{$property};
		}
		$slug = \Inflector::friendly_title(substr($source, 1), '-', true);

		// update it if it's different from the current one
		$obj->{$this->_property} === $slug or $this->before_insert($obj);
	}
}
