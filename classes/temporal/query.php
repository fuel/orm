<?php

namespace Orm;

/**
 * 
 *
 * @author Steve "uru" West <uruwolf@gmail.com>
 */
class Temporal_Query extends Query
{
	private $timestamp = null;
	private $timestamp_end_col = null;
	private $timestamp_start_col = null;
	
	/**
	 * Sets the timestamp to be used on joins. If set to null the latest revision
	 * is used.
	 * 
	 * @param null|string $stamp
	 */
	public function set_temporal_properties(
		$stamp,
		$timestamp_end_col,
		$timestamp_start_col)
	{
		$this->timestamp = $stamp;
		$this->timestamp_end_col = $timestamp_end_col;
		$this->timestamp_start_col = $timestamp_start_col;
	}
	
	protected function modify_join_result($join_result, $name)
	{	
		if(!is_null($this->timestamp))
		{
			//Add the needed conditions to allow for temporal-ness
			$join_result[$name]['where'][] = array($this->timestamp_start_col, '<=', $this->timestamp);
			$join_result[$name]['where'][] = array($this->timestamp_end_col, '>', $this->timestamp);
		}
		
		return $join_result;
	}
	
}
