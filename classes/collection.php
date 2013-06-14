<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.6
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
		return new self($model, $data);
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
	 * getMode function.
	 * 
	 * @access public
	 * @return void
	 */
	public function getMode(){
		return $this->_model;
	}
	
	public function implode_pk($data){
		$data = array();
		
		foreach($data as $n=>$v){
			$data[] = $v->implode_pk($v);
		}
		
		return $data;
	}
}