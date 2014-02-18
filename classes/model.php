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
 * Record Not Found Exception
 */
class RecordNotFound extends \OutOfBoundsException {}

/**
 * Frozen Object Exception
 */
class FrozenObject extends \RuntimeException {}

class Model implements \ArrayAccess, \Iterator, \Sanitization
{
	/* ---------------------------------------------------------------------------
	 * Static usage
	 * --------------------------------------------------------------------------- */

	/**
	 * @var  string  connection to use
	 */
	// protected static $_connection = null;

	/**
	 * @var  string  write connection to use
	 */
	// protected static $_write_connection = null;

	/**
	 * @var  string  table name to overwrite assumption
	 */
	// protected static $_table_name;

	/**
	 * @var  array  array of object properties
	 */
	// protected static $_properties;

	/**
	 * @var  array  array of views with additional properties
	 */
	// protected static $_views;

	/**
	 * @var  array  array of observer classes to use
	 */
	// protected static $_observers;

	/**
	 * @var  array  relationship properties
	 */
	// protected static $_has_one;
	// protected static $_belongs_to;
	// protected static $_has_many;
	// protected static $_many_many;
	// protected static $_eav;

	/**
	 * @var  array  name or names of the primary keys
	 */
	protected static $_primary_key = array('id');

	/**
	 * @var  array  name or columns that need to be excluded from any to_array() result
	 */
	protected static $_to_array_exclude = array();

	/**
	 * @var  array  cached tables
	 */
	protected static $_table_names_cached = array();

	/**
	 * @var  array  cached properties
	 */
	protected static $_properties_cached = array();

	/**
	 * @var  array  cached properties
	 */
	protected static $_views_cached = array();

	/**
	 * @var  string  relationships
	 */
	protected static $_relations_cached = array();

	/**
	 * @var  array  cached observers
	 */
	protected static $_observers_cached = array();

	/**
	 * @var  array  array of fetched objects
	 */
	protected static $_cached_objects = array();

	/**
	 * @var  array  array of valid relation types
	 */
	protected static $_valid_relations = array(
		'belongs_to'    => 'Orm\\BelongsTo',
		'has_one'       => 'Orm\\HasOne',
		'has_many'      => 'Orm\\HasMany',
		'many_many'     => 'Orm\\ManyMany',
	);

	/**
	 * @var  array  global array to track circular references in to_array()
	 */
	protected static $to_array_references = array();

	/**
	 * Create a new model instance
	 */
	public static function forge($data = array(), $new = true, $view = null, $cache = true)
	{
		return new static($data, $new, $view, $cache);
	}

	/**
	 * Fetch the database connection name to use
	 *
	 * @param	bool	if true return the writeable connection (if set)
	 * @return  null|string
	 */
	public static function connection($writeable = false)
	{
		$class = get_called_class();

		if ($writeable and property_exists($class, '_write_connection'))
		{
			return static::$_write_connection;
		}

		return property_exists($class, '_connection') ? static::$_connection : null;
	}

	/**
	 * Get the table name for this class
	 *
	 * @return  string
	 */
	public static function table()
	{
		$class = get_called_class();

		// Table name unknown
		if ( ! array_key_exists($class, static::$_table_names_cached))
		{
			// Table name set in Model
			if (property_exists($class, '_table_name'))
			{
				static::$_table_names_cached[$class] = static::$_table_name;
			}
			else
			{
				static::$_table_names_cached[$class] = \Inflector::tableize($class);
			}
		}

		return static::$_table_names_cached[$class];
	}

	/**
	 * Get a defined condition for this class
	 *
	 * @param	string	type of condition to return
	 * @return  array
	 */
	public static function condition($type = null)
	{
		$class = get_called_class();

		// a specific condition requested?
		if (property_exists($class, '_conditions'))
		{
			if ($type !== null)
			{
				return isset(static::$_conditions[$type]) ? static::$_conditions[$type] : array();
			}
			else
			{
				return static::$_conditions;
			}
		}
		else
		{
			return array();
		}
	}

	/**
	 * Attempt to retrieve an earlier loaded object
	 *
	 * @param   array|Model  $obj
	 * @param   null|string  $class
	 * @return  Model|false
	 */
	public static function cached_object($obj, $class = null)
	{
		$class = $class ?: get_called_class();
		$id    = (is_int($obj) or is_string($obj)) ? (string) $obj : $class::implode_pk($obj);

		$result = ( ! empty(static::$_cached_objects[$class][$id])) ? static::$_cached_objects[$class][$id] : false;

		return $result;
	}

	/**
	 * Get the primary key(s) of this class
	 *
	 * @return  array
	 */
	public static function primary_key()
	{
		return static::$_primary_key;
	}

	/**
	 * Implode the primary keys within the data into a string
	 *
	 * @param   array
	 * @return  string
	 */
	public static function implode_pk($data)
	{
		if (count(static::$_primary_key) == 1)
		{
			$p = reset(static::$_primary_key);
			return (is_object($data)
				? strval($data->{$p})
				: (isset($data[$p])
					? strval($data[$p])
					: null));
		}

		$pk = '';
		foreach (static::$_primary_key as $p)
		{
			if (is_null((is_object($data) ? $data->{$p} : (isset($data[$p]) ? $data[$p] : null))))
			{
				return null;
			}
			$pk .= '['.(is_object($data) ? $data->{$p} : $data[$p]).']';
		}

		return $pk;
	}

    /**
     * Get the class's properties
     *
     * @throws \FuelException Listing columns failed
     *
     * @return  array
     */
	public static function properties()
	{
		$class = get_called_class();

		// If already determined
		if (array_key_exists($class, static::$_properties_cached))
		{
			return static::$_properties_cached[$class];
		}

		// Try to grab the properties from the class...
		if (property_exists($class, '_properties'))
		{
			$properties = static::$_properties;
			foreach ($properties as $key => $p)
			{
				if (is_string($p))
				{
					unset($properties[$key]);
					$properties[$p] = array();
				}
			}
		}

		// ...if the above failed, run DB query to fetch properties
		if (empty($properties))
		{
			try
			{
				$properties = \DB::list_columns(static::table(), null, static::connection());
			}
			catch (\Exception $e)
			{
				throw new \FuelException('Listing columns failed, you have to set the model properties with a '.
					'static $_properties setting in the model. Original exception: '.$e->getMessage());
			}
		}

		// cache the properties for next usage
		static::$_properties_cached[$class] = $properties;

		return static::$_properties_cached[$class];
	}

