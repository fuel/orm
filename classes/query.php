<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.9-dev
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

namespace Orm;

/**
 * ORM query object.
 */
class Query
{
	/**
	 * @var  bool  switch to globally enable/disable object caching
	 */
	protected static $caching = null;


	/**
	 * Load the ORM config file
	 */
	public static function _init()
	{
		// load the config
		\Config::load('orm', true);

		// update the caching flag if defined
		static::$caching = \Config::get('orm.caching', null);
	}

	/**
	 * Enables or disables the default state of the object cache
	 *
	 * @param  bool  $cache    Whether or not to use the object cache by default
	 *
	 * @return  Query
	 */
	public static function caching($cache = true)
	{
		static::$caching = (bool) $cache;
	}

	/**
	 * Create a new instance of the Query class.
	 *
	 * @param	string  $model      name of the model this instance has to operate on
	 * @param	mixed   $connection DB connection to use to run the query
	 * @param	array   $options    any options to pass on to the query
	 *
	 * @return	Query	newly created instance
	 */
	public static function forge($model, $connection = null, $options = array())
	{
		return new static($model, $connection, $options);
	}

	/**
	 * @var  string  classname of the model
	 */
	protected $model;

	/**
	 * @var  null|string  connection name to use
	 */
	protected $connection;

	/**
	 * @var  null|string  connection name to use for writes
	 */
	protected $write_connection;

	/**
	 * @var  array  database view to use with keys 'view' and 'columns'
	 */
	protected $view;

	/**
	 * @var  string  table alias
	 */
	protected $alias = 't0';

	/**
	 * @var  array  relations to join on
	 */
	protected $relations = array();

	/**
	 * @var  array  tables to join without returning any info
	 */
	protected $joins = array();

	/**
	 * @var  array  fields to select
	 */
	protected $select = array();

	/**
	 * @var  int  max number of returned base model instances
	 */
	protected $limit;

	/**
	 * @var  int  offset of base model table
	 */
	protected $offset;

	/**
	 * @var  int  max number of requested rows
	 */
	protected $rows_limit;

	/**
	 * @var  int  offset of requested rows
	 */
	protected $rows_offset;

	/**
	 * @var  array  where conditions
	 */
	protected $where = array();

	/**
	 * @var  array  order by clauses
	 */
	protected $order_by = array();

	/**
	 * @var  array  group by clauses
	 */
	protected $group_by = array();

	/**
	 * @var  array  having clauses
	 */
	protected $having = array();

	/**
	 * @var  array  values for insert or update
	 */
	protected $values = array();

	/**
	 * @var  array  select filters
	 */
	protected $select_filter = array();

	/**
	 * @var  bool  whether or not to retrieve a cached object
	 */
	protected $from_cache = true;

	/**
	 * Create a new instance of the Query class.
	 *
	 * @param	string  $model        Name of the model this instance has to operate on
	 * @param	mixed   $connection   DB connection to use to run the query
	 * @param	array   $options      Any options to pass on to the query
	 * @param	mixed   $table_alias  Optionally, the alias to use for the models table
	 */
	protected function __construct($model, $connection, $options, $table_alias = null)
	{
		if ( ! is_null(static::$caching))
		{
				$this->from_cache = (bool) static::$caching;
		}

		$this->model = $model;

		if (is_array($connection))
		{
			list($this->connection, $this->write_connection) = $connection;
		}
		else
		{
			$this->connection = $connection;
			$this->write_connection = $connection;
		}

		foreach ($options as $opt => $val)
		{
			switch ($opt)
			{
				case 'select':
					$val = (array) $val;
					call_fuel_func_array(array($this, 'select'), $val);
					break;
				case 'related':
					$val = (array) $val;
					$this->related($val);
					break;
				case 'use_view':
					$this->use_view($val);
					break;
				case 'or_where':
					$this->and_where_open();
					foreach ($val as $where)
					{
						call_fuel_func_array(array($this, '_where'), array($where, 'or_where'));
					}
					$this->and_where_close();
					break;
				case 'where':
					$this->_parse_where_array($val);
					break;
				case 'order_by':
					$val = (array) $val;
					$this->order_by($val);
					break;
				case 'group_by':
					call_fuel_func_array(array($this, 'group_by'), $val);
					break;
				case 'limit':
					$this->limit($val);
					break;
				case 'offset':
					$this->offset($val);
					break;
				case 'rows_limit':
					$this->rows_limit($val);
					break;
				case 'rows_offset':
					$this->rows_offset($val);
					break;
				case 'from_cache':
					$this->from_cache($val);
					break;
			}
		}
	}

	/**
	 * Does the work for where() and or_where()
	 *
	 * @param   array   $condition
	 * @param   string  $type
	 *
	 * @throws \FuelException
	 *
	 * @return  $this
	 */
	public function _where($condition, $type = 'and_where')
	{
		if (is_array(reset($condition)) or is_string(key($condition)))
		{
			foreach ($condition as $k_c => $v_c)
			{
				is_string($k_c) and $v_c = array($k_c, $v_c);
				$this->_where($v_c, $type);
			}
			return $this;
		}

		// prefix table alias when not yet prefixed and not a DB expression object
		if (strpos($condition[0], '.') === false and ! $condition[0] instanceof \Fuel\Core\Database_Expression)
		{
			$condition[0] = $this->alias.'.'.$condition[0];
		}

		if (count($condition) == 2)
		{
			$this->where[] = array($type, array($condition[0], '=', $condition[1]));
		}
		elseif (count($condition) == 3 or $condition[0] instanceof \Fuel\Core\Database_Expression)
		{
			$this->where[] = array($type, $condition);
		}
		else
		{
			throw new \FuelException('Invalid param count for where condition.');
		}

		return $this;
	}

