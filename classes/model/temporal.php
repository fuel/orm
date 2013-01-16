<?php

namespace Orm;

/**
 * Allows revisions of database entries to be kept when updates are made.
 * 
 * @author Steve "Uru" West <uruwolf@gmail.com>
 */
class Model_Temporal extends Model
{

	/**
	 * Contains cached temporal properties.
	 */
	protected static $_temporal_cached = array();

	/**
	 * Contains the status of the primary key disable flag for temporal models
	 */
	protected static $_pk_check_disabled = array();
	
	/**
	 * Contains the status for classes that defines if primaryKey() should return
	 * just the ID.
	 */
	protected static $_pk_id_only = array();
	
	/**
	 * Contains the status of filters for temporal model's query method
	 */
	protected static $_filter_query = array();

	public static function _init()
	{
		\Config::load('orm', true);
	}

	/**
	 * Gets the temporal properties.
	 * Mostly stolen from the parent class properties() function
	 * 
	 * @return array
	 */
	public static function temporal_properties()
	{
		$class = get_called_class();

		// If already determined
		if (array_key_exists($class, static::$_temporal_cached))
		{
			return static::$_temporal_cached[$class];
		}

		$properties = array();

		// Try to grab the properties from the class...
		if (property_exists($class, '_temporal'))
		{
			//Load up the info
			$properties['start_column'] =
				\Arr::get(static::$_temporal, 'start_column', 'temporal_start');
			$properties['end_column'] =
				\Arr::get(static::$_temporal, 'end_column', 'temporal_end');
			$properties['mysql_timestamp'] =
				\Arr::get(static::$_temporal, 'mysql_timestamp', true);

			$properties['max_timestamp'] = ($properties['mysql_timestamp']) ?
				\Config::get('orm.sql_max_timestamp_mysql') :
				\Config::get('orm.sql_max_timestamp_unix');
		}

		// cache the properties for next usage
		static::$_temporal_cached[$class] = $properties;

		return static::$_temporal_cached[$class];
	}

	/**
	 * Fetches temporal property description array, or specific data from
	 * it.
	 * Stolen from parent class.
	 *
	 * @param   string  property or property.key
	 * @param   mixed   return value when key not present
	 * @return  mixed
	 */
	public static function temporal_property($key, $default = null)
	{
		$class = get_called_class();

		// If already determined
		if (!array_key_exists($class, static::$_temporal_cached))
		{
			static::temporal_properties();
		}

		return \Arr::get(static::$_temporal_cached[$class], $key, $default);
	}

	/**
	 * Finds a specific revision for the given ID. If a timestamp is specified 
	 * the revision returned will reflect the entity's state at that given time.
	 * This will also load relations when requested.
	 * 
	 * @param type $id
	 * @param int $timestamp Null to get the latest revision (Same as find($id))
	 * @param array $relations Names of the relations to load.
	 * @return Subclass of Orm\Model_Temporal
	 */
	public static function find_revision($id, $timestamp = null, $relations = array())
	{
		if ($timestamp == null)
		{
			return parent::find($id);
		}

		$timestamp_start_name = static::temporal_property('start_column');
		$timestamp_end_name = static::temporal_property('end_column');
		
		//Select the next latest revision after the requested one then use that
		//to get the revision before.
		self::disable_primary_key_check();

		$query = static::query()
			->where('id', $id)
			->where($timestamp_start_name, '<=', $timestamp)
			->where($timestamp_end_name, '>', $timestamp);
		self::enable_primary_key_check();

		//Make sure the temporal stuff is activated
		$query->set_temporal_properties($timestamp, $timestamp_end_name, $timestamp_start_name);
		
		foreach ($relations as $relation)
		{
			$query->related($relation);
		}

		$query_result = $query->get_one();
		return $query_result;
	}
	
	public static function query($options = array())
	{
		$class = get_called_class();
		
		if(\Arr::get(static::$_filter_query, $class, false))
		{
			$timestamp_start_name = static::temporal_property('start_column');
			$timestamp_end_name = static::temporal_property('end_column');
			$timestamp = 1;

			$options['where'] = array(
				array($timestamp_start_name, '<=', $timestamp),
				array($timestamp_end_name, '>', $timestamp),
			);
		}
		
		return Temporal_Query::forge(get_called_class(), static::connection(), $options);
	}
	
	/**
	 * Returns a list of revisions between the given times with the most recent
	 * first. This does not load relations.
	 * 
	 * @param int|string $id
	 * @param timestamp $earliestTime
	 * @param timestamp $latestTime
	 */
	public static function find_revisions_between($id, $earliestTime = null, $latestTime = null)
	{
		$timestamp_start_name = static::temporal_property('start_column');
		$max_timestamp = static::temporal_property('max_timestamp');
		
		if ($earliestTime == null)
		{
			$earliestTime = 0;
		}
		
		if($latestTime == null)
		{
			$latestTime = $max_timestamp;
		}

		static::disable_primary_key_check();
		//Select all revisions within the given range.
		$query = static::query()
			->where('id', $id)
			->where($timestamp_start_name, '>=', $earliestTime)
			->where($timestamp_start_name, '<=', $latestTime);
		static::enable_primary_key_check();

		$revisions = $query->get();
		return $revisions;
	}