	/**
	 * Fetches a property description array, or specific data from it
	 *
	 * @param   string  property or property.key
	 * @param   mixed   return value when key not present
	 * @return  mixed
	 */
	public static function property($key, $default = null)
	{
		$class = get_called_class();

		// If already determined
		if ( ! array_key_exists($class, static::$_properties_cached))
		{
			static::properties();
		}

		return \Arr::get(static::$_properties_cached[$class], $key, $default);
	}

    /**
     * Fetch the model's views
     *
     * @throws \InvalidArgumentException Database view is defined without columns
     *
     * @return  array
     */
	public static function views()
	{
		$class = get_called_class();

		if ( ! isset(static::$_views_cached[$class]))
		{
			static::$_views_cached[$class] = array();
			if (property_exists($class, '_views'))
			{
				$views = $class::$_views;
				foreach ($views as $k => $v)
				{
					if ( ! isset($v['columns']))
					{
						throw new \InvalidArgumentException('Database view '.$k.' is defined without columns.');
					}
					$v['columns'] = (array) $v['columns'];
					if ( ! isset($v['view']))
					{
						$v['view'] = $k;
					}
					static::$_views_cached[$class][$k] = $v;
				}
			}
		}

		return static::$_views_cached[$class];
	}

	/**
	 * Get the class's relations
	 *
	 * @param bool $specific
	 * @return  HasOne|HasMany|ManyMany|Belongsto|HasOne[]|HasMany[]|ManyMany[]|Belongsto[]
	 */
	public static function relations($specific = false)
	{
		$class = get_called_class();

		if ( ! array_key_exists($class, static::$_relations_cached))
		{
			$relations = array();
			foreach (static::$_valid_relations as $rel_name => $rel_class)
			{
				if (property_exists($class, '_'.$rel_name))
				{
					foreach (static::${'_'.$rel_name} as $key => $settings)
					{
						$name = is_string($settings) ? $settings : $key;
						$settings = is_array($settings) ? $settings : array();
						$relations[$name] = new $rel_class($class, $name, $settings);
					}
				}
			}

			static::$_relations_cached[$class] = $relations;
		}

		if ($specific === false)
		{
			return static::$_relations_cached[$class];
		}
		else
		{
			if ( ! array_key_exists($specific, static::$_relations_cached[$class]))
			{
				return false;
			}

			return static::$_relations_cached[$class][$specific];
		}
	}

	/**
	 * Get the name of the class that defines a relation
	 *
	 * @param   string
	 * @return  array
	 */
	public static function related_class($relation)
	{
		$class = get_called_class();

		foreach (static::$_valid_relations as $rel_name => $rel_class)
		{
			if (property_exists($class, '_'.$rel_name))
			{
				if (isset(static::${'_'.$rel_name}[$relation]))
				{
					return static::${'_'.$rel_name}[$relation]['model_to'];
				}
			}
		}

		return null;
	}