	/**
	 * Does the work for having() and or_having()
	 *
	 * @param   array   $condition
	 * @param   string  $type
	 *
	 * @throws \FuelException
	 *
	 * @return  $this
	 */
	public function _having($condition, $type = 'and_having')
	{
		if (is_array(reset($condition)) or is_string(key($condition)))
		{
			foreach ($condition as $k_c => $v_c)
			{
				is_string($k_c) and $v_c = array($k_c, $v_c);
				$this->_having($v_c, $type);
			}
			return $this;
		}

		// prefix table alias when not yet prefixed and not a DB expression object
		if (strpos($condition[0], '.') === false and ! $condition[0] instanceof \Fuel\Core\Database_Expression)
		{
			$condition[0] = $this->alias.'.'.$condition[0];
		}

		if (count($condition) == 2)
		{
			$this->having[] = array($type, array($condition[0], '=', $condition[1]));
		}
		elseif (count($condition) == 3 or $condition[0] instanceof \Fuel\Core\Database_Expression)
		{
			$this->having[] = array($type, $condition);
		}
		else
		{
			throw new \FuelException('Invalid param count for having condition.');
		}

		return $this;
	}

	/**
	 * Parses an array of where conditions into the query
	 *
	 * @param   array   $val
	 * @param   string  $base
	 * @param   bool    $or
	 */
	public function _parse_where_array(array $val, $base = '', $or = false)
	{
		$or and $this->or_where_open();
		foreach ($val as $k_w => $v_w)
		{
			if (is_array($v_w) and ! empty($v_w[0]) and (is_string($v_w[0]) or $v_w[0] instanceof \Database_Expression))
			{
				! $v_w[0] instanceof \Database_Expression and strpos($v_w[0], '.') === false and $v_w[0] = $base.$v_w[0];
				call_fuel_func_array(array($this, ($k_w === 'or' ? 'or_' : '').'where'), $v_w);
			}
			elseif (is_int($k_w) or $k_w == 'or')
			{
				$k_w === 'or' ? $this->or_where_open() : $this->where_open();
				$this->_parse_where_array($v_w, $base, $k_w === 'or');
				$k_w === 'or' ? $this->or_where_close() : $this->where_close();
			}
			else
			{
				! $k_w instanceof \Database_Expression and strpos($k_w, '.') === false and $k_w = $base.$k_w;
				$this->where($k_w, $v_w);
			}
		}
		$or and $this->or_where_close();
	}

	/**
	/* normalize the select fields passed
	 *
	 * @param  array  list of columns to select
	 * @param  int    counter of the number of selected columnss
	 */
	protected function _normalize($fields, &$i)
	{
		$select = array();

		// for BC reasons, deal with the odd array(DB::expr, 'name') syntax first
		if (($value = reset($fields)) instanceOf \Fuel\Core\Database_Expression and is_string($index = next($fields)))
		{
			$select[$this->alias.'_c'.$i++] = $fields;
		}

		// otherwise iterate
		else
		{
			foreach ($fields as $index => $value)
			{
				// an array of field definitions is passed
				if (is_array($value))
				{
					// recurse and add them individually
					$select = array_merge($select, $this->_normalize($value, $i));
				}

				// a "field -> include" value pair is passed
				elseif (is_bool($value))
				{
					if ($value)
					{
						// if include is true, add the field
						$select[$this->alias.'_c'.$i++] = (strpos($index, '.') === false ? $this->alias.'.' : '').$index;
					}
					else
					{
						// if not, add it to the filter list
						if ( ! in_array($index, $this->select_filter))
						{
							$this->select_filter[] = $index;
						}
					}
				}

				// attempted a "SELECT *"?
				elseif ($value === '*')
				{
					// recurse and add all model properties
					$select = array_merge($select, $this->_normalize(array_keys(call_user_func($this->model.'::properties')), $i));
				}

				// DB::expr() passed?
				elseif ($value instanceOf \Fuel\Core\Database_Expression)
				{
					// no column name given for the result?
					if (is_numeric($index))
					{
						$select[$this->alias.'_c'.$i++] = array($value);
					}

					// add the index as the column name
					else
					{
						$select[$this->alias.'_c'.$i++] = array($value, $index);
					}
				}

				// must be a regular field
				else
				{
					$select[$this->alias.'_c'.$i++] = (strpos($value, '.') === false ? $this->alias.'.' : '').$value;
				}
			}
		}

		return $select;
	}

	/**
	 * Returns target table (or view, if specified).
	 */
	protected function _table()
	{
		return $this->view ? $this->view['view'] : call_user_func($this->model.'::table');
	}

	/**
	 * Sets the name of the connection to use for this query. Set to null to use the default DB connection
	 *
	 * @param string $name
	 */
	public function connection($name)
	{
		$this->connection = $name;
		return $this;
	}

