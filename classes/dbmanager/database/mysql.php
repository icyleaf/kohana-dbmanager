<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * DBManager Mysql driver.
 *
 * @package DBManager/Database/Mysql
 * @author icyleaf <icyleaf.cn@gmail.com>
 * @link http://icyleaf.com
 * @link http://kohana.cn
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class DBManager_Database_Mysql extends DBManager_Core {

	public function __construct($name, array $config)
	{
		// Check requires function
		$this->_check_functions();

		parent::__construct($name, $config);
	}

	/**
	 * Get Mysql version
	 *
	 * @return string		mysql version
	 */
	public function version()
	{
		$result = DB::query(Database::SELECT, 'SELECT VERSION() AS version')->execute();
		return $result->get('version');
	}
	
	/**
	 * List tables information in database of application
	 *
	 * @return object		tables information
	 */ 
	public function list_tables()
	{
		$result = DB::query(Database::SELECT, 'SHOW TABLE STATUS')->execute();
		
		$tables = array();
		$i = 0;
		foreach($result as $table)
		{
			$tables[$i]['name'] = $table['Name'];
			$tables[$i]['records'] = number_format($table['Rows']);
			$tables[$i]['data_length'] = $table['Data_length'];
			$tables[$i]['data_usage'] = $this->format_size($table['Data_length']);
			$tables[$i]['index_length'] = $table['Index_length'];
			$tables[$i]['index_usage'] = $this->format_size($table['Index_length']);
			$tables[$i]['data_free'] = $table['Data_free'];
			$tables[$i]['overhead'] = $this->format_size($table['Data_free']);

			$i++;
		}
		
		return $tables;
	}
	
	/**
	 * Total tables number in database of application
	 *
	 * @return int			tables count
	 */
	public function total_tables()
	{
		return DB::query(Database::SELECT, 'SHOW TABLE STATUS')->execute()->count();
	}
	
	/**
	 * Optimize tables
	 *
	 * @param mixed $tables	need to optimize tables, default it will repair all tables
	 * @return boolean		return TRUE if successs
	 */
	public function optimize_tables($tables = NULL)
	{
		if (empty($tables))
		{
			$tables = $this->list_tables();
		}

		// built table query
		$query = $this->_table_query_string($tables);

		// execute optimize sql
		DB::query(Database::SELECT, 'OPTIMIZE TABLE '.$query)->execute();

		return TRUE;
	}
	
	/**
	 * Repair tables
	 *
	 * @param array $tables	need to repair tables, set 'all' will repair all tables
	 * @return boolean		return TRUE if successs
	 */
	public function repair_tables($tables = NULL)
	{
		if (empty($tables))
		{
			$tables = $this->list_tables();
		}

		// built table query
		$query = $this->_table_query_string($tables);

		// execute repair sql
		DB::query(Database::SELECT, 'REPAIR TABLE '.$query)->execute();

		return TRUE;
	}
	
	/**
	 * Backup dababase of application
	 *
	 * @param boolean $gzip	set TRUE, compress sql file with Gzip. by default, set it FALSE.
	 * @return mixed		if success, else return error messages.
	 */
	public function backup_db($gzip = FALSE)
	{
		$config = $this->_config['connection'];
		
		$backup['filepath'] = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath'));
		$backup['filename'] = $backup['filepath'].'/'.date('YmdHis', time()).
			'_-_'.$config['database'].'.sql';
		$backup = array_merge($backup, $this->_detect_mysql());

		$gzip_param = $gzip ? ' | gzip ' : '';
		$file_ext = $gzip ? '.gz' : '';

		$query_string = $backup['mysqldump'].' --host="'.$config['hostname'].
			'" --user="'.$config['username'].'" --password="'.$config['password'].
			'" --add-drop-table --skip-lock-tables '.$config['database'].
			$gzip_param.' > '.$backup['filename'].$file_ext;

		$result = $this->_execute_backup($backup, $query_string);
		
		return $result;
	}
	
	/**
	 * Download backup file
	 *
	 * @param string $filename	only fule filename without path. the path will include from config file.
	 * @return void
	 */
	public function download_backup($filename)
	{
		$filename = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath')).
			'/'.$filename;

		if ( ! file_exists($filename))
		{
			exit('file not exist');
		}
		
		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Content-Type: application/force-download');
		header('Content-Type: application/octet-stream');
		header('Content-Type: application/download');
		header('Content-Disposition: attachment; filename='.basename($filename).';');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.filesize($filename));
		@readfile($filename);
		exit();
	}
	
	/**
	 * Delete backup file
	 *
	 * @param string $filename	only fule filename without path. the path will include from config file.
	 * @return boolean		return TRUE if successs, else FALSE.
	 */
	public function delete_backup($filename)
	{
		$filename = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath')).
			'/'.$filename;

		if ( ! file_exists($filename))
		{
			return FALSE;
		}
		else
		{
			return (boolean) unlink($filename);
		}
	}
	
	/**
	 * List backup files
	 *
	 * @return array			backup files
	 */
	public function backup_files()
	{
		$backup['filepath'] = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath'));
		$dir = dir($backup['filepath']);

		$list = array();
		while($file = $dir->read())
		{
			if((is_dir($backup['filepath'].'/'.$file)) AND ($file='.') AND ($file='..'))
			{
				continue;
			}
			
			array_push($list, $file);
		}
		$dir->close();

		return $list;
	}
	
	/**
	 * Build query command for backup
	 *
	 * @param array $tables		need to query tables
	 * @return string			query command
	 * @throws DBManager_Exception
	 */
	private function _table_query_string($tables = array())
	{
		$_table_prefix = $this->_config['table_prefix'];
		
		$tables_query = NULL;
		if (is_array($tables))
		{
			if (count($tables) <= 0)
			{
				return 'Empty table in database';
			}

			foreach ($tables as $table)
			{
				$table_name = is_array($table) ? $table['name'] : $table;
				$table_name = $this->_get_table_name($table_name);
				if ( ! $this->_table_exists($table_name))
				{
					throw new DBManager_Exception('Table is not exists in database: '.$table_name);
				}

				$tables_query .=  '`, `'.$table_name;
			}

			$tables_query = substr($tables_query, 3).'`';
		}
		else
		{	
			$table_prefix = substr($tables, 0, strlen($_table_prefix));
			if ($table_prefix == $this->_config['table_prefix'])
			{
				$tables = substr($tables, strlen($_table_prefix));
			}

			if ( ! $this->_table_exists($tables))
			{
				throw new DBManager_Exception('Table is not exists in database: '.$table_name);
			}
				
			$tables_query = '`'.$_table_prefix.$tables.'`';
		}

		return $tables_query;
	}
	
	/**
	 * Detect Mysql
	 *
	 * @return array			include mysql and mysqldump application's path.
	 */
	private function _detect_mysql()
	{
		$paths = array(
			'mysql'		=> '',
			'mysqldump' => '',
		);
		
		if (substr(PHP_OS, 0, 3) == 'WIN')
		{
			$mysql_install = DB::query(Database::SELECT, 'SHOW VARIABLES LIKE \'basedir\'')->execute();
 
			if($mysql_install->count() > 0)
			{
				$install_path = str_replace('\\', '/', $mysql_install->get('Value'));
				$paths['mysql'] = $install_path.'bin/mysql.exe';
				$paths['mysqldump'] = $install_path.'bin/mysqldump.exe';
			} 
			else
			{
				$paths['mysql'] = 'mysql.exe';
				$paths['mysqldump'] = 'mysqldump.exe';
			}
		} 
		else
		{
			if(function_exists('exec'))
			{
				$paths['mysql'] = @exec('which mysql');
				$paths['mysqldump'] = @exec('which mysqldump');
			}
			else
			{
				$paths['mysql'] = 'mysql';
				$paths['mysqldump'] = 'mysqldump';
			}
		}
		
		return $paths;
	}
	
	/**
	 * Execute backup
	 *
	 * @param array $backup		backup configuration.
	 * @param string $command	execute backup command
	 * @return mixed			if success, else reutrn error messages.
	 */
	private function _execute_backup($backup, $command)
	{
		$this->_check_backup_system($backup);
		
		if (substr(PHP_OS, 0, 3) == 'WIN')
		{
			$tmpnam = $backup['filepath'].DIRECTORY_SEPARATOR.'dbmanager_script.bat';
			$fp = fopen($tmpnam, 'w');
			fwrite($fp, $command);
			fclose($fp);
			system($tmpnam.' > NUL', $error_code);
			unlink($tmpnam);
		}
		else
		{
			passthru($command, $error_code);
		}

		switch ($error_code)
		{
			case 0:
				return TRUE;
			case 2:
				return 'Permission denied';
			default:
				return $error_code;
		}
	}
	
	/**
	 * Check backup file
	 *
	 * @param array $backup		backup configuration.
	 * @return void
	 * @throws DBManager_Exception
	 */
	private function _check_backup_system($backup)
	{
		if ( ! file_exists($backup['filepath']))
		{
			mkdir($backup['filepath'], 0777, TRUE);
			chmod($backup['filepath'], 0777);
		}

		if ( ! file_exists($backup['mysql']))
		{
			throw new DBManager_Exception('file of mysql(.exe) is not exist.');
		}

		if ( ! file_exists($backup['mysqldump']))
		{
			throw new DBManager_Exception('file of mysqldump(.exe) is not exist.');
		}
	}

	/**
	 * Check table exists
	 *
	 * @param string $table_name	table name	
	 * @return boolean				return TURE if it exits, else FALSE.
	 */
	private function _table_exists($table_name)
	{
		$sql = 'SELECT table_name FROM information_schema.tables
			WHERE table_schema = \''.$this->_config['connection']['database'].'\'
				AND table_name = \''.$table_name.'\'
			LIMIT 1';
		$row = DB::query(Database::SELECT, $sql)->execute();

		if ($row->count() == 1)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Format table name
	 * @param string $table_name	table name
	 * @return string				formated table name
	 */
	private function _get_table_name($table_name)
	{
		$_table_prefix = $this->_config['table_prefix'];

		$table_prefix = substr($table_name, 0, strlen($_table_prefix));
		if ( ! empty($_table_prefix))
		{
			if ($table_prefix == $_table_prefix)
			{
				$table_name = substr($table_name, strlen($_table_prefix));
			}
		}

		return $table_name;
	}
	
	/**
	 * Check function
	 *
	 * @throws DBManager_Exception
	 */
	private function _check_functions()
	{
		if ( ! function_exists('passthru'))
		{
			throw new DBManager_Exception('function passthru not exist');
		}

		if ( ! function_exists('system'))
		{
			throw new DBManager_Exception('function system not exist');
		}

		if ( ! function_exists('exec'))
		{
			throw new DBManager_Exception('function exec not exist');
		}
	}
	
} // End DBManager_Mysql_Driver