	/**
	 * Overrides the default find method to allow the latest revision to be found
	 * by default.
	 * 
	 * If any new options to find are added the switch statement will have to be
	 * updated too.
	 * 
	 * @param type $id
	 * @param array $options
	 * @return type
	 */
	public static function find($id = null, array $options = array())
	{
		$timestamp_end_name = static::temporal_property('end_column');
		$max_timestamp = static::temporal_property('max_timestamp');
		
		switch ($id)
		{
			case NULL:
			case 'all':
			case 'first':
			case 'last':
				break;
			default:
				$id = (array) $id;
				$count = 0;
				foreach(static::getNonTimestampPks() as $key)
				{
					$options['where'][] = array($key, $id[$count]);
					
					$count++;
				}
				break;
		}
		
		$options['where'][] = array($timestamp_end_name, $max_timestamp);

		static::enable_id_only_primary_key();
		$result = parent::find($id, $options);
		static::disable_id_only_primary_key();
		
		return $result;
	}
	
	/**
	 * Returns an array of the primary keys that are not related to temporal
	 * timestamp information.
	 */
	private static function getNonTimestampPks()
	{
		$timestamp_start_name = static::temporal_property('start_column');
		$timestamp_end_name = static::temporal_property('end_column');
		
		$pks = array();
		foreach(parent::primary_key() as $key)
		{
			if ($key != $timestamp_start_name && $key != $timestamp_end_name)
			{
				$pks[] = $key;
			}
		}
		
		return $pks;
	}

	/**
	 * Overrides the save method to allow temporal models to be 
	 * @param type $cascade
	 * @param type $use_transaction
	 * @return type
	 */
	public function save($cascade = null, $use_transaction = false)
	{
		//Load temporal properties.
		$timestamp_start_name = static::temporal_property('start_column');
		$timestamp_end_name = static::temporal_property('end_column');
		$mysql_timestamp = static::temporal_property('mysql_timestamp');

		$max_timestamp = static::temporal_property('max_timestamp');
		$current_timestamp = $mysql_timestamp ?
			\Date::forge()->format('mysql') :
			\Date::forge()->get_timestamp();
		
		//If this is new then just call the parent and let everything happen as normal
		if ($this->is_new())
		{
			static::disable_primary_key_check();
			$this->{$timestamp_start_name} = $current_timestamp;
			$this->{$timestamp_end_name} = $max_timestamp;
			static::enable_primary_key_check();
			
			return parent::save($cascade, $use_transaction);
		}
		//If this is an update then set a new PK, save and then insert a new row
		else
		{
			$diff = $this->get_diff();

			if (count($diff[0]) > 0)
			{
				//Take a copy before resetting
				$newModel = clone $this;

				//Reset the current model and update the timestamp
				$this->reset();

				self::disable_primary_key_check();
				$this->{$timestamp_end_name} = $current_timestamp;
				self::enable_primary_key_check();

				parent::save();
				
				//Construct a copy of this model and save that with a 0 timestamp
				foreach ($this->primary_key() as $pk)
				{
					if ($pk != $timestamp_start_name && $pk != $timestamp_end_name)
					{
						$newModel->{$pk} = $this->{$pk};
					}
				}
				
				static::disable_primary_key_check();
				$newModel->{$timestamp_start_name} = $current_timestamp;
				$newModel->{$timestamp_end_name} = $max_timestamp;
				static::enable_primary_key_check();
				
				return $newModel->save();
			}
		}

		return $this;
	}
	
	/**
	 * Overrides update to remove PK checking when performing an update.
	 */
	public function update()
	{
		static::disable_primary_key_check();
		$result = parent::update();
		static::enable_primary_key_check();
		
		return $result;
	}
	
	/**
	 * Allows correct PKs to be added when performing updates
	 * 
	 * @param Query $query
	 */
	protected function add_primary_keys_to_where($query)
	{
		$timestamp_start_name = static::temporal_property('start_column');
		$timestamp_end_name = static::temporal_property('end_column');
		
		$primary_key = array(
			'id',
			$timestamp_start_name,
			$timestamp_end_name,
		);
		
		foreach ($primary_key as $pk)
		{
			$query->where($pk, '=', $this->_original[$pk]);
		}
	}

	/**
	 * Overrides the parent primary_key method to allow primaray key enforcement
	 * to be turned off when updating a temporal model.
	 */
	public static function primary_key()
	{
		$id_only = static::get_primary_key_id_only_status();
		$pk_status = static::get_primary_key_status();
		
		if ($id_only)
		{
			return static::getNonTimestampPks();
		}
		
		if ($pk_status && ! $id_only)
		{
			return static::$_primary_key;
		}
		
		return array();
	}

	/**
	 * Disables PK checking
	 */
	private static function disable_primary_key_check()
	{
		$class = get_called_class();
		self::$_pk_check_disabled[$class] = false;
	}

	/**
	 * Enables PK checking
	 */
	private static function enable_primary_key_check()
	{
		$class = get_called_class();
		self::$_pk_check_disabled[$class] = true;
	}

	/**
	 * Returns true if the PK checking should be performed. Defaults to true
	 */
	private static function get_primary_key_status()
	{
		$class = get_called_class();
		return \Arr::get(self::$_pk_check_disabled, $class, true);
	}
	
	/**
	 * Returns true if the PK shoudl only contain the ID. Defaults to false
	 */
	private static function get_primary_key_id_only_status()
	{
		$class = get_called_class();
		return \Arr::get(self::$_pk_id_only, $class, false);
	}
	
	/**
	 * Makes all PKs returned
	 */
	private static function disable_id_only_primary_key()
	{
		$class = get_called_class();
		self::$_pk_id_only[$class] = false;
	}

	/**
	 * Makes only id returned as PK
	 */
	private static function enable_id_only_primary_key()
	{
		$class = get_called_class();
		self::$_pk_id_only[$class] = true;
	}

}
