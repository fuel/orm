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
 * @copyright  2010 - 2013 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Orm;

class Collection extends \ArrayIterator
{
	private $_model;

	/**
	 * forge function.
	 * 
	 * @access public
	 * @static
	 * @param mixed $model
	 * @param array $data (default: array())
	 * @return void
	 */
	public static function forge($model,array $data = array()){
		return new static($model, $data);
	}
	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @param array $array (default: array())
	 * @return void
	 */
	public function __construct($model, array $array=array()) {
		$this->_model = $model;
		
		return parent::__construct($array);
	}
	
	
	/**
	 * __get function.
	 * 
	 * @access public
	 * @param mixed $name
	 * @return void
	 */
	public function __get($name){
		
		$class = get_called_class();
		$results = new $class($this->get_model());
		
		$is_nested = true;
		
		foreach ($this as $n=>$v){
			$results[$n] = $v->{$name};
			
			if (!is_subclass_of($v,$class) && get_class($v) != $class) $is_nested = false;
			
		}
		
		if (count($this) && $is_nested){
			$_results = array();
			
			foreach ($results as $result) $_results = $_results + $result->getArrayCopy();
			
			$results = new $class($this->get_model(),$_results);
		}
		
		return $results;
		
	}
	
	/**
	 * getMode function.
	 * 
	 * @access public
	 * @return void
	 */
	public function get_model(){
		return $this->_model;
	}
	
	/**
	 * implode_pk function.
	 * 
	 * @access public
	 * @param mixed $data
	 * @return void
	 */
	public function implode_pk($data){
		$data = array();
		
		foreach($data as $n=>$v)
		{
			$data[] = $v->implode_pk($v);
		}
		
		return $data;
	}
}