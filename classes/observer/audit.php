<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.9-dev
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010-2026 Fuel Development Team
 * @link       https://fuelphp.com
 */

namespace Orm;

/**
 * Audit observer. When added to a model, it records all changes made to
 * the table in the audit tables
 */
class Observer_Audit extends Observer
{
	/**
	 * @var  bool  whether or not the audit functionality is enabled
	 */
	protected $enabled = false;

	/**
	 * @var  string  base name for the audit table. "_diff" will be appended for the table storing the diffs
	 */
	protected $table = null;

	/**
	 * @var  string  value of the session variable which uniquely idenfifies this session. Defaults to the Session key
	 */
	protected $session_key = null;

	/**
	 * @var  array  storage for the diff taken before the database operation, so we can write it to the audit log after
	 */
	protected $diff = null;

	/**
	 * Set the properties for this observer instance, based on the parent model's
	 * configuration or the defined defaults.
	 */
	public function __construct()
	{
		// get the config for this observer
		$this->enabled = \Config::get('orm.audit.enabled', false);
		$this->table = \Config::get('orm.audit.table', false);
		$this->session_key = \Config::get('orm.audit.session_key', false);
	}

	/**
	 * @param  Model  Model object subject of this observer method
	 */
	public function before_insert(Model $obj)
	{
		$this->make_diff($obj);
	}

	/**
	 * @param  Model  Model object subject of this observer method
	 */
	public function before_update(Model $obj)
	{
		$this->make_diff($obj);
	}

	/**
	 * @param  Model  Model object subject of this observer method
	 */
	public function before_delete(Model $obj)
	{
		$this->make_diff($obj);
	}

	/**
	 * @param  Model  Model object subject of this observer method
	 */
	public function after_insert(Model $obj)
	{
		$this->write_audit_log('INSERT', get_class($obj));
	}

	/**
	 * @param  Model  Model object subject of this observer method
	 */
	public function after_update(Model $obj)
	{
		$this->write_audit_log('UPDATE', get_class($obj));
	}

	/**
	 * @param  Model  Model object subject of this observer method
	 */
	public function after_delete(Model $obj)
	{
		$this->write_audit_log('DELETE', get_class($obj));
	}

	// ----------------------[ internal methods ]----------------------

	/**
	 * Make a diff from the object
	 */
	protected function make_diff($obj)
	{
		// only if enabled
		if ($this->enabled)
		{
			// get a pre-delete diff of the changes
			$this->diff = $obj->get_diff();
		}
	}

	/**
	 * write the diff to the audit log
	 */
	protected function write_audit_log($type, $model)
	{
		// only if enabled
		if ($this->enabled)
		{
			// get the unique identifier for this session
			$key = empty($this->session_key) ? \Session::key() : \Session::get($this->session_key,\Session::key());

			// get the current users id
			$user_id = \Auth::get('id', -1);

			// start a database transaction to make this change atomic
			\DB::start_transaction();

			// see if we already have an audit record
			$result = \DB::select('id')->from($this->table)->as_object()->execute();

			// found it
			if (count($result))
			{
				// get the primary key value
				$id = $result->current()->id;

				// update the 'last timestamp'
				\DB::update($this->table)->value('last', time())->where('id', '=', $id)->execute();
			}
			else
			{
				// insert a new audit record
				list($id, $rows) = \DB::insert($this->table)->set(array('key' => $key, 'user' => $user_id, 'first' => time(), 'last' => time()))->execute();
			}

			// add the audit diff record
			\DB::insert($this->table.'_diff')->set(array('audit_id' => $id, 'logged' => time(), 'type' => $type, 'model' => $model, 'diff' => json_encode($this->diff)))->execute();

			// end a database transaction and connit the changes
			\DB::commit_transaction();
		}
	}
}
