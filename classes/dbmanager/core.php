<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Database Manager library.
 * password hashing.
 *
 * @author icyleaf (http://icyleaf.com)
 * @link http://icyleaf.com
 * @link http://kohana.cn
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @version 0.2
 *
 * ----------------------------------------------------------
 * This Modules Inspired by WP-DBManager of Wordpress plugins 
 * ----------------------------------------------------------
 *
 * TODO List
 * 1. limit backup files numbers
 * 2. nofity by email
 * 3. support for other available database driver of application
 *
 */
abstract class DBManager_Core {
	// configuration
	protected $config;
	// instance
	public static $instances = array();

	/**
	 * Return a static instance of DBManager.
	 * @param string $name
	 * @param array $config
	 * @return object
	 */
	public static function instance($name = 'default', array $config = NULL)
	{
		if ( ! isset(DBManager::$instances[$name]))
		{
			if ($config === NULL)
			{
				// Load the configuration for this database
				$config = Kohana::config('database')->$name;
			}
			
			if ( ! isset($config['type']))
			{
				throw new Kohana_Exception('Database type not defined in '.$name.' configuration');
			}
			
			// Set the driver class name
			$driver = 'DBManager_Database_'.ucfirst($config['type']);

			// Create the database connection instance
			new $driver($name, $config);
		}

		return DBManager::$instances[$name];
	}
	
	// Instance name
	protected $_instance;

	// Configuration array
	protected $_config;

	/**
	 * Stores the database configuration locally and name the instance.
	 *
	 * @param string $name
	 * @param array $config
	 * @return  void
	 */
	protected function __construct($name, array $config = NULL)
	{
		// Set the instance name
		$this->_instance = $name;

		// Store the config locally
		$this->_config = $config;

		// Store the database instance
		DBManager::$instances[$name] = $this;
	}
	
	/**
	 * Get Mysql Version
	 *
	 * @return string mysql version
	 */
	abstract function version();
	
	/**
	 * List tables information in database of application
	 *
	 * @return (object) tables information
	 */ 
	abstract function list_tables();
	
	/**
	 * Total tables number in database of application
	 *
	 * @return int tables count
	 */
	abstract function total_tables();
	
	/**
	 * Optimize tables
	 *
	 * @param array $tables - need to optimize tables
	 * @return void
	 */
	abstract function optimize_tables($tables = array());
	
	/**
	 * Repair tables
	 *
	 * @param array $tables - need to repair tables
	 * @return void if successs, else return error messages.
	 */
	abstract function repair_tables($tables = array());
	
	/**
	 * Backup dababase of application
	 *
	 * @param boolean $gzip - set TRUE, compress sql file with Gzip. by default, set it FALSE.
	 * @return void if success, else return error messages.
	 */
	abstract function backup_db($gzip = FALSE);
	
	/**
	 * Download backup file
	 *
	 * @param string $filename - only fule filename without path. the path will include from config file.
	 * @return void
	 */
	abstract function download_backup($filename);
	
	/**
	 * Delete backup file
	 *
	 * @param string $filename - only fule filename without path. the path will include from config file.
	 * @return delete status. if file not exist, return error message.
	 */
	abstract function delete_backup($filename);
	
	/**
	 * List backup files
	 *
	 * @return array backup files
	 */
	abstract function backup_files();
	
	/**
	 * Formate string
	 *
	 * @param string $string - need to format string
	 * @param string $type - format type
	 * @return string formated string
	 */
	protected function format_srting($string, $type = 'ucfirst')
	{
		switch($type)
		{
			case 'lower':
				return strtolower($string);
			case 'upper':
				return strtoupper($string);
			default:
			case 'ucfirst':
				return ucfirst(strtolower($string));
		}
	}
	
	/**
	 * Formate size
	 *
	 * @param string $raw_size - row of table length in database
	 * @return string row size.
	 */
	protected function format_size($raw_size)
	{
		if(($raw_size / 1073741824) > 1)
		{
			return round($raw_size/1048576, 1) . ' '.'GiB';
		}
		elseif (($raw_size / 1048576) > 1)
		{
			return round($raw_size/1048576, 1) . ' '.'MiB';
		}
		elseif (($raw_size / 1024) > 1)
		{
			return round($raw_size/1024, 1) . ' '.'KiB';
		}
		else
		{
			return round($raw_size, 1) . ' '.'bytes';
		}
	}
	
	/**
	 * Formate time
	 *
	 * @param int $days - days
	 * @return int days in seconds unit.
	 */
	protected function format_time($minites = NULL)
	{
		$minite = 60; // 1 minite
		
		if ( ! empty($minites))
		{
			return $minite * $minites;
		}
	}

} // End DBManager