	/**
	 * Enables or disables the object cache for this query
	 *
	 * @param  bool  $cache    Whether or not to use the object cache on this query
	 *
	 * @return  Query
	 */
	public function from_cache($cache = true)
	{
		$this->from_cache = (bool) $cache;

		return $this;
	}

	/**
	 * Select which properties are included, each as its own param. Or don't give input to retrieve
	 * the current selection.
	 *
	 * @param bool          $add_pks    Whether or not to add the Primary Keys to the list of selected columns
	 * @param string|array  $fields     Optionally. Which field/fields must be retrieved
	 *
	 * @throws \FuelException No properties found in model
	 *
	 * @return  void|array
	 */
	public function select($add_pks = true)
	{
		$fields = func_get_args();

		if (empty($fields) or is_bool($add_pks))
		{
			if (empty($this->select))
			{
				$fields = array_keys(call_user_func($this->model.'::properties'));

				if (empty($fields))
				{
					throw new \FuelException('No properties found in model.');
				}
				foreach ($fields as $field)
				{
					in_array($field, $this->select_filter) or $this->select($field);
				}

				if ($this->view)
				{
					foreach ($this->view['columns'] as $field)
					{
						$this->select($field);
					}
				}
			}

			// backup select before adding PKs
			$select = $this->select;

			// ensure all PKs are being selected
			if ($add_pks)
			{
				$pks = call_user_func($this->model.'::primary_key');
				foreach($pks as $pk)
				{
					if ( ! in_array($this->alias.'.'.$pk, $this->select))
					{
						$this->select($pk);
					}
				}
			}

			// convert selection array for DB class
			$out = array();
			foreach($this->select as $k => $v)
			{
				if (is_array($v))
				{
					if (count($v) === 1)
					{
						$out[] = array($v[0], $k);
					}
					else
					{
						$out[$v[1]] = array($v[0], $k);
					}
				}
				else
				{
					$out[] = array($v, $k);
				}
			}

			// set select back to before the PKs were added
			$this->select = $select;

			return $out;
		}

		// get the current select count
		$i = count($this->select);

		// parse the passed fields list
		$this->select = array_merge($this->select, $this->_normalize($fields, $i));

		return $this;
	}

