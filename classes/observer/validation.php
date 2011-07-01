<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Fuel
 * @version		1.0
 * @author		Fuel Development Team
 * @license		MIT License
 * @copyright	2010 - 2011 Fuel Development Team
 * @link		http://fuelphp.com
 */

namespace Orm;

// Exception to throw when validation failed
class ValidationFailed extends \Fuel_Exception {}

class Observer_Validation extends Observer {

	/**
	 * Set a Model's properties as fields on a Fieldset, which will be created with the Model's
	 * classname if none is provided.
	 *
	 * @param   string
	 * @param   Fieldset|null
	 * @return  Fieldset
	 */
	public static function set_fields($class, $fieldset = null)
	{
		$properties = $class::properties();

		if (is_null($fieldset))
		{
			$fieldset = \Fieldset::instance($class);
			if ( ! $fieldset)
			{
				$fieldset = \Fieldset::factory($class);
			}
		}

		foreach ($properties as $p => $settings)
		{
			$field = $fieldset->add($p, ! empty($settings['label']) ? $settings['label'] : $p);
			if (empty($settings['validation']))
			{
				continue;
			}
			else
			{
				foreach ($settings['validation'] as $rule => $args)
				{
					if (is_int($rule) and is_string($args))
					{
						$args = array($args);
					}
					else
					{
						array_unshift($args, $rule);
					}

					call_user_func_array(array($field, 'add_rule'), $args);
				}
			}
		}

		// Add related fields to the validation to prevent them being stripped
		$val = $fieldset->validation();
		foreach ($class::relations() as $name=>$relation) {
			$val->add($name);
		}

		return $fieldset;
	}

	/**
	 * Execute before saving the Model
	 *
	 * @param   Model
	 * @throws  ValidationFailed
	 */
	public function before_save(Model $obj)
	{
		$val = static::set_fields(get_class($obj))->validation();

		$input = array();
		foreach ($obj as $k => $v)
		{
			! in_array($k, $obj->primary_key()) and $input[$k] = $v;
		}

		if ($val->run($input) === false)
		{
			throw new ValidationFailed();
		}
		else
		{
			foreach ($input as $k => $v)
			{
				$obj->{$k} = $val->validated($k);
			}
		}
	}
}

// End of file validation.php