	/**
	 * Get the class's observers and what they observe
	 *
	 * @param   string  specific observer to retrieve info of, allows direct param access by using dot notation
	 * @param   mixed   default return value when specific key wasn't found
	 * @return  array
	 */
	public static function observers($specific = null, $default = null)
	{
		$class = get_called_class();

		if ( ! array_key_exists($class, static::$_observers_cached))
		{
			$observers = array();
			if (property_exists($class, '_observers'))
			{
				foreach (static::$_observers as $obs_k => $obs_v)
				{
					if (is_int($obs_k))
					{
						$observers[$obs_v] = array();
					}
					else
					{
						if (is_string($obs_v) or (is_array($obs_v) and is_int(key($obs_v))))
						{
							// @TODO deprecated until v1.4
							logger(\Fuel::L_WARNING, 'Passing observer events as array is deprecated, they must be
								inside another array under a key "events". Check the docs for more info.', __METHOD__);
							$observers[$obs_k] = array('events' => (array) $obs_v);
						}
						else
						{
							$observers[$obs_k] = $obs_v;
						}
					}
				}
			}
			static::$_observers_cached[$class] = $observers;
		}

		if ($specific)
		{
			return \Arr::get(static::$_observers_cached[$class], $specific, $default);
		}

		return static::$_observers_cached[$class];
	}

	/**
	 * Register an observer
	 *
	 * @param	string	class name of the observer (including namespace)
	 * @param	mixed	observer options
	 *
	 * @return	void
	 */
	public static function register_observer($name, $options = null)
	{
		$class = get_called_class();
		$new_observer = is_null($options) ? array($name) : array($name => $options);

		static::$_observers_cached[$class] = static::observers() + $new_observer;
	}

	/**
	 * Unregister an observer
	 *
	 * @param string class name of the observer (including namespace)
	 * @return void
	 */
	public static function unregister_observer($name)
	{
		$class = get_called_class();
		foreach (static::observers() as $key => $value)
		{
			if ((is_array($value) and $key == $name) or $value == $name)
			{
				unset(static::$_observers_cached[$class][$key]);
			}
		}
	}

    /**
     * Find one or more entries
     *
     * @param int|null $id
     * @param array $options
     *
     * @throws \FuelException
     *
     * @return  Model|Model[]
     */
	public static function find($id = null, array $options = array())
	{
		// deal with null valued PK's
		if (is_null($id))
		{
			// if no options are present, simply return null. a PK with a null value can exist
			return func_num_args() === 2 ? static::query($options) : null;
		}

		// Return all that match $options array
		elseif ($id === 'all')
		{
			return static::query($options)->get();
		}

		// Return first or last row that matches $options array
		elseif ($id === 'first' or $id === 'last')
		{
			$query = static::query($options);

			foreach(static::primary_key() as $pk)
			{
				$query->order_by($pk, $id == 'first' ? 'ASC' : 'DESC');
			}

			return $query->get_one();
		}

		// Return specific request row by ID
		else
		{
			$cache_pk = $where = array();
			$id = (array) $id;
			foreach (static::primary_key() as $pk)
			{
				$where[] = array($pk, '=', current($id));
				$cache_pk[$pk] = current($id);
				next($id);
			}

			if (array_key_exists(get_called_class(), static::$_cached_objects)
			    and array_key_exists(static::implode_pk($cache_pk), static::$_cached_objects[get_called_class()])
			    and (! isset($options['from_cache']) or $options['from_cache'] == true))
			{
				return static::$_cached_objects[get_called_class()][static::implode_pk($cache_pk)];
			}

			array_key_exists('where', $options) and $where = array_merge($options['where'], $where);
			$options['where'] = $where;
			return static::query($options)->get_one();
		}
	}

	/**
	 * Creates a new query with optional settings up front
	 *
	 * @param   array
	 * @return  Query
	 */
	public static function query($options = array())
	{
		return Query::forge(get_called_class(), array(static::connection(), static::connection(true)), $options);
	}

	/**
	 * Count entries, optionally only those matching the $options
	 *
	 * @param   array
	 * @return  int
	 */
	public static function count(array $options = array())
	{
		return static::query($options)->count();
	}

	/**
	 * Find the maximum
	 *
	 * @param   mixed
	 * @param   array
	 * @return  bool|int Maximum value or false
	 */
	public static function max($key = null)
	{
		return static::query()->max($key ?: static::primary_key());
	}

	/**
	 * Find the minimum
	 *
	 * @param   mixed
	 * @param   array
	 * @return  object|array
	 */
	public static function min($key = null)
	{
		return static::query()->min($key ?: static::primary_key());
	}

	public static function __callStatic($method, $args)
	{
		// Start with count_by? Get counting!
		if (strpos($method, 'count_by') === 0)
		{
			$find_type = 'count';
			$fields = substr($method, 9);
		}

		// Otherwise, lets find stuff
		elseif (strpos($method, 'find_') === 0)
		{
			if ($method == 'find_by')
			{
				$find_type = 'all';
				$fields = array_shift($args);
			}
			else
			{
				$find_type = strncmp($method, 'find_all_by_', 12) === 0 ? 'all' : (strncmp($method, 'find_by_', 8) === 0 ? 'first' : false);
				$fields = $find_type === 'first' ? substr($method, 8) : substr($method, 12);
			}
		}

		// God knows, complain
		else
		{
			throw new \FuelException('Invalid method call.  Method '.$method.' does not exist.', 0);
		}

		$where = $or_where = array();

		if (($and_parts = explode('_and_', $fields)))
		{
			foreach ($and_parts as $and_part)
			{
				$or_parts = explode('_or_', $and_part);

				if (count($or_parts) == 1)
				{
					$where[] = array($or_parts[0], array_shift($args));
				}
				else
				{
					foreach($or_parts as $or_part)
					{
						$or_where[] = array($or_part, array_shift($args));
					}
				}
			}
		}

		$options = count($args) > 0 ? array_pop($args) : array();

		if ( ! empty($where))
		{
			if ( ! array_key_exists('where', $options))
			{
				$options['where'] = $where;
			}
			else
			{
				$options['where'] = array_merge($where, $options['where']);
			}
		}

		if ( ! empty($or_where))
		{
			if ( ! array_key_exists('or_where', $options))
			{
				$options['or_where'] = $or_where;
			}
			else
			{
				$options['or_where'] = array_merge($or_where, $options['or_where']);
			}
		}

		if ($find_type == 'count')
		{
			return static::count($options);
		}

		else
		{
			return static::find($find_type, $options);
		}

		// min_...($options)
		// max_...($options)
	}

	/* ---------------------------------------------------------------------------
	 * Object usage
	 * --------------------------------------------------------------------------- */

	/**
	 * @var  bool  keeps track of whether it's a new object
	 */
	protected $_is_new = true;

	/**
	 * @var  bool  keeps to object frozen
	 */
	protected $_frozen = false;

	/**
	 * @var  bool  $_sanitization_enabled  If this is a records data will be sanitized on get
	 */
	protected $_sanitization_enabled = false;

	/**
	 * @var  array  keeps the current state of the object
	 */
	protected $_data = array();

	/**
	 * @var  array  storage for custom properties on this object
	 */
	protected $_custom_data = array();

	/**
	 * @var  array  keeps a copy of the object as it was retrieved from the database
	 */
	protected $_original = array();

	/**
	 * @var  array
	 */
	protected $_data_relations = array();

	/**
	 * @var  array  keeps a copy of the relation ids that were originally retrieved from the database
	 */
	protected $_original_relations = array();

	/**
	 * @var  array  keeps track of relations that need to be reset before saving the new ones
	 */
	protected $_reset_relations = array();

	/**
	 * @var  array  disabled observer events
	 */
	protected $_disabled_events = array();

	/**
	 * @var  string  view name when used
	 */
	protected $_view;

	/**
	 * Constructor
	 *
	 * @param  array
	 * @param  bool
	 */
	public function __construct(array $data = array(), $new = true, $view = null, $cache = true)
	{
		// This is to deal with PHP's native hydration that happens before constructor is called
		// for some weird reason, for example using the DB's as_object() function
		if( ! empty($this->_data))
		{
			// merge the injected data with the passed data
			$data = array_merge($this->_data, $data);
			$this->_data = array();

			// and mark it as existing data
			$new = false;
		}

		// move the passed data to the correct container
		$properties = $this->properties();
		foreach ($properties as $prop => $settings)
		{
			// do we have data for this this model property?
			if (array_key_exists($prop, $data))
			{
				// store it in the data container
				$this->_data[$prop] = $data[$prop];
				unset($data[$prop]);
			}

			// property not present, do we have a default value?
			elseif ($new and array_key_exists('default', $settings))
			{
				$this->_data[$prop] = $settings['default'];
			}
		}

		// store the remainder in the custom data store
		$this->_custom_data = $data;

		// store the view, if one was passed
		if ($view and array_key_exists($view, $this->views()))
		{
			$this->_view = $view;
		}

		if ($new === false)
		{
			// update the original datastore and the related datastore
			$this->_update_original($this->_data);

			// update the object cache if needed
			$cache and static::$_cached_objects[get_class($this)][static::implode_pk($this->_data)] = $this;

			// mark the object as existing
			$this->_is_new = false;

			// and fire the after-load observers
			$this->observe('after_load');
		}
		else
		{
			// new object, fire the after-create observers
			$this->observe('after_create');
		}
	}

	/**
	 * Update the original setting for this object
	 *
	 * @param  array|null  $original
	 */
	public function _update_original($original = null)
	{
		$original = is_null($original) ? $this->_data : $original;
		$this->_original = array_merge($this->_original, $original);

		$this->_update_original_relations();
	}

	/**
	 * Update the original relations for this object
	 */
	public function _update_original_relations($relations = null)
	{
		if (is_null($relations))
		{
			$this->_original_relations = array();
			$relations = $this->_data_relations;
		}
		else
		{
			foreach ($relations as $key => $rel)
			{
				// Unload the just fetched relation from the originals
				unset($this->_original_relations[$rel]);

				// Unset the numeric key and set the data to update by the relation name
				unset($relations[$key]);
				$relations[$rel] = $this->_data_relations[$rel];
			}
		}

		foreach ($relations as $rel => $data)
		{
			if (is_array($data))
			{
				$this->_original_relations[$rel] = array();
				foreach ($data as $obj)
				{
					if ($obj and ! $obj->is_new())
					{
						$this->_original_relations[$rel][] = $obj->implode_pk($obj);
					}
				}
			}
			else
			{
				$this->_original_relations[$rel] = null;
				if ($data and ! $data->is_new())
				{
					$this->_original_relations[$rel] = $data->implode_pk($data);
				}
			}
		}
	}

    /**
     * Fetch or set relations on this object
     * To be used only after having fetched them from the database!
     *
     * @param array|bool|null $rels
     *
     * @throws \FuelException  Invalid input for _relate(), should be an array
     * @throws FrozenObject    No changes allowed
     *
     * @return  void|array
     */
	public function _relate($rels = false)
	{
		if ($rels === false)
		{
			return $this->_data_relations;
		}
		elseif (is_array($rels))
		{
			if ($this->_frozen)
			{
				throw new FrozenObject('No changes allowed.');
			}
			$this->_data_relations = $rels;
		}
		else
		{
			throw new \FuelException('Invalid input for _relate(), should be an array.');
		}
	}

	/**
	 * Fetch a property or relation
	 *
	 * @param   string
	 * @return  mixed
	 */
	public function & __get($property)
	{
		return $this->get($property);
	}

	/**
	 * Set a property or relation
	 *
	 * @param  string
	 * @param  mixed
	 *
	 * @return Model
	 */
	public function __set($property, $value)
	{
		return $this->set($property, $value);
	}

	/**
	 * Check whether a property exists, only return true for table columns, relations, eav and custom data
	 *
	 * @param   string  $property
	 * @return  bool
	 */
	public function __isset($property)
	{
		if (array_key_exists($property, static::properties()))
		{
			return true;
		}
		elseif (static::relations($property))
		{
			return true;
		}
		elseif ($this->_get_eav($property, true))
		{
			return true;
		}
		elseif (array_key_exists($property, $this->_custom_data))
		{
			return true;
		}

		return false;
	}

	/**
	 * Empty a property, relation or custom data
	 *
	 * @param   string  $property
	 */
	public function __unset($property)
	{
		if (array_key_exists($property, static::properties()))
		{
			$this->_data[$property] = null;
		}
		elseif ($rel = static::relations($property))
		{
			$this->_reset_relations[$property] = true;
			$this->_data_relations[$property] = $rel->singular ? null : array();
		}
		elseif ($this->_get_eav($property, true, true))
		{
			// no additional work needed here
		}
		elseif (array_key_exists($property, $this->_custom_data))
		{
			unset($this->_custom_data[$property]);
		}
	}

	/**
	 * Allow for getter, setter and unset methods
	 *
	 * @param   string  $method
	 * @param   array   $args
	 * @return  mixed
	 * @throws  \BadMethodCallException
	 */
	public function __call($method, $args)
	{
		if (substr($method, 0, 4) == 'get_')
		{
			return $this->get(substr($method, 4));
		}
		elseif (substr($method, 0, 4) == 'set_')
		{
			return $this->set(substr($method, 4), reset($args));
		}
		elseif (substr($method, 0, 6) == 'unset_')
		{
			return $this->__unset(substr($method, 6));
		}

		// Throw an exception
		throw new \BadMethodCallException('Call to undefined method '.get_class($this).'::'.$method.'()');
	}

	/**
	 * Allow object cloning to new object
	 */
	public function __clone()
	{
		// Reset primary keys
		foreach (static::$_primary_key as $pk)
		{
			$this->_data[$pk] = null;
		}

		// This is a new object
		$this->_is_new = true;
		$this->_original = array();
		$this->_original_relations = array();

		// Cleanup relations
		foreach ($this->relations() as $name => $rel)
		{
			// singular relations (hasone, belongsto) can't be copied, neither can HasMany
			if ($rel->singular or $rel instanceof HasMany)
			{
				unset($this->_data_relations[$name]);
			}
		}

		$this->observe('after_clone');
	}

	/**
	 * Get
	 *
	 * Gets a property or
	 * relation from the
	 * object
	 *
	 * @access  public
	 * @param   string  $property
	 * @param   array   $conditions
	 * @return  mixed
	 */
	public function & get($property, array $conditions = array())
	{
		// database columns
		if (array_key_exists($property, static::properties()))
		{
			if ( ! array_key_exists($property, $this->_data))
			{
				$result = null;
			}
			elseif ($this->_sanitization_enabled)
			{
				// use a copy
				$result = $this->_data[$property];
			}
			else
			{
				// use a reference
				$result =& $this->_data[$property];
			}
		}

		// related models
		elseif ($rel = static::relations($property))
		{
			if ( ! array_key_exists($property, $this->_data_relations))
			{
				$this->_data_relations[$property] = $rel->get($this, $conditions);
				$this->_update_original_relations(array($property));
			}

			$result =& $this->_data_relations[$property];
		}

		// EAV properties
		elseif (($result = $this->_get_eav($property)) !== false)
		{
			// nothing else to do here
		}

		// database view columns
		elseif ($this->_view and in_array($property, static::$_views_cached[get_class($this)][$this->_view]['columns']))
		{
			if ($this->_sanitization_enabled)
			{
				// use a copy
				$result = $this->_data[$property];
			}
			else
			{
				// use a reference
				$result =& $this->_data[$property];
			}
		}

		// stored custom data
		elseif (array_key_exists($property, $this->_custom_data))
		{
			if ($this->_sanitization_enabled)
			{
				// use a copy
				$result = $this->_custom_data[$property];
			}
			else
			{
				// use a reference
				$result =& $this->_custom_data[$property];
			}
		}
		else
		{
			throw new \OutOfBoundsException('Property "'.$property.'" not found for '.get_class($this).'.');
		}

		// do we need to clean before returning the result?
		if ($this->_sanitization_enabled)
		{
			$result = \Security::clean($result, null, 'security.output_filter');
		}

		return $result;
	}

    /**
     * Set
     *
     * Sets a property or
     * relation of the
     * object
     *
     * @access  public
     * @param   string|array $property
     * @param   string $value in case $property is a string
     *
     * @throws \FuelException Primary key on model cannot be changed
     * @throws \InvalidArgumentException You need to pass both a property name and a value to set()
     * @throws FrozenObject No changes allowed
     *
     * @return  Model
     */
	public function set($property, $value = null)
	{
		if ($this->_frozen)
		{
			throw new FrozenObject('No changes allowed.');
		}

		if (is_array($property))
		{
			foreach ($property as $p => $v)
			{
				$this->set($p, $v);
			}
		}
		else
		{
			if (func_num_args() < 2)
			{
				throw new \InvalidArgumentException('You need to pass both a property name and a value to set().');
			}

			if (in_array($property, static::primary_key()) and $this->{$property} !== null)
			{
				throw new \FuelException('Primary key on model '.get_class($this).' cannot be changed.');
			}
			if (array_key_exists($property, static::properties()))
			{
				$this->_data[$property] = $value;
			}
			elseif (static::relations($property))
			{
				$this->is_fetched($property) or $this->_reset_relations[$property] = true;
				$this->_data_relations[$property] = $value;
			}
			elseif ( ! $this->_set_eav($property, $value))
			{
				$this->_custom_data[$property] = $value;
			}
		}

		return $this;
	}

	/**
	 * Save the object and it's relations, create when necessary
	 *
	 * @param  mixed  $cascade
	 *     null = use default config,
	 *     bool = force/prevent cascade,
	 *     array cascades only the relations that are in the array
     *
     * @return bool
     */
	public function save($cascade = null, $use_transaction = false)
	{
		if ($this->frozen())
		{
			return false;
		}

		if ($use_transaction)
		{
			$db = \Database_Connection::instance(static::connection(true));
			$db->start_transaction();
		}

		try
		{
			$this->observe('before_save');

			$this->freeze();
			foreach($this->relations() as $rel_name => $rel)
			{
				if (array_key_exists($rel_name, $this->_reset_relations))
				{
					if (method_exists($rel, 'delete_related'))
					{
						$rel->delete_related($this);
						$this->_original_relations[$rel_name] = $rel->singular ? null : array();
					}
					else
					{
						if (empty($this->_original_relations[$rel_name]))
						{
							$data = $rel->get($this);
							if (is_array($data))
							{
								$this->_original_relations[$rel_name] = array();
								foreach ($data as $obj)
								{
									$this->_original_relations[$rel_name][] = $obj ? $obj->implode_pk($obj) : null;
								}
							}
							else
							{
								$this->_original_relations[$rel_name] = $data ? $data->implode_pk($data) : null;
							}
						}
					}
					unset($this->_reset_relations[$rel_name]);
				}
				if (array_key_exists($rel_name, $this->_data_relations))
				{
					$rel->save($this, $this->{$rel_name},
						array_key_exists($rel_name, $this->_original_relations) ? $this->_original_relations[$rel_name] : null,
						false, is_array($cascade) ? in_array($rel_name, $cascade) : $cascade
					);
				}
			}
			$this->unfreeze();

			// Insert or update
			$return = $this->_is_new ? $this->create() : $this->update();

			$this->freeze();
			foreach($this->relations() as $rel_name => $rel)
			{
				if (array_key_exists($rel_name, $this->_data_relations))
				{
					$rel->save($this, $this->{$rel_name},
						array_key_exists($rel_name, $this->_original_relations) ? $this->_original_relations[$rel_name] : null,
						true, is_array($cascade) ? in_array($rel_name, $cascade) : $cascade
					);
				}
			}
			$this->unfreeze();

			$this->_update_original();

			$this->observe('after_save');

			$use_transaction and $db->commit_transaction();
		}
		catch (\Exception $e)
		{
			$use_transaction and $db->rollback_transaction();
			throw $e;
		}

		return $return;
	}

	/**
	 * Save using INSERT
	 */
	protected function create()
	{
		// Only allow creation with new object, otherwise: clone first, create later
		if ( ! $this->is_new())
		{
			return false;
		}

		$this->observe('before_insert');

		// Set all current values
		$query = Query::forge(get_called_class(), static::connection(true));
		$primary_key = static::primary_key();
		$properties  = array_keys(static::properties());
		foreach ($properties as $p)
		{
			if ( ! (in_array($p, $primary_key) and is_null($this->{$p})))
			{
				$query->set($p, $this->{$p});
			}
		}

		// Insert!
		$id = $query->insert();

		// when there's one PK it might be auto-incremented, get it and set it
		if (count($primary_key) == 1 and $id !== false)
		{
			$pk = reset($primary_key);
			// only set it if it hasn't been set manually
			is_null($this->{$pk}) and $this->{$pk} = $id;
		}

		// update the original properties on creation and cache object for future retrieval in this request
		$this->_is_new = false;
		$this->_original = $this->_data;
		static::$_cached_objects[get_class($this)][static::implode_pk($this)] = $this;

		$this->observe('after_insert');

		return $id !== false;
	}

	/**
	 * Save using UPDATE
	 */
	protected function update()
	{
		// New objects can't be updated, neither can frozen
		if ($this->is_new())
		{
			return false;
		}

		// Non changed objects don't have to be saved, but return true anyway (no reason to fail)
		if ( ! $this->is_changed(array_keys(static::properties())))
		{
			return true;
		}

		$this->observe('before_update');

		// Create the query and limit to primary key(s)
		$query       = Query::forge(get_called_class(), static::connection(true));
		$primary_key = static::primary_key();
		$properties  = array_keys(static::properties());
		//Add the primary keys to the where
		$this->add_primary_keys_to_where($query);

		// Set all current values
		foreach ($properties as $p)
		{
			if ( ! in_array($p, $primary_key) )
			{
				if (array_key_exists($p, $this->_original))
				{
					$this->{$p} !== $this->_original[$p] and $query->set($p, isset($this->_data[$p]) ? $this->_data[$p] : null);
				}
				else
				{
					array_key_exists($p, $this->_data) and $query->set($p, $this->_data[$p]);
				}
			}
		}

		// Return false when update fails
		if ( ! $query->update())
		{
			return false;
		}

		// update the original property on success
		$this->observe('after_update');

		return true;
	}

	/**
	 * Adds the primary keys in where clauses to the given query.
	 *
	 * @param Query $query
	 */
	protected function add_primary_keys_to_where($query)
	{
		$primary_key = static::primary_key();
		foreach ($primary_key as $pk)
		{
			$query->where($pk, '=', $this->_original[$pk]);
		}
	}

    /**
     * Delete current object
     *
     * @param   mixed $cascade
     *     null = use default config,
     *     bool = force/prevent cascade,
     *     array cascades only the relations that are in the array
     * @param bool $use_transaction
     *
     * @throws \Exception
     *
     * @return  Model  this instance as a new object without primary key(s)
     */
	public function delete($cascade = null, $use_transaction = false)
	{
		// New objects can't be deleted, neither can frozen
		if ($this->is_new() or $this->frozen())
		{
			return false;
		}

		if ($use_transaction)
		{
			$db = \Database_Connection::instance(static::connection(true));
			$db->start_transaction();
		}

		try
		{
			$this->observe('before_delete');

			$this->freeze();
			foreach($this->relations() as $rel_name => $rel)
			{
				$should_cascade = is_array($cascade) ? in_array($rel_name, $cascade) : $rel->cascade_delete;

				// Give model subclasses a chance to chip in.
				if ($should_cascade && ! $this->should_cascade_delete($rel))
				{
					// The function returned false so something does not want this relation to be cascade deleted
					$should_cascade = false;
				}

				$rel->delete($this, $this->{$rel_name}, false, $should_cascade);
			}
			$this->unfreeze();

			// Delete the model in question
			if ( ! $this->delete_self())
			{
				return false;
			}

			$this->freeze();
			foreach($this->relations() as $rel_name => $rel)
			{
				$should_cascade = is_array($cascade) ? in_array($rel_name, $cascade) : $rel->cascade_delete;

				// Give model subclasses a chance to chip in.
				if ($should_cascade && ! $this->should_cascade_delete($rel))
				{
					// The function returned false so something does not want this relation to be cascade deleted
					$should_cascade = false;
				}

				$rel->delete($this, $this->{$rel_name}, true, $should_cascade);
			}
			$this->unfreeze();

			// Perform cleanup:
			// remove from internal object cache, remove PK's, set to non saved object, remove db original values
			if (array_key_exists(get_called_class(), static::$_cached_objects)
				and array_key_exists(static::implode_pk($this), static::$_cached_objects[get_called_class()]))
			{
				unset(static::$_cached_objects[get_called_class()][static::implode_pk($this)]);
			}
			foreach ($this->primary_key() as $pk)
			{
				unset($this->_data[$pk]);
			}
			// remove original relations too
			foreach($this->relations() as $rel_name => $rel)
			{
				$this->_original_relations[$rel_name] = $rel->singular ? null : array();
			}

			$this->_is_new = true;
			$this->_original = array();


			$this->observe('after_delete');

			$use_transaction and $db->commit_transaction();
		}
		catch (\Exception $e)
		{
			$use_transaction and $db->rollback_transaction();
			throw $e;
		}

		return $this;
	}

	/**
	 * Deletes this model instance from the database.
	 *
	 * @return bool
	 */
	protected function delete_self()
	{
		// Create the query and limit to primary key(s)
		$query = Query::forge(get_called_class(), static::connection(true))->limit(1);
		$primary_key = static::primary_key();
		foreach ($primary_key as $pk)
		{
			$query->where($pk, '=', $this->{$pk});
		}

		// Return success of update operation
		return $query->delete();
	}

	/**
	 * Allows subclasses to more easily define if a relation can be cascade deleted or not.
	 *
	 * @param array $rel
	 *
	 * @return bool False to stop the relation from being deleted. Works the same as the cascade_delete property
	 */
	protected function should_cascade_delete($rel)
	{
		return true;
	}

	/**
	 * Reset values to those gotten from the database
	 */
	public function reset()
	{
		foreach ($this->_original as $p => $val)
		{
			$this->_data[$p] = $val;
		}
	}

	/**
	 * Disable an observer event
	 *
	 * @param string event to disable
	 * @return void
	 */
	public function disable_event($event)
	{
		$this->_disabled_events[$event] = true;
	}

	/**
	 * Enable a defined observer
	 *
	 * @param string class name of the observer (including namespace)
	 * @param string event to enable, or null for all events
	 * @return void
	 */
	public function enable_event($event)
	{
		unset($this->_disabled_events[$event]);
	}

	/**
	 * Calls all observers for the current event
	 *
	 * @param  string
	 */
	public function observe($event)
	{
		foreach ($this->observers() as $observer => $settings)
		{
			$events = isset($settings['events']) ? $settings['events'] : array();
			if ((empty($events) or in_array($event, $events))
				and empty($this->_disabled_events[$event]))
			{
				if ( ! class_exists($observer))
				{
					$observer_class = \Inflector::get_namespace($observer).'Observer_'.\Inflector::denamespace($observer);
					if ( ! class_exists($observer_class))
					{
						throw new \UnexpectedValueException($observer);
					}

					// Add the observer with the full classname for next usage
					unset(static::$_observers_cached[$observer]);
					static::$_observers_cached[$observer_class] = $events;
					$observer = $observer_class;
				}

				try
				{
					call_user_func(array($observer, 'orm_notify'), $this, $event);
				}
				catch (\Exception $e)
				{
					// Unfreeze before failing
					$this->unfreeze();

					throw $e;
				}
			}
		}
	}

	/**
	 * Compare current state with the retrieved state
	 *
	 * @param   string|array $property
	 *
	 * @throws \OutOfBoundsException
	 *
	 * @return  bool
	 */
	public function is_changed($property = null)
	{
		$properties = static::properties();
		$relations = static::relations();
		$property = (array) $property ?: array_merge(array_keys($properties), array_keys($relations));

		foreach ($property as $p)
		{
			if (isset($properties[$p]))
			{
				if (array_key_exists($p, $this->_original))
				{
					if (array_key_exists('type', $properties[$p]) and $properties[$p]['type'] == 'int')
					{
						if ($this->{$p} != $this->_original[$p])
						{
							return true;
						}
					}
					elseif ($this->{$p} !== $this->_original[$p])
					{
						return true;
					}
				}
				else
				{
					if (array_key_exists($p, $this->_data))
					{
						return true;
					}
				}
			}
			elseif (isset($relations[$p]))
			{
				if ($relations[$p]->singular)
				{
					if (empty($this->_original_relations[$p]) !== empty($this->_data_relations[$p])
						or ( ! empty($this->_original_relations[$p])
							and $this->_original_relations[$p] !== $this->_data_relations[$p]->implode_pk($this->{$p})))
					{
						return true;
					}
				}
				else
				{
					if (empty($this->_original_relations[$p]))
					{
						if ( ! empty($this->_data_relations[$p]))
						{
							return true;
						}
						continue;
					}

					$orig_rels = $this->_original_relations[$p];
					foreach ($this->{$p} as $rk => $r)
					{
						if ( ! in_array($r->implode_pk($r), $orig_rels))
						{
							return true;
						}
						unset($orig_rels[array_search($rk, $orig_rels)]);
					}
					if ( ! empty($orig_rels))
					{
						return true;
					}
				}
			}
			else
			{
				throw new \OutOfBoundsException('Unknown property or relation: '.$p);
			}
		}

		return false;
	}

	/**
	 * Generates an array with keys new & old that contain ONLY the values that differ between the original and
	 * the current unsaved model.
	 * Note: relations are given as single or array of imploded pks
	 *
	 * @return  array
	 */
	public function get_diff()
	{
		$diff = array(0 => array(), 1 => array());
		foreach ($this->_data as $key => $val)
		{
			if ($this->is_changed($key))
			{
				$diff[0][$key] = array_key_exists($key, $this->_original) ? $this->_original[$key] : null;
				$diff[1][$key] = $val;
			}
		}
		foreach ($this->_data_relations as $key => $val)
		{
			$rel = static::relations($key);
			if ($rel->singular)
			{
				$new_pk = null;
				if (empty($this->_original_relations[$key]) !== empty($val)
					or ( ! empty($this->_original_relations[$key]) and ! empty($val)
						and $this->_original_relations[$key] !== $new_pk = $val->implode_pk($val)
					))
				{
					$diff[0][$key] = isset($this->_original_relations[$key]) ? $this->_original_relations[$key] : null;
					$diff[1][$key] = isset($val) ? $new_pk : null;
				}
			}
			else
			{
				$original_pks = empty($this->_original_relations[$key]) ? array() : $this->_original_relations[$key];
				$new_pks = array();
				if ($val)
				{
					foreach ($val as $v)
					{
						if ( ! in_array(($new_pk = $v->implode_pk($v)), $original_pks))
						{
							$new_pks[] = $new_pk;
						}
						else
						{
							$original_pks = array_diff($original_pks, array($new_pk));
						}
					}
				}
				if ( ! empty($original_pks) or ! empty($new_pks)) {
					$diff[0][$key] = empty($original_pks) ? null : $original_pks;
					$diff[1][$key] = empty($new_pks) ? null : $new_pks;
				}
			}
		}

		return $diff;
	}

	/***
	 * Returns whether the given relation is fetched. If no relation is
	 *
	 * @param string $relation Name of relation
	 *
	 * @return  bool
	 */
	public function is_fetched($relation)
	{
		if (static::relations($relation))
		{
			return array_key_exists($relation, $this->_data_relations);
		}

		return false;
	}

	/***
	 * Returns whether this is a saved or a new object
	 *
	 * @return  bool
	 */
	public function is_new()
	{
		return $this->_is_new;
	}

	/**
	 * Check whether the object was frozen
	 *
	 * @return  boolean
	 */
	public function frozen()
	{
		return $this->_frozen;
	}

	/**
	 * Freeze the object to disallow changing it or saving it
	 */
	public function freeze()
	{
		$this->_frozen = true;
	}

	/**
	 * Unfreeze the object to allow changing it or saving it again
	 */
	public function unfreeze()
	{
		$this->_frozen = false;
	}

	/**
	 * Enable sanitization mode in the object
	 *
	 * @return  $this
	 */
	public function sanitize()
	{
		$this->_sanitization_enabled = true;

		return $this;
	}

	/**
	 * Disable sanitization mode in the object
	 *
	 * @return  $this
	 */
	public function unsanitize()
	{
		$this->_sanitization_enabled = false;

		return $this;
	}

	/**
	 * Returns the current sanitization state of the object
	 *
	 * @return  bool
	 */
	public function sanitized()
	{
		return $this->_sanitization_enabled;
	}

	/**
	 * Method for use with Fieldset::add_model()
	 *
	 * @param   Fieldset     Fieldset instance to add fields to
	 * @param   array|Model  Model instance or array for use to repopulate
	 */
	public static function set_form_fields($form, $instance = null)
	{
		Observer_Validation::set_fields($instance instanceof static ? $instance : get_called_class(), $form);
		$instance and $form->populate($instance, true);
	}


	/**
	 * Allow populating this object from an array, and any related objects
	 *
	 * @param  array  assoc array with named values to store in the object
	 *
	 * @return  Model  this instance as a new object without primary key(s)
	 */
	public function from_array(array $values)
	{
		foreach($values as $property => $value)
		{
			if (array_key_exists($property, static::properties()) and ! in_array($property, static::primary_key()))
			{
				$this->_data[$property] = $value;
			}
			elseif (array_key_exists($property, static::relations()) and is_array($value))
			{
				$rel = static::relations($property);
				if ( ! isset($this->_data_relations[$property]))
				{
					$this->_data_relations[$property] = $rel->singular ? null : array();
				}
				foreach($value as $id => $data)
				{
					if (is_array($data))
					{
						if (array_key_exists($id, $this->_data_relations[$property]))
						{
							foreach($data as $field => $contents)
							{
								if ($rel->singular)
								{
									$this->_data_relations[$property]->{$field} = $contents;
								}
								else
								{
									$this->_data_relations[$property][$id]->{$field} = $contents;
								}
							}
						}
						else
						{
							if ($rel->singular)
							{
								$this->_data_relations[$property] = call_user_func(static::relations($property)->model_to.'::forge', $data);
							}
							else
							{
								$this->_data_relations[$property][] = call_user_func(static::relations($property)->model_to.'::forge', $data);
							}
						}
					}
				}
			}
			elseif (property_exists($this, '_eav') and ! empty(static::$_eav))
			{
				$this->_set_eav($property, $value);
			}
			else
			{
				$this->_custom_data[$property] = $value;
			}
		}

		return $this;
	}

	/**
	 * Allow converting this object to an array
	 *
	 * @param bool $custom
	 * @param bool $recurse
	 * @param bool $eav
	 *
	 * @internal param \Orm\whether $bool or not to include the custom data array
	 *
	 * @return  array
	 */
	public function to_array($custom = false, $recurse = false, $eav = false)
	{
		// storage for the result
		$array = array();

		// reset the references array on first call
		$recurse or static::$to_array_references = array(get_class($this));

		// make sure all data is scalar or array
		if ($custom)
		{
			foreach ($this->_custom_data as $key => $val)
			{
				if (is_object($val))
				{
					if (method_exists($val, '__toString'))
					{
						$val = (string) $val;
					}
					else
					{
						$val = get_object_vars($val);
					}
				}
				$array[$key] = $val;
			}
		}

		// make sure all data is scalar or array
		foreach ($this->_data as $key => $val)
		{
			if (is_object($val))
			{
				if (method_exists($val, '__toString'))
				{
					$val = (string) $val;
				}
				else
				{
					$val = get_object_vars($val);
				}
			}
			$array[$key] = $val;
		}

		// convert relations
		foreach ($this->_data_relations as $name => $rel)
		{
			if (is_array($rel))
			{
				$array[$name] = array();
				if ( ! empty($rel))
				{
					static::$to_array_references[] = get_class(reset($rel));
					foreach ($rel as $id => $r)
					{
						$array[$name][$id] = $r->to_array($custom, true, $eav);
					}
				}
			}
			else
			{
				if ( ! in_array(get_class($rel), static::$to_array_references))
				{
					if (is_null($rel))
					{
						$array[$name] = null;
					}
					else
					{
						static::$to_array_references[] = get_class($rel);
						$array[$name] = $rel->to_array($custom, true, $eav);
					}
				}
			}
		}

		// get eav relations
		if ($eav and property_exists(get_called_class(), '_eav'))
		{
			// loop through the defined EAV containers
			foreach (static::$_eav as $rel => $settings)
			{
				// normalize the container definition, could be string or array
				if (is_string($settings))
				{
					$rel = $settings;
					$settings = array();
				}

				// determine attribute and value column names
				$attr = \Arr::get($settings, 'attribute', 'attribute');
				$val  = \Arr::get($settings, 'value', 'value');

				// check if relation is present
				if (array_key_exists($rel, $array))
				{
					// get eav properties
					$container = \Arr::assoc_to_keyval($array[$rel], $attr, $val);

					// merge eav properties to array without overwritting anything
					$array = array_merge($container, $array);

					// we don't need this relation anymore
					unset($array[$rel]);
				}
			}
		}

		// strip any excluded values from the array
		foreach (static::$_to_array_exclude as $key)
		{
			if (array_key_exists($key, $array))
			{
				unset($array[$key]);
			}
		}

		return $array;
	}


	/**
	 * Allow converting this object to a real object
	 *
	 * @return  object
	 */
	public function to_object($custom = false, $recurse = false)
	{
		return (object) $this->to_array($custom, $recurse);
	}

	/**
	 * EAV attribute getter. Also deals with isset() and unset()
	 *
	 * @param   string $attribute, the attribute value to get
	 * @param    bool $isset, if true, do an exists check instead of returning the value
	 * @param    bool $unset, if true, delete the EAV attribute if it exists
	 *
	 * @throws \OutOfBoundsException if the defined EAV relation does not exist or of the wrong type
	 *
	 * @return  mixed
	 */
	protected function _get_eav($attribute, $isset = false, $unset = false)
	{
		// get the current class name
		$class = get_called_class();

		// don't do anything unless we actually have an EAV container
		if (property_exists($class, '_eav'))
		{
			// loop through the defined EAV containers
			foreach (static::$_eav as $rel => $settings)
			{
				// normalize the container definition, could be string or array
				if (is_string($settings))
				{
					$rel = $settings;
					$settings = array();
				}

				// fetch the relation object for this EAV container
				if ( ! $rel = static::relations($rel))
				{
					throw new \OutOfBoundsException('EAV container defines a relation that does not exist in '.get_class($this).'.');
				}

				// EAV containers must be of the "Many type"
				if ($rel instanceOf \Orm\HasOne or $rel instanceOf \Orm\BelongsTo )
				{
					throw new \OutOfBoundsException('EAV containers can only be defined on "HasMany" or "ManyMany" relations in '.get_class($this).'.');
				}

				// determine attribute and value column names
				$attr = isset($settings['attribute']) ? $settings['attribute'] : 'attribute';
				$val = isset($settings['value']) ? $settings['value'] : 'value';

				// see if we have a result
				if ($result = $this->{$rel->name})
				{
					// loop over the resultset
					foreach ($result as $key => $record)
					{
						// check if this is the attribute we need
						if ($record->{$attr} === $attribute)
						{
							if ($unset)
							{
								// delete the related object if we need to unset
								unset($this->{$rel->name}[$key]);
								$record->delete();
								return true;
							}
							else
							{
								// else return its existence or its value
								return $isset ? true : $record->{$val};
							}
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * EAV attribute setter
	 *
	 * @param   string $attribute
	 * @param   string $value
	 *
	 * @throws \OutOfBoundsException
	 *
	 * @return  mixed
	 */
	protected function _set_eav($attribute, $value)
	{
		// get the current class name
		$class = get_called_class();

		// don't do anything unless we actually have an EAV container
		if (property_exists($class, '_eav'))
		{
			// loop through the defined EAV containers
			foreach (static::$_eav as $rel => $settings)
			{
				// normalize the container definition, could be string or array
				if (is_string($settings))
				{
					$rel = $settings;
					$settings = array();
				}

				// fetch the relation object for this EAV container
				if ( ! $relation = static::relations($rel))
				{
					throw new \OutOfBoundsException('EAV container defines a relation that does not exist in '.get_class($this).'.');
				}

				// EAV containers must be of the "Many type"
				if ($relation instanceOf \Orm\HasOne or $relation instanceOf \Orm\BelongsTo)
				{
					throw new \OutOfBoundsException('EAV containers can only be defined on "HasMany" or "ManyMany" relations in '.get_class($this).'.');
				}

				// determine attribute and value column names
				$attr = isset($settings['attribute']) ? $settings['attribute'] : 'attribute';
				$val = isset($settings['value']) ? $settings['value'] : 'value';

				// loop over the resultset
				foreach ($this->{$relation->name} as $key => $record)
				{
					if ($record->{$attr} === $attribute)
					{
						$record->{$val} = $value;
						return true;
					}
				}

				// not found, we've got outselfs a new attribute, so add it
				if ($rel = static::related_class($rel))
				{
					$this->{$relation->name}[] = $rel::forge(array(
						$attr => $attribute,
						$val => $value,
					));
					return true;
				}
			}
		}

		return false;
	}

	/***************************************************************************
	 * Implementation of ArrayAccess
	 **************************************************************************/

	public function offsetSet($offset, $value)
	{
		try
		{
			$this->__set($offset, $value);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	public function offsetExists($offset)
	{
		return $this->__isset($offset);
	}

	public function offsetUnset($offset)
	{
		$this->__unset($offset);
	}

	public function offsetGet($offset)
	{
		try
		{
			return $this->__get($offset);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/***************************************************************************
	 * Implementation of Iterable
	 **************************************************************************/

	protected $_iterable = array();

	public function rewind()
	{
		$this->_iterable = array_merge($this->_custom_data, $this->_data, $this->_data_relations);
		reset($this->_iterable);
	}

	public function current()
	{
		return current($this->_iterable);
	}

	public function key()
	{
		return key($this->_iterable);
	}

	public function next()
	{
		return next($this->_iterable);
	}

	public function valid()
	{
		return key($this->_iterable) !== null;
	}

}
