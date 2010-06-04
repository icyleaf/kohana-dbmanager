<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Database Manager library.
 * password hashing.
 *
 * @author icyleaf (http://icyleaf.com)
 * @license The MIT License
 * @version 0.1
 *
 * ----------------------------------------------------------
 * This Modules Inspired by WP-DBManager of Wordpress plugins 
 * ----------------------------------------------------------
 *
 * The MIT License
 *
 * Copyright (c) 2009 icyleaf
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 *
 * TODO List
 * 1. auto backup [F]
 * 2. auto optimize [F]
 * 3. limit backup files numbers
 * 4. nofity by email 
 * 5. support for other available database driver of application
 *
 */
abstract class DBManager {
	
	// DBManager configuration
	protected $config;

	public static $instances = array();
	
	/**
	 * Return a static instance of DBManager.
	 *
	 * @return  object
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
				throw new Kohana_Exception('Database type not defined in :name configuration',
					array(':name' => $name));
			}
			
			// Set the driver class name
			$driver = 'DBManager_'.ucfirst($config['type']);

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
	 * @return  void
	 */
	final protected function __construct($name, array $config)
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
	 * @return (string) mysql version
	 */
	abstract function get_version();
	
	/**
	 * List tables information in database of application
	 *
	 * @return (object) tables information
	 */ 
	abstract function list_tables();
	
	/**
	 * Total tables number in database of application
	 *
	 * @return (int) tables count
	 */
	abstract function total_tables();
	
	/**
	 * Optimize tables
	 *
	 * @param (array) $tables - need to optimize tables
	 * @return void
	 */
	abstract function optimize_tables($tables = array());
	
	/**
	 * Repair tables
	 *
	 * @param (array) $tables - need to repair tables
	 * @return void if successs, else return error messages.
	 */
	abstract function repair_tables($tables = array());
	
	/**
	 * Backup dababase of application
	 *
	 * @param (boolean) $gzip - set TRUE, compress sql file with Gzip. by default, set it FALSE.
	 * @return void if success, else return error messages.
	 */
	abstract function backup_db($gzip=FALSE);
	
	/**
	 * Download backup file
	 *
	 * @param (string) $filename - only fule filename without path. the path will include from config file.
	 * @return void
	 */
	abstract function download_backup($filename);
	
	/**
	 * Delete backup file
	 *
	 * @param (string) $filename - only fule filename without path. the path will include from config file.
	 * @return delete status. if file not exist, return error message.
	 */
	abstract function delete_backup($filename);
	
	/**
	 * List backup files
	 *
	 * @return (array) backup files
	 */
	abstract function list_backfiles();
	
	/**
	 * Automatic backup dababase of application
	 *
	 * @param (boolean) $gzip - set TRUE, compress sql file with Gzip. by default, set it FALSE.
	 * @return void.
	 */
	public function auto_backup()
	{
		$cycle = Kohana::config('dbmanager.auto_backup');
		if ( $cycle==0 )
			return FALSE;
		
		$time = $this->read_time();
		
		if ( !is_numeric($time['backup']) )
		{
			$time['backup'] = time() + $this->format_time($cycle);
			$this->write_time($time['backup'], 'backup');
		}
		
		$gzip = Kohana::config('dbmanager.auto_backup_gzip');
		
		if ( $gzip )
			$sqlfile = MODPATH.'backup-db/'.$time['backup'].'_-_'.$this->config['database'].'.sql.gz';
		else
			$sqlfile = MODPATH.'backup-db/'.$time['backup'].'_-_'.$this->config['database'].'.sql';
			
		if ( $time['backup']<=time() && !file_exists($sqlfile) )
		{
			$next_time = time() + $this->format_time($cycle);
			$this->write_time($next_time, 'backup');
			Kohana::log('debug', 'Auto backup is starting at '.date('Y-m-d H:i', time()).'.');
			
			return $this->driver->backup_db($gzip);
		} else return 'FALSE';
	}
	
	/**
	 * Automatic optimize dababase of application
	 *
	 * @param (boolean) $gzip - set TRUE, compress sql file with Gzip. by default, set it FALSE.
	 * @return void.
	 */	
	public function auto_optimize()
	{
		$cycle = Kohana::config('dbmanager.auto_optimize');
		if ( $cycle==0 )
			return FALSE;
		
		$time = $this->read_time();
		
		if ( !is_numeric($time['optimize']) )
		{
			$time['optimize'] = time() + $this->format_time($cycle);
			$this->write_time($time['optimize'], 'optimize');
		}

		if ( $time['optimize']<=time() && !file_exists($sqlfile) )
		{
			$next_time = time() + $this->format_time($cycle);
			$this->write_time($next_time, 'backup');
			Kohana::log('debug', 'Auto Optimize is starting at '.date('Y-m-d H:i', time()).'.');
			
			return $this->dirver->optimize_tables('all');
		} else return 'FALSE';
	}
	
	/**
	 * Show Next time of Backup database
	 *
	 * @return string YYYY-MM-DD hh:mm.
	 */	
	public function next_backup_time()
	{
		$time = $this->read_time();
		if ( $time['backup']=='N/A' )
		{
			return $time['backup'];
		}
		return date('Y-m-d H:i', $time['backup']);
	}
	
	/**
	 * Show Next time of Backup database
	 *
	 * @return string.
	 */	
	public function next_optimize_time()
	{
		$time = $this->read_time();
		
		if ( $time['optimize']=='N/A' )
		{
			return $time['optimize'];
		}
		return date('Y-m-d H:i', $time['optimize']);
	}
	
	/**
	 * Formate string
	 *
	 * @param (string) $string - need to format string
	 * @param (stting) $type - format type
	 * @return (string) formated string
	 */
	protected function format_srting($string, $type='ucfirst')
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
	 * @param (string) $rawSize - row of table length in database
	 * @return (string) row size.
	 */
	protected function format_size($rawSize) {
		if($rawSize / 1073741824 > 1) 
			return round($rawSize/1048576, 1) . ' '.__('GiB');
		else if ($rawSize / 1048576 > 1)
			return round($rawSize/1048576, 1) . ' '.__('MiB');
		else if ($rawSize / 1024 > 1)
			return round($rawSize/1024, 1) . ' '.__('KiB');
		else
			return round($rawSize, 1) . ' '.__('bytes');
	}
	
	/**
	 * Formate time
	 *
	 * @param (int) $days - days
	 * @return (int) days in seconds unit.
	 */
	protected function format_time($minites=null)
	{
		$minite = 60; // 1 minite
		
		if ( !empty($minites) )
			return $minite * $minites;
	}
	
	/**
	 * Read 'automatic.txt' in backup-db
	 *
	 * @return  string content of file.
	 */
	protected function read_time()
	{
		$file = MODPATH.'/dbmanager/backup-db/automatic.txt';
		
		$fp = fopen($file, "r");
		$line = '';
		$content = array();
		while (!feof($fp))
		{
			$line = fgets($fp, 4096);
			if ( preg_match('/NEXT_BACKUP_TIME=(.*)/i', $line, $match1) )
				$content['backup'] = trim($match1[1]);
				
			if ( preg_match('/NEXT_BACKUP_TIME=(.*)/i', $line, $match2) )
				$content['optimize'] = trim($match2[1]);
		}
		fclose($fp);

		return $content;
	}

	/**
	 * Write 'automatic.txt' in backup-db
	 *
	 * @return  string content of file.
	 */
	protected function write_time($time, $type)
	{
		if ( empty($time) || !is_numeric($time) || empty($type))
			return FALSE;
			
		$file = MODPATH.'/dbmanager/backup-db/automatic.txt';
		
		$fp = fopen($file, "r");
		$line = '';
		$content = '';
		while (!feof($fp))
		{
			$line = fgets($fp, 4096);
			$content .= $line;
		}
		fclose($fp);
		
		switch($type)
		{
			case 'backup':
				$new_content = preg_replace('/NEXT_BACKUP_TIME=(.*)/','NEXT_BACKUP_TIME='.$time, $content);
				break;
			case 'optimize':
				$new_content = preg_replace('/NEXT_BACKUP_TIME=(.*)/','NEXT_BACKUP_TIME='.$time, $content);
				break;
		}
		
		$fp = fopen($file, "w");
		fwrite($fp, $new_content);
		fclose($fp);
		
/*
!! DON'T DELETE OR REMOVE THIS FILE !!
		
NEXT_BACKUP_TIME=N/A
		
NEXT_OPTIMIZE_TIME=N/A
*/
		return TRUE;
	}

} // End DBManager