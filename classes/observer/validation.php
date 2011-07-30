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
	public static function set_fields($obj, $fieldset = null)
	{
		static $_generated = array();

		$class = is_object($obj) ? get_class($obj) : $obj;
		if (is_null($fieldset))
		{
			$fieldset = \Fieldset::instance($class);
			if ( ! $fieldset)
			{
				$fieldset = \Fieldset::factory($class);
			}
		}

		! array_key_exists($class, $_generated) and $_generated[$class] = array();
		if (in_array($fieldset, $_generated[$class], true))
		{
			return $fieldset;
		}
		$_generated[$class][] = $fieldset;

		$fieldset->validation()->add_callable($obj);

		$properties = is_object($obj) ? $obj->properties() : $class::properties();
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
		return $this->validate($obj);
	}

	/**
	 * Validate the model
	 *
	 * @param   Model
	 * @throws  ValidationFailed
	 */
	public function validate(Model $obj)
	{
		$val = static::set_fields($obj)->validation();

		$input = array();
		foreach (array_keys($obj->properties()) as $p)
		{
			! in_array($p, $obj->primary_key()) and $input[$p] = $obj->{$p};
		}

		if ($val->run($input) === false)
		{
			throw new ValidationFailed($val->show_errors());
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