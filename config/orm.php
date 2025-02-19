<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.9-dev
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010-2025 Fuel Development Team
 * @link       https://fuelphp.com
 */

return array(
    // global query settings
    'caching' => true,

	// lazy load related objects
	'relation_lazy_load' => false,

	// temporal model settings
    'sql_max_timestamp_mysql' => '2038-01-18 22:14:08',
    'sql_max_timestamp_unix'  => 2147483647,
);
