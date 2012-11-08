<?php

namespace Orm;

/**
 * Allows revisions of database entries to be kept when updates are made.
 * 
 * @author Steve "Uru" West <uruwolf@gmail.com>
 */
class Model_Temporal extends Model
{

	protected static $_temporal_cached = array();
	private static $_default_mysql_timestamp = true;
	private static $_default_timestamp_field = 'temporal';
	private static $_pk_check_disabled = array();
	protected static $_timestamp_zero = null;

	public static function _init()
	{
		static::$_timestamp_zero = static::temporal_property('mysql_timestamp', self::$_default_mysql_timestamp) ? '0000-00-00 00:00:00' : 0;
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
			$properties = static::$_temporal;
		}

		// cache the properties for next usage
		static::$_temporal_cached[$class] = $properties;

		return static::$_temporal_cached[$class];
	}

	/**
	 * Fetches a soft delete property description array, or specific data from it.
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
			return parent::find(array($id, 0));
		}

		$timestamp_field = static::temporal_property('timestamp_name', self::$_default_timestamp_field);

		//Select the next latest revision after the requested one then use that
		//to get the revision before.
		self::disable_primary_key_check();
		$subQuery = static::query()
			->select($timestamp_field)
			->where('id', $id)
			->where($timestamp_field, '>=', $timestamp)
			->or_where($timestamp_field, static::$_timestamp_zero)
			->order_by($timestamp_field, '= \'' . static::$_timestamp_zero . '\' ASC')
			->order_by($timestamp_field, 'ASC')
			->limit(1);

		$query = static::query()
			->where('id', $id)
			->where($timestamp_field, '<', $subQuery->get_query())
			->or_where($timestamp_field, static::$_timestamp_zero)
			->order_by($timestamp_field, 'DESC')
			->limit(1);
		self::enable_primary_key_check();
		
		//Ensure the relations are added
		foreach($relations as $relation)
		{
			$query->related($relation);
		}
		
		$queryResult = $query->get();

		$singleItem = array_pop($queryResult);

		if ($singleItem->id != $id)
		{
			return null;
		}

		return $singleItem;
	}

	/**
	 * Returns a list of revisions between the given times with the most recent
	 * first.
	 * 
	 * @param type $id
	 * @param type $earliestTime
	 * @param type $latestTime
	 */
	public static function find_revisions_between($id, $earliestTime = null, $latestTime = null)
	{
		//Must also slect +1 earliest times to make sure the entity state
		//at the earliestTime is included.

		$timestamp_field = static::temporal_property('timestamp_name', self::$_default_timestamp_field);

		if ($earliestTime == null)
		{
			$earliestTime = 1;
		}

		$maxTimestamp = static::query()
			->where('id', $id)
			->max($timestamp_field);

		static::disable_primary_key_check();
		//Sub query to select a single revision that is the current state at the given start time
		$startState = static::query()
			->select($timestamp_field)
			->where('id', $id)
			->where($timestamp_field, '<=', $earliestTime)
			->order_by($timestamp_field, 'DESC')
			->limit(1);

		//Select all revisions within the given range.
		$query = static::query()
			->where('id', $id)
			->where($timestamp_field, '>=', $earliestTime)
			->or_where($timestamp_field, $startState->get_query())
			//This makes sure that the latest (0 timestamped) revision is at the top of the list
			->order_by($timestamp_field, '= \'' . static::$_timestamp_zero . '\' DESC')
			->order_by($timestamp_field, 'DESC');
		static::enable_primary_key_check();

		if ($latestTime != null)
		{
			$query->where($timestamp_field, '<=', $latestTime);
		}

		//This must be the last or/where added to ensure the correct ordering
		$query->or_where($timestamp_field, static::$_timestamp_zero);

		$revisions = $query->get();

		//If there is not more than one we don't need to worry about popping 0 off the list
		if (count($revisions) > 1)
		{
			$selectMax = static::query()
				->where('id', $id)
				->where($timestamp_field, $maxTimestamp)
				->order_by($timestamp_field, '= \'' . $maxTimestamp . '\' DESC')
				->get_one();

			$latest = array_slice($revisions, 1, 1);
			$latest = array_pop($latest);

			if ($latest->{$timestamp_field} != $selectMax->{$timestamp_field})
			{
				array_shift($revisions);
			}
		}

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
		$timestamp_field = static::temporal_property('timestamp_name', self::$_default_timestamp_field);

		switch ($id)
		{
			case NULL:
			case 'all':
			case 'first':
			case 'last':
				$options['where'][] = array($timestamp_field, 0);
				break;
			default:
				$id = (array) $id;
				$id[] = static::$_timestamp_zero;
				break;
		}

		return parent::find($id, $options);
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
		$timestamp_field = static::temporal_property('timestamp_name', self::$_default_timestamp_field);
		$mysql_timestamp = static::temporal_property('mysql_timestamp', self::$_default_mysql_timestamp);

		//If this is new then just call the parent and let everything happen as normal
		if ($this->is_new())
		{
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
				$this->{$timestamp_field} = $mysql_timestamp ? \Date::forge()->format('mysql') : \Date::forge()->get_timestamp();
				self::enable_primary_key_check();

				parent::save();

				//Construct a copy of this model and save that with a 0 timestamp
				foreach ($this->primary_key() as $pk)
				{
					if ($pk != $timestamp_field)
					{
						$newModel->{$pk} = $this->{$pk};
					}
				}
				$newModel->{$timestamp_field} = static::$_timestamp_zero;

				return $newModel->save();
			}
		}

		return $this;
	}

	/**
	 * Overrides the parent primary_key method to allow primaray key enforcement
	 * to be turned off when updating a temporal model.
	 */
	public static function primary_key()
	{
		$class = get_called_class();

		if (static::get_primary_key_status())
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

}