	/**
	 * Set a view to use instead of the table
	 *
	 * @param  string   $view   Name of view which you want to use
	 *
	 * @throws \OutOfBoundsException Cannot use undefined database view, must be defined with Model
	 *
	 * @return  Query
	 */
	public function use_view($view)
	{
		$views = call_user_func(array($this->model, 'views'));
		if ( ! array_key_exists($view, $views))
		{
			throw new \OutOfBoundsException('Cannot use undefined database view, must be defined with Model.');
		}

		$this->view = $views[$view];
		$this->view['_name'] = $view;
		return $this;
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   $coulmns    Column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function group_by()
	{
		$columns = func_get_args();

		$this->group_by = array_merge($this->group_by, $columns);

		return $this;
	}

	/**
	 * Set the limit
	 *
	 * @param   int $limit
	 *
	 * @return  $this
	 */
	public function limit($limit)
	{
		$this->limit = intval($limit);

		return $this;
	}

	/**
	 * Set the offset
	 *
	 * @param   int $offset
	 *
	 * @return  $this
	 */
	public function offset($offset)
	{
		$this->offset = intval($offset);

		return $this;
	}

	/**
	 * Set the limit of rows requested
	 *
	 * @param   int $limit
	 *
	 * @return  $this
	 */
	public function rows_limit($limit)
	{
		$this->rows_limit = intval($limit);

		return $this;
	}

	/**
	 * Set the offset of rows requested
	 *
	 * @param   int $offset
	 *
	 * @return  $this
	 */
	public function rows_offset($offset)
	{
		$this->rows_offset = intval($offset);

		return $this;
	}

	/**
	 * Set where condition
	 *
	 * @param   string  Property
	 * @param   string  Comparison type (can be omitted)
	 * @param   string  Comparison value
	 *
	 * @return  $this
	 */
	public function where()
	{
		$condition = func_get_args();
		is_array(reset($condition)) and $condition = reset($condition);

		return $this->_where($condition);
	}

	/**
	 * Set or_where condition
	 *
	 * @param   string  Property
	 * @param   string  Comparison type (can be omitted)
	 * @param   string  Comparison value
	 *
	 * @return  $this
	 */
	public function or_where()
	{
		$condition = func_get_args();
		is_array(reset($condition)) and $condition = reset($condition);

		return $this->_where($condition, 'or_where');
	}

	/**
	 * Open a nested and_where condition
	 *
	 * @return  $this
	 */
	public function and_where_open()
	{
		$this->where[] = array('and_where_open', array());

		return $this;
	}

	/**
	 * Close a nested and_where condition
	 *
	 * @return  $this
	 */
	public function and_where_close()
	{
		$this->where[] = array('and_where_close', array());

		return $this;
	}

	/**
	 * Alias to and_where_open()
	 *
	 * @return  $this
	 */
	public function where_open()
	{
		$this->where[] = array('and_where_open', array());

		return $this;
	}

	/**
	 * Alias to and_where_close()
	 *
	 * @return  $this
	 */
	public function where_close()
	{
		$this->where[] = array('and_where_close', array());

		return $this;
	}

	/**
	 * Open a nested or_where condition
	 *
	 * @return  $this
	 */
	public function or_where_open()
	{
		$this->where[] = array('or_where_open', array());

		return $this;
	}

	/**
	 * Close a nested or_where condition
	 *
	 * @return  $this
	 */
	public function or_where_close()
	{
		$this->where[] = array('or_where_close', array());

		return $this;
	}

	/**
	 * Set having condition
	 *
	 * @param   string  Property
	 * @param   string  Comparison type (can be omitted)
	 * @param   string  Comparison value
	 *
	 * @return  $this
	 */
	public function having()
	{
		$condition = func_get_args();
		is_array(reset($condition)) and $condition = reset($condition);

		return $this->_having($condition);
	}

	/**
	 * Set or_having condition
	 *
	 * @param   string  Property
	 * @param   string  Comparison type (can be omitted)
	 * @param   string  Comparison value
	 *
	 * @return  $this
	 */
	public function or_having()
	{
		$condition = func_get_args();
		is_array(reset($condition)) and $condition = reset($condition);

		return $this->_having($condition, 'or_having');
	}

	/**
	 * Open a nested and_having condition
	 *
	 * @return  $this
	 */
	public function and_having_open()
	{
		$this->having[] = array('and_having_open', array());

		return $this;
	}

	/**
	 * Close a nested and_where condition
	 *
	 * @return  $this
	 */
	public function and_having_close()
	{
		$this->having[] = array('and_having_close', array());

		return $this;
	}

	/**
	 * Alias to and_having_open()
	 *
	 * @return  $this
	 */
	public function having_open()
	{
		$this->having[] = array('and_having_open', array());

		return $this;
	}

	/**
	 * Alias to and_having_close()
	 *
	 * @return  $this
	 */
	public function having_close()
	{
		$this->having[] = array('and_having_close', array());

		return $this;
	}

	/**
	 * Open a nested or_having condition
	 *
	 * @return  $this
	 */
	public function or_having_open()
	{
		$this->having[] = array('or_having_open', array());

		return $this;
	}

	/**
	 * Close a nested or_where condition
	 *
	 * @return  $this
	 */
	public function or_having_close()
	{
		$this->having[] = array('or_having_close', array());

		return $this;
	}

	/**
	 * Set the order_by
	 *
	 * @param   string|array  $property
	 * @param   string        $direction
	 *
	 * @return  $this
	 */
	public function order_by($property, $direction = 'ASC')
	{
		if (is_array($property))
		{
			foreach ($property as $p => $d)
			{
				if (is_int($p))
				{
					is_array($d) ? $this->order_by($d[0], $d[1]) : $this->order_by($d, $direction);
				}
				else
				{
					$this->order_by($p, $d);
				}
			}
			return $this;
		}

		// prefix table alias when not yet prefixed and not a DB expression object
		if ( ! $property instanceof \Fuel\Core\Database_Expression and strpos($property, '.') === false)
		{
			$property = $this->alias.'.'.$property;
		}

		$this->order_by[] = array($property, $direction);

		return $this;
	}

	/**
	 * Set a relation to include
	 *
	 * @param   string  $relation
	 * @param   array   $conditions    Optionally
	 *
	 * @throws \UnexpectedValueException Relation was not found in the model
	 *
	 * @return  $this
	 */
	public function related($relation, $conditions = array())
	{
		if (is_array($relation))
		{
			foreach ($relation as $k_r => $v_r)
			{
				is_array($v_r) ? $this->related($k_r, $v_r) : $this->related($v_r);
			}
			return $this;
		}

		if (strpos($relation, '.'))
		{
			$rels = explode('.', $relation);
			$model = $this->model;
			foreach ($rels as $r)
			{
				$rel = call_user_func(array($model, 'relations'), $r);
				if (empty($rel))
				{
					throw new \UnexpectedValueException('Relation "'.$r.'" was not found in the model "'.$model.'".');
				}
				$model = $rel->model_to;
			}
		}
		else
		{
			$rel = call_user_func(array($this->model, 'relations'), $relation);
			if (empty($rel))
			{
				throw new \UnexpectedValueException('Relation "'.$relation.'" was not found in the model.');
			}
		}

		$this->relations[$relation] = array($rel, $conditions);

		if ( ! empty($conditions['related']))
		{
			$conditions['related'] = (array) $conditions['related'];
			foreach ($conditions['related'] as $k_r => $v_r)
			{
				is_array($v_r) ? $this->related($relation.'.'.$k_r, $v_r) : $this->related($relation.'.'.$v_r);
			}

			unset($conditions['related']);
		}

		// avoid UnexpectedValue exceptions due to incorrect order
		ksort($this->relations);

		return $this;
	}

	/**
	 * Add a table to join, consider this a protect method only for Orm package usage
	 *
	 * @param   array   $join
	 *
	 * @return  $this
	 */
	public function _join(array $join)
	{
		$this->joins[] = $join;

		return $this;
	}

	/**
	 * Set any properties for insert or update
	 *
	 * @param   string|array  $property
	 * @param   mixed         $value    Optionally
	 *
	 * @return  $this
	 */
	public function set($property, $value = null)
	{
		if (is_array($property))
		{
			foreach ($property as $p => $v)
			{
				$this->set($p, $v);
			}
			return $this;
		}

		$this->values[$property] = $value;

		return $this;
	}

	/**
	 * Build a select, delete or update query
	 *
	 * @param   \Fuel\Core\Database_Query_Builder_Where  DB where() query object
	 * @param   array $columns  Optionally
	 * @param   string $type    Type of query to build (count/select/update/delete/insert)
	 *
	 * @throws \FuelException            Models cannot be related between different database connections
	 * @throws \UnexpectedValueException Trying to get the relation of an unloaded relation
	 *
	 * @return  array          with keys query and relations
	 */
	public function build_query(\Fuel\Core\Database_Query_Builder_Where $query, $columns = array(), $type = 'select')
	{
		// Are we generating a read or a write query?
		$read_query = ! in_array($type, array('insert', 'update', 'delete'));

		// Get the limit
		if ( ! is_null($this->limit))
		{
			$query->limit($this->limit);
		}

		// Get the offset
		if ( ! is_null($this->offset))
		{
			$query->offset($this->offset);
		}

		$where_conditions = call_user_func($this->model.'::condition', 'where');
		empty($where_conditions) or $this->_parse_where_array($where_conditions);

		$where_backup = $this->where;
		if ( ! empty($this->where))
		{
			$open_nests = 0;
			$where_nested = array();
			$include_nested = true;
			foreach ($this->where as $key => $w)
			{
				list($method, $conditional) = $w;

				if ($read_query and (empty($conditional) or $open_nests > 0))
				{
					$include_nested and $where_nested[$key] = $w;
					if ( ! empty($conditional) and strpos($conditional[0], $this->alias.'.') !== 0)
					{
						$include_nested = false;
					}
					strpos($method, '_open') and $open_nests++;
					strpos($method, '_close') and $open_nests--;
					continue;
				}

				if (empty($conditional)
					or strpos($conditional[0], $this->alias.'.') === 0
					or ( ! $read_query and $conditional[0] instanceof \Fuel\Core\Database_Expression))
				{
					if ( ! $read_query and ! empty($conditional)
						and ! $conditional[0] instanceof \Fuel\Core\Database_Expression)
					{
						$conditional[0] = substr($conditional[0], strlen($this->alias.'.'));
					}
					call_fuel_func_array(array($query, $method), $conditional);
					unset($this->where[$key]);
				}
			}

			if ($include_nested and ! empty($where_nested))
			{
				foreach ($where_nested as $key => $w)
				{
					list($method, $conditional) = $w;

					if (empty($conditional)
						or strpos($conditional[0], $this->alias.'.') === 0
						or ( ! $read_query and $conditional[0] instanceof \Fuel\Core\Database_Expression))
					{
						if ( ! $read_query and ! empty($conditional)
							and ! $conditional[0] instanceof \Fuel\Core\Database_Expression)
						{
							$conditional[0] = substr($conditional[0], strlen($this->alias.'.'));
						}
						call_fuel_func_array(array($query, $method), $conditional);
						unset($this->where[$key]);
					}
				}
			}
		}

		// If it's a write query, we're done
		if ( ! $read_query)
		{
			return array('query' => $query, 'models' => array());
		}

		// Alias number counter
		$i = 1;

		// Add defined relations
		$models = array();

		foreach ($this->relations as $name => $rel)
		{
			// when there's a dot it must be a nested relation
			if ($pos = strrpos($name, '.'))
			{
				if (empty($models[substr($name, 0, $pos)]['table'][1]))
				{
					throw new \UnexpectedValueException('Trying to get the relation of an unloaded relation, make sure you load the parent relation before any of its children.');
				}

				$alias = $models[substr($name, 0, $pos)]['table'][1];
			}
			else
			{
				$alias = $this->alias;
			}

			$join = $rel[0]->join($alias, $name, $i++, $rel[1]);
			$models = array_merge($models, $this->modify_join_result($join, $name));
		}

		// if no order_by was given, see if a default was defined in the model
		empty($this->order_by) and $this->order_by(call_user_func($this->model.'::condition', 'order_by'));

		if ($this->use_subquery())
		{
			// Count queries should not select on anything besides the count
			if ($type != 'count')
			{
				// Get the columns for final select
				foreach ($models as $m)
				{
					foreach ($m['columns'] as $c)
					{
						$columns[] = $c;
					}
				}
			}

			// do we need to add order_by clauses on the subquery?
			foreach ($this->order_by as $idx => $ob)
			{
				if ( ! $ob[0] instanceof \Fuel\Core\Database_Expression)
				{
					if (strpos($ob[0], $this->alias.'.') === 0)
					{
						// order by on the current model
						$query->order_by($ob[0], $ob[1]);
					}
				}
			}

			// make current query subquery of ultimate query
			$new_query = call_fuel_func_array('DB::select', array_values($columns));
			$query = $new_query->from(array($query, $this->alias));
		}
		else
		{
			// Count queries should not select on anything besides the count
			if ($type != 'count')
			{
				// add additional selected columns
				foreach ($models as $m)
				{
					foreach ($m['columns'] as $c)
					{
						$query->select($c);
					}
				}
			}
		}

		// join tables
		foreach ($this->joins as $j)
		{
			$join_query = $query->join($j['table'], $j['join_type']);
			foreach ($j['join_on'] as $on)
			{
				$join_query->on($on[0], $on[1], $on[2]);
			}
		}
		foreach ($models as $m)
		{
			if ($m['connection'] != $this->connection)
			{
				throw new \FuelException('Models cannot be related between different database connections.');
			}

			$join_query = $query->join($m['table'], $m['join_type']);
			foreach ($m['join_on'] as $on)
			{
				$join_query->on($on[0], $on[1], $on[2]);
			}
		}

		// Get the order, if none set see if we have an order_by condition set
		$order_by = $order_by_backup = $this->order_by;

		// Add any additional order_by and where clauses from the relations
		foreach ($models as $m_name => $m)
		{
			if ( ! empty($m['order_by']))
			{
				foreach ((array) $m['order_by'] as $k_ob => $v_ob)
				{
					if (is_int($k_ob))
					{
						$v_dir = is_array($v_ob) ? $v_ob[1] : 'ASC';
						$v_ob = is_array($v_ob) ? $v_ob[0] : $v_ob;
						if ( ! $v_ob instanceof \Fuel\Core\Database_Expression and strpos($v_ob, '.') === false)
						{
							$v_ob = $m_name.'.'.$v_ob;
						}

						$order_by[] = array($v_ob, $v_dir);
					}
					else
					{
						strpos($k_ob, '.') === false and $k_ob = $m_name.'.'.$k_ob;
						$order_by[] = array($k_ob, $v_ob);
					}
				}
			}
			if ( ! empty($m['where']))
			{
				$this->_parse_where_array($m['where'], $m_name.'.');
			}
		}

		// Get the order
		if ( ! empty($order_by))
		{
			foreach ($order_by as $ob)
			{
				if ( ! $ob[0] instanceof \Fuel\Core\Database_Expression)
				{
					if (strpos($ob[0], $this->alias.'.') === 0)
					{
						// get the field name
						$fn = substr($ob[0], strlen($this->alias.'.'));

						// if not a a model property?
						if ( ! call_fuel_func_array(array($this->model, 'property'), array($fn)))
						{
							// and it's not an alias?
							foreach ($this->select as $salias => $sdef)
							{
								// if the fieldname matches
								if (is_array($sdef) and $sdef[1] == $fn)
								{
									// order on it's alias instead
									$ob[0] = $salias;
									break;
								}
							}
						}
					}
					else
					{
						// try to rewrite conditions on the relations to their table alias
						$dotpos = strrpos($ob[0], '.');
						$relation = substr($ob[0], 0, $dotpos);
						if ($dotpos > 0 and array_key_exists($relation, $models))
						{
							$ob[0] = $models[$relation]['table'][1].substr($ob[0], $dotpos);
						}
					}
				}
				$query->order_by($ob[0], $ob[1]);
			}
		}

		// Get the grouping
		if ( ! empty($this->group_by))
		{
			foreach ($this->group_by as $gb)
			{
				if ( ! $gb instanceof \Fuel\Core\Database_Expression)
				{
					if (strpos($gb, $this->alias.'.') === false)
					{
						// try to rewrite on the relations to their table alias
						$dotpos = strrpos($gb, '.');
						$relation = substr($gb, 0, $dotpos);
						if ($dotpos > 0)
						{
							if(array_key_exists($relation, $models))
							{
								$gb = $models[$relation]['table'][1].substr($gb, $dotpos);
							}
						}
						else
						{
							$gb = $this->alias.'.'.$gb;
						}
					}
				}
				$query->group_by($gb);
			}
		}

		// Add any having filters
		if ( ! empty($this->having))
		{
			foreach ($this->having as $h)
			{
				list($method, $conditional) = $h;

				// try to rewrite conditions on the relations to their table alias
				if ( ! empty($conditional) and is_array($conditional))
				{
					$dotpos = strrpos($conditional[0], '.');
					$relation = substr($conditional[0], 0, $dotpos);
					if ($dotpos > 0 and array_key_exists($relation, $models))
					{
						$conditional[0] = $models[$relation]['table'][1].substr($conditional[0], $dotpos);
					}
				}

				call_fuel_func_array(array($query, $method), $conditional);
			}
		}

		// put omitted where conditions back
		if ( ! empty($this->where))
		{
			foreach ($this->where as $w)
			{
				list($method, $conditional) = $w;

				// try to rewrite conditions on the relations to their table alias
				if ( ! empty($conditional) and is_array($conditional))
				{
					$dotpos = strrpos($conditional[0], '.');
					$relation = substr($conditional[0], 0, $dotpos);
					if ($dotpos > 0 and array_key_exists($relation, $models))
					{
						$conditional[0] = $models[$relation]['table'][1].substr($conditional[0], $dotpos);
					}
				}

				call_fuel_func_array(array($query, $method), $conditional);
			}
		}

		$this->where = $where_backup;
		$this->order_by = $order_by_backup;

		// Set the row limit and offset, these are applied to the outer query when a subquery
		// is used or overwrite limit/offset when it's a normal query
		! is_null($this->rows_limit) and $query->limit($this->rows_limit);
		! is_null($this->rows_offset) and $query->offset($this->rows_offset);

		return array('query' => $query, 'models' => $models);
	}

	/**
	 * Allows subclasses to make changes to the join information before it is used
	 */
	protected function modify_join_result($join_result, $name)
	{
		return $join_result;
	}

	/**
	 * Determines whether a subquery is needed, is the case if there was a limit/offset on a join
	 *
	 * @return  bool
	 */
	public function use_subquery()
	{
		return ( ! empty($this->relations) and ( ! empty($this->limit) or ! empty($this->offset)));
	}

	/**
	 * Build the query and return hydrated results
	 *
	 * @return  array
	 */
	public function get()
	{
		// closure to convert field name to column name
		$field_to_column = function($fields, $alias) {
			// storage for the result
			$result = array();

			// process the columns
			foreach ($fields as $key => $value)
			{
				// check for db expressions
				if (is_array($value))
				{
					if ($value[0] instanceOf \Fuel\Core\Database_Expression)
					{
						$result[$value[1]] = $key;
					}
					else
					{
						$result[$value[1]] = substr($value[0], strlen($alias)+1);
					}
				}
				else
				{
					$result[$key] = substr($value, strlen($alias)+1);
				}
			}

			return $result;
		};

		// Get the columns in this query
		$columns = $this->select();

		// Start building the query
		$select = $columns;
		if ($this->use_subquery())
		{
			$select = array();
			foreach ($columns as $c)
			{
				$select[] = $c[0];
			}
		}

		$query = call_fuel_func_array('DB::select', array_values($select));

		// Set from view/table
		$query->from(array($this->_table(), $this->alias));

		// Build the query further
		$tmp     = $this->build_query($query, $columns);
		$query   = $tmp['query'];
		$models  = $tmp['models'];

		// list of models expected in the resulting rows
		$qmodels = array($this->alias => array(
			'model' => $this->model,
			'pk' => $this->model::primary_key(),
			'columns' => $field_to_column($columns, $this->alias),
			'relation' => null,
			'singular' => true,
		));

		// Make models hierarchical
		foreach ($models as $name => $values)
		{
			// add the model to the list
			if ($values['model'])
			{
				$qmodels[$values['table'][1]] = array(
					'model' => $values['model'],
					'pk' => $values['model']::primary_key(),
					'columns' => $field_to_column($values['columns'], $values['table'][1]),
					'relation' => $name,
					'singular' => $values['relation']->is_singular(),
				);
			}
		}

		// fetch the result
		$rows = $query->execute($this->connection)->as_array();

		// storage for the fimal result
		$result = array();

		// process the result
		foreach ($rows as $id => $row)
		{
			$this->process_row($row, $qmodels, $result);
		}
		// free up some memory
		unset($rows);

		// convert the result into objects
		$objects = array();
		foreach ($result as $key => $record)
		{
			if (is_array($record))
			{
				$objects[$key] = $this->model::forge($record, false, $this->view ? $this->view['_name'] : null, $this->from_cache);
			}
			else
			{
				$objects[$key] = $record;
			}

			// free up some memory
			unset($result[$key]);
		}

		return $objects;
	}

	/**
	 * Process the retrieved data, convert rows to a hierarchical data structure
-	 *
-	 * @param   array     $row     Row from the database
-	 * @param   array     $models  Relations to be expected
-	 * @param   array     &$result array to accumulate the processed results in
-	 */
	public function process_row($row, $models, &$result)
	{
		// PK trackers
		$pointers = array();

		// process the models in the result row
		foreach ($models as $alias => $model)
		{
			// storage for extracting current record
			$record = array();

			// get this models data from the row
			foreach ($row as $column => $value)
			{
				// check if this coulumn belongs to this model
				if (array_key_exists($column, $model['columns']))
				{
					// get the true column name
					$column = $model['columns'][$column];

					// is it a (part of a) primary key?
					if (in_array($column, $model['pk']))
					{
						// typecast the pk value
						$value = Observer_Typing::typecast($column, $value, call_user_func($model['model'].'::property', $column));
					}

					// store the value
					$record[$column] = $value;
				}
			}

			// determine the PK for this record
			$pk = $model['model']::implode_pk($record);

			// skip the rest if we don't have a pk (= no result)
			if (is_null($pk))
			{
				continue;
			}

			// determine the current relation name and parent record pointer
			$parent = explode('.', strrev($model['relation']), 2);
			$current = strrev($parent[0]);
			$parent = isset($parent[1]) ? strrev($parent[1]) : null;

			// is the current record a root record in this query?
			if (empty($current))
			{
				isset($result[$pk]) or $result[$pk] = $record;
				$pointers[null] =& $result[$pk];
			}

			// nope, it is a related record
			else
			{
				// store it, but do not overwrite an existing result!
				if ($model['singular'] === true)
				{
					if ( ! array_key_exists($current, $pointers[$parent]))
					{
						$pointers[$parent][$current] = $record;
					}
					else
					{
						// do not overwrite the existing entry
					}
				}
				else
				{
					if ( ! array_key_exists($current, $pointers[$parent]))
					{
						$pointers[$parent][$current] = array($pk => $record);
					}
					else
					{
						isset($pointers[$parent][$current][$pk]) or $pointers[$parent][$current][$pk] = $record;
					}
				}
				$pointers[$model['relation']] = $pointers[$parent][$current];
			}
		}
	}

	/**
	 * Get the Query as it's been build up to this point and return it as an object
	 *
	 * @return  Database_Query
	 */
	public function get_query()
	{
		// Get the columns
		$columns = $this->select(false);

		// Start building the query
		$select = $columns;
		if ($this->use_subquery())
		{
			$select = array();
			foreach ($columns as $c)
			{
				$select[] = $c[0];
			}
		}
		$query = call_fuel_func_array('DB::select', array_values($select));

		// Set the defined connection on the query
		$query->set_connection($this->connection);

		// Set from table
		$query->from(array($this->_table(), $this->alias));

		// Build the query further
		$tmp = $this->build_query($query, $columns);

		return $tmp['query'];
	}

	/**
	 * Build the query and return single object hydrated
	 *
	 * @return  Model
	 */
	public function get_one()
	{
		// save the current limits
		$limit = $this->limit;
		$rows_limit = $this->rows_limit;

		if ($this->rows_limit !== null)
		{
			$this->limit = null;
			$this->rows_limit = 1;
		}
		else
		{
			$this->limit = 1;
			$this->rows_limit = null;
		}

		// get the result using normal find
		$result = $this->get();

		// put back the old limits
		$this->limit = $limit;
		$this->rows_limit = $rows_limit;

		return $result ? reset($result) : null;
	}

	/**
	 * Count the result of a query
	 *
	 * @param   bool  $column   False for random selected column or specific column, only works for main model currently
	 * @param   bool  $distinct True if DISTINCT has to be aded to the query
	 *
	 * @return  mixed   number of rows OR false
	 */
	public function count($column = null, $distinct = true)
	{
		$select = $column ?: \Arr::get(call_user_func($this->model.'::primary_key'), 0);
		$select = (strpos($select, '.') === false ? $this->alias.'.'.$select : $select);

		// Get the columns
		$columns = \DB::expr('COUNT('.($distinct ? 'DISTINCT ' : '').
			\Database_Connection::instance($this->connection)->quote_identifier($select).
			') AS count_result');

		// Remove the current select and
		$query = \DB::select($columns);

		// Set from view or table
		$query->from(array($this->_table(), $this->alias));

		$tmp   = $this->build_query($query, $columns, 'count');
		$query = $tmp['query'];
		$count = $query->execute($this->connection)->get('count_result');

		// Database_Result::get('count_result') returns a string | null
		if ($count === null)
		{
			return false;
		}

		return (int) $count;
	}

	/**
	 * Get the maximum of a column for the current query
	 *
	 * @param   string      $column Column
	 * @return  bool|int    maximum value OR false
	 */
	public function max($column)
	{
		is_array($column) and $column = array_shift($column);

		// Get the columns
		$columns = \DB::expr('MAX('.
			\Database_Connection::instance($this->connection)->quote_identifier($this->alias.'.'.$column).
			') AS max_result');

		// Remove the current select and
		$query = \DB::select($columns);

		// Set from table
		$query->from(array($this->_table(), $this->alias));

		$tmp   = $this->build_query($query, $columns, 'max');
		$query = $tmp['query'];
		$max   = $query->execute($this->connection)->get('max_result');

		// Database_Result::get('max_result') returns a string | null
		if ($max === null)
		{
			return false;
		}

		return $max;
	}

	/**
	 * Get the minimum of a column for the current query
	 *
	 * @param   string      $column Column which min value you want to get
	 *
	 * @return  bool|int    minimum value OR false
	 */
	public function min($column)
	{
		is_array($column) and $column = array_shift($column);

		// Get the columns
		$columns = \DB::expr('MIN('.
			\Database_Connection::instance($this->connection)->quote_identifier($this->alias.'.'.$column).
			') AS min_result');

		// Remove the current select and
		$query = \DB::select($columns);

		// Set from table
		$query->from(array($this->_table(), $this->alias));

		$tmp   = $this->build_query($query, $columns, 'min');
		$query = $tmp['query'];
		$min   = $query->execute($this->connection)->get('min_result');

		// Database_Result::get('min_result') returns a string | null
		if ($min === null)
		{
			return false;
		}

		return $min;
	}

	/**
	 * Run INSERT with the current values
	 *
	 * @return  bool|int    Last inserted ID (if present) or false on failure
	 */
	public function insert()
	{
		$res = \DB::insert(call_user_func($this->model.'::table'), array_keys($this->values))
			->values(array_values($this->values))
			->execute($this->write_connection);

		// Failed to save the new record
		if ($res[1] === 0)
		{
			return false;
		}

		return $res[0];
	}

	/**
	 * Run UPDATE with the current values
	 *
	 * @return  bool  success of update operation
	 */
	public function update()
	{
		// temporary disable relations
		$tmp_relations   = $this->relations;
		$this->relations = array();

		// Build query and execute update
		$query = \DB::update(call_user_func($this->model.'::table'));
		$tmp   = $this->build_query($query, array(), 'update');
		$query = $tmp['query'];
		$res = $query->set($this->values)->execute($this->write_connection);

		// put back any relations settings
		$this->relations = $tmp_relations;

		// Update can affect 0 rows when input types are different but outcome stays the same
		return $res >= 0;
	}

	/**
	 * Run DELETE with the current values
	 *
	 * @return  bool  success of delete operation
	 */
	public function delete()
	{
		// temporary disable relations
		$tmp_relations   = $this->relations;
		$this->relations = array();

		// Build query and execute update
		$query = \DB::delete(call_user_func($this->model.'::table'));
		$tmp   = $this->build_query($query, array(), 'delete');
		$query = $tmp['query'];
		$res = $query->execute($this->write_connection);

		// put back any relations settings
		$this->relations = $tmp_relations;

		return $res > 0;
	}
}
