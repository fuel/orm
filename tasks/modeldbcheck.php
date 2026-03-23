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

namespace Fuel\Tasks;

/**
 * Get all models defined in the app, and check their
 * defined properties against the database schema
 */
class ModelDbCheck
{
	protected $models = array();

	/**
	 * task runner
	 */
    public function run()
    {
		\Cli::write('Enumerate application models', 'white');

		// get all possible model class paths
		$paths = array(APPPATH.'classes'.DS.'model');
		foreach (\Config::get('package_paths', array()) as $path)
		{
			foreach (glob($path.'*') as $path)
			{
				if (is_dir($path.DS.'classes'.DS.'model') and strpos($path, 'packages/oil') === false  and strpos($path, 'packages/orm') === false)
				{
					$paths[] = $path.DS.'classes'.DS.'model';
				}
			}
		}
		foreach (\Config::get('module_paths', array()) as $path)
		{
			foreach (glob($path.'*') as $path)
			{
				if (is_dir($path.DS.'classes'.DS.'model'))
				{
					$paths[] = $path.DS.'classes'.DS.'model';
				}
			}
		}

		// get a loaded class baseline
		$loaded = get_declared_classes();

		// scan the paths for ORM model classes
		foreach ($paths as $path)
		{
			$iterator = new \RecursiveDirectoryIterator($path);
			foreach (new \RecursiveIteratorIterator($iterator) as $file)
			{
				if ($file->getExtension() == 'php')
				{
					// try to load the file
					try
					{
						// make sure no output 'leaks' to the console
						ob_start();
						require_once $file->getPathname();
						ob_get_clean();
					}

					// and catch any parsing errors
					catch (\Exception $e)
					{
						continue;
					}

					// check what classes we have now
					$new = get_declared_classes();

					// and compare them to the baseline
					foreach (array_diff($new,$loaded) as $model)
					{
						switch ($model)
						{
							// classes to ignore
							case 'Orm\\Model':
							case 'Orm\\FrozenObject':
							case 'Orm\\RecordNotFound':
								continue 2;

							default:
								$model = '\\'.$model;
						}

						// access the model object
						$reflection = new \ReflectionClass($model);

						// skip abstract classes, traits and interfaces
						if ($reflection->isAbstract() or $reflection->isInterface() or $reflection->isTrait())
						{
							continue;
						}

						// skip models that don't extend anything
						if ( ! $reflection->getParentClass())
						{
							continue;
						}

						// skip models that extend the ORM model or a model class already loaded
						if ($reflection->getParentClass()->getName() == 'Orm\\Model' or in_array($reflection->getParentClass()->getName(), $loaded))
						{
							continue;
						}

						// call the static init, if defined, as it's not called outside autoload
						if (method_exists($model, '_init'))
						{
							$model::_init();
						}

						// we'll need to very this model
						$this->models[] = $model;
					}

					// store the new loaded state
					$loaded = $new;
				}
			}
		}

		\Cli::write(sprintf('%d ORM database models found to verify'.PHP_EOL, count($this->models)));

		\Cli::write('Database model verification', 'white');

		// process the defined models
		foreach ($this->models as $model)
		{
			\Cli::write('Verifying model '.$model, 'cyan');

			// access the model
			$reflection = new \ReflectionClass($model);

			// get table name and connection
			$table = $reflection->getProperty('_table_name')->getValue();
			$connection = $reflection->getProperty('_connection')->getValue();
			$columns = \DB::list_columns($table, null, $connection);

			// get the property map
			$propertymap = $reflection->getProperty('_property_map')->getValue();

			// check columns against properties
			foreach ($columns as $column => $def)
			{
				// if in the property map, it is defined
				if ( ! array_key_exists($column, $propertymap))
				{
					if ( ! array_key_exists($column, $model::properties()))
					{
						if (is_null($def['default']))
						{
							$default = 'null';
						}
						elseif ($def['type'] == 'int' or $def['type'] == 'float' )
						{
							$default = $def['default'];
						}
						else
						{
							$default = "'".$def['default']."'";
						}
						\Cli::write(sprintf("\t\t'%s' => array(\n\t\t\t'type' => '%s',\n\t\t\t'data_type' => '%s',\n\t\t\t'label' => '%s',\n\t\t\t'default' => %s,\n\t\t\t'null' => %s\n\t\t),", $column, $def['type'], $def['data_type'], $def['name'], $default, $def['null'] ? 'true' : 'false'));
					}
				}
			}

			// check properties against colums
			foreach ($model::properties() as $property => $def)
			{
				// if mapped, get the column name from the map
				if (in_array($property, $propertymap))
				{
					$property = array_search($property, $propertymap);
				}

				if ( ! array_key_exists($property, $columns))
				{
						\Cli::write('  '.$property.' not found');
				}
			}

			$obj = $model::forge();
		}

    }

	/************************[ internal methods ]************************/
}
