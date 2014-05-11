<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.7
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2014 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Orm;

/**
 * Observer class to generate SEO friendly slugs from a model property (usually something like a title)
 */
class Observer_Slug extends Observer
{
	/**
	 * @var  mixed  Default source property or array of properties, which is/are used to create the slug
	 */
	public static $source = 'title';

	/**
	 * @var  string  Default slug property
	 */
	public static $property = 'slug';

	/**
	 * @var  string  Default separator
	 */
	public static $separator = '-';

	/**
	* @var  bool  Required to be unique
	*/
	public static $unique = true;

	/**
	* @var  bool  Required to be overwritten
	*/
	public static $overwrite = true;

	/**
	 * @var  mixed  Source property or array of properties, which is/are used to create the slug
	 */
	protected $_source;

	/**
	 * @var  string  Slug property
	 */
	protected $_property;

	/**
	 * @var  string  Slug separator
	 */
	protected $_separator;

	/**
	* @var  bool  If the slug is required to be unique
	*/
	protected $_unique;

	/**
	* @var  bool  If the slug can be manually assigned
	*/
	protected $_overwrite;

	/**
	 * Set the properties for this observer instance, based on the parent model's
	 * configuration or the defined defaults.
	 *
	 * @param  string  Model class this observer is called on
	 */
	public function __construct($class)
	{
		$props = $class::observers(get_class($this));
		$this->_source    = isset($props['source']) ? $props['source'] : static::$source;
		$this->_property  = isset($props['property']) ? $props['property'] : static::$property;
		$this->_separator = isset($props['separator']) ? $props['separator'] : static::$separator;
		$this->_unique    = isset($props['unique']) ? (bool) $props['unique'] : static::$unique;
		$this->_overwrite = isset($props['overwrite']) ? (bool) $props['overwrite'] : static::$overwrite;
	}

	/**
	 * Creates a slug (unique by default) and adds it to the object
	 *
	 * @param  Model  Model object subject of this observer method
	 */
	public function before_insert(Model $obj)
	{
		// slug should be overwritten if it is enabled to be or there is no manually assigned value
		$overwrite = $this->_overwrite === true || empty($obj->{$this->_property});
		$slug = $obj->{$this->_property};

		// query to check for existence of this slug
		$query = $obj->query();

		// only determine the slug if it should be overwritten
		// fill the query with appropriate where condition
		if ($overwrite === true)
		{
			$properties = (array) $this->_source;
			$source = '';
			foreach ($properties as $property)
			{
				$source .= $this->_separator.$obj->{$property};
			}
			$slug = \Inflector::friendly_title(substr($source, 1), $this->_separator, true);

			$query->where($this->_property, 'like', $slug.'%');
		}
		else
		{
			$query->where($this->_property, $slug);
		}


		if($this->_unique === true)
		{
			// query to check for existence of this slug
			$query = $obj->query()->where($this->_property, 'like', $slug.'%');

			// is this a temporal model?
			if ($obj instanceOf Model_Temporal)
			{
				// add a filter to only check current revisions excluding the current object
				$class = get_class($obj);
				$query->where($class::temporal_property('end_column'), '=', $class::temporal_property('max_timestamp'));
				foreach($class::getNonTimestampPks() as $key)
				{
					$query->where($key, '!=', $obj->{$key});
				}
			}

			// do we have records with this slug?
			$same = $query->get();

			// make sure our slug is unique
			if ( ! empty($same))
			{
				if ($overwrite === false)
				{
					throw new \FuelException('Slug ' . $slug . ' already exists.');
				}

				$max = -1;

				foreach ($same as $record)
				{
					if (preg_match('/^'.$slug.'(?:-([0-9]+))?$/', $record->{$this->_property}, $matches))
					{
						$index = isset($matches[1]) ? (int) $matches[1] : 0;
						$max < $index and $max = $index;
					}
				}

				$max < 0 or $slug .= $this->_separator.($max + 1);
			}
		}

		$obj->{$this->_property} = $slug;
	}

	/**
	 * Creates a new slug (unique by default) and update the object
	 *
	 * @param  Model  Model object subject of this observer method
	 */
	public function before_update(Model $obj)
	{
		// determine the slug
		$properties = (array) $this->_source;
		$source = '';
		foreach ($properties as $property)
		{
			$source .= $this->_separator.$obj->{$property};
		}
		$slug = \Inflector::friendly_title(substr($source, 1), $this->_separator, true);

		// update it if it's different from the current one
		// and is not manually assigned
		if ($obj->{$this->_property} !== $slug)
		{
			$overwrite = $this->_overwrite;

			if ($overwrite === false and ! $obj->is_changed($this->_property))
			{
				$this->_overwrite = true;
			}

			$this->before_insert($obj);

			$this->_overwrite = $overwrite;
		}
	}
}
