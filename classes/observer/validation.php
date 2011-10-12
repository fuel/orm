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
class ValidationFailed extends \FuelException {}

class Observer_Validation extends Observer
{

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
				$fieldset = \Fieldset::forge($class);
			}
		}

		! array_key_exists($class, $_generated) and $_generated[$class] = array();
		if (in_array($fieldset, $_generated[$class], true))
		{
			return $fieldset;
		}
		$_generated[$class][] = $fieldset;

		$properties = is_object($obj) ? $obj->properties() : $class::properties();
		foreach ($properties as $p => $settings)
		{
			if (isset($settings['form']['options']))
			{
				foreach ($settings['form']['options'] as $key => $value)
				{
					$settings['form']['options'][$key] = __($value) ?: $value;
				}
			}

			$label       = isset($settings['label']) ? $settings['label'] : $p;
			$attributes  = isset($settings['form']) ? $settings['form'] : array();
			$field       = $fieldset->add($p, $label, $attributes);
			if ( ! empty($settings['validation']))
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

		// only allow partial validation on updates, specify the fields for updates to allow null
		$allow_partial = $obj->is_new() ? false : array();

		$input = array();
		foreach (array_keys($obj->properties()) as $p)
		{
			if ( ! in_array($p, $obj->primary_key()) and $obj->is_changed($p))
			{
				$input[$p] = $obj->{$p};
				is_array($allow_partial) and $allow_partial[] = $p;
			}
		}

		if ( ! empty($input) and $val->run($input, $allow_partial, array($obj)) === false)
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