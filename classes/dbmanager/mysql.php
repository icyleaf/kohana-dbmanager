<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * DBManager Mysql driver.
 */
class DBManager_Mysql extends DBManager {

	/**
	 * Get Mysql Version
	 *
	 * @return (string) mysql version
	 */
	public function get_version()
	{
		$result = DB::query(Database::SELECT, 'SELECT VERSION() AS version')->execute();
		return $result->get('version');
	}
	
	/**
	 * List tables information in database of application
	 *
	 * @return (object) tables information
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
	 * @return (int) tables count
	 */
	public function total_tables()
	{
		return DB::query(Database::SELECT, 'SHOW TABLE STATUS')->execute()->count();
	}
	
	/**
	 * Optimize tables
	 *
	 * @param (array) $tables - need to optimize tables, set 'all' will repair all tables
	 * @return void
	 */
	public function optimize_tables($tables = NULL)
	{
		if ( $tables==NULL )
			$tables = $this->list_tables();
		
		$query = $this->table_query_string($tables);
		if ( $query['type']=='error' )
			return $query['content'];
		
		DB::query(Database::SELECT, 'OPTIMIZE TABLE '.$query['content'])->execute();

		return TRUE;
	}
	
	/**
	 * Repair tables
	 *
	 * @param (array) $tables - need to repair tables, set 'all' will repair all tables
	 * @return void if successs, else return error messages.
	 */
	public function repair_tables($tables = NULL)
	{
		if ( $tables==NULL )
			$tables = $this->list_tables();
			
		$query = $this->table_query_string($tables);
		if ( $query['type']=='error' )
			return $query['content'];
		
		DB::query(Database::SELECT, 'REPAIR TABLE '.$query['content'])->execute();

		return TRUE;
	}
	
	/**
	 * Backup dababase of application
	 *
	 * @param (boolean) $gzip - set TRUE, compress sql file with Gzip. by default, set it FALSE.
	 * @return void if success, else return error messages.
	 */
	public function backup_db($gzip = FALSE)
	{
		$config = $this->_config['connection'];
		
		$backup['filepath'] = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath'));
		$backup['filename'] = $backup['filepath'].'/'.time().'_-_'.$config['database'].'.sql';
		$backup = array_merge($backup, $this->detect_mysql());
		
		if ( $gzip ) {
			$backup['filename'] = $backup['filename'].'.gz';
			$query_string = $backup['mysqldump'].' --host="'.$config['hostname'].'" --user="'.$config['username'].'" --password="'.$config['password'].'" --add-drop-table --skip-lock-tables '.$config['database'].' | gzip > '.$backup['filename'];
		} 
		else
		{
			$backup['filename'] = $backup['filename'];
			$query_string = $backup['mysqldump'].' --host="'.$config['hostname'].'" --user="'.$config['username'].'" --password="'.$config['password'].'" --add-drop-table --skip-lock-tables '.$config['database'].' > '.$backup['filename'];
		}
		
		$result = $this->execute_backup($backup, $query_string);
		
		return $result;
	}
	
	/**
	 * Download backup file
	 *
	 * @param (string) $filename - only fule filename without path. the path will include from config file.
	 * @return void
	 */
	public function download_backup($filename)
	{
		$filename = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath')).'/'.$filename;
		if ( !file_exists($filename) )
			return 'file not exist';
			
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment; filename=".basename($filename).";");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($filename));
		@readfile($filename);
		exit();
	}
	
	/**
	 * Delete backup file
	 *
	 * @param (string) $filename - only fule filename without path. the path will include from config file.
	 * @return delete status. if file not exist, return error message.
	 */
	public function delete_backup($filename)
	{
		$filename = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath')).'/'.$filename;
		if ( !file_exists($filename) )
			return 'file not exist';
		else
			return unlink($filename);
	}
	
	/**
	 * List backup files
	 *
	 * @return (array) backup files
	 */
	public function list_backfiles()
	{
		$backup['filepath'] = preg_replace('/\//', '/', Kohana::config('dbmanager.backup_filepath'));
		$dir = dir($backup['filepath']);

		$list = array();
		while($file=$dir->read())
		{
			if((is_dir($backup['filepath'].'/'.$file)) && ($file=".") && ($file=".."))
				continue;

			array_push($list, $file);
		}
		$dir->close();

		return $list;
	}
	
	/**
	 * Build query command for backup
	 *
	 * @param (array) $tables - need to query tables
	 * @return (string) $return_string - query command
	 */
	private function table_query_string($tables = array())
	{
		$return_string = array
		(
			'type' => null,
			'content' => null
		);
		
		$tables_error = null;
		$tables_string = null;
		
		if ( count($tables)<=0 )
		{
			return __('empty_table', $this->_config['connection.database']);
		}
		
		if ( is_array($tables) )
		{
			foreach ( $tables as $table_name )
			{
				$table_name = $table_name['name'];
				
				$table_prefix = substr($table_name, 0, strlen($this->_config['table_prefix']));
				if ( !empty($this->_config['table_prefix']) )
				{
					if ( $table_prefix==$this->_config['table_prefix'] )
						$table_name = substr($table_name, strlen($this->_config['table_prefix']));
				}

				//if ( !$this->table_exists($table_name['name']) )
				//	$tables_error .= '`, `<b>'.$table_name.'</b>';
			}
			
			if ( !empty($tables_error) )
			{
				$return_string['type'] = 'error';
				$return_string['content'] = __('notexist_table', $this->_config['database'], substr($tables_error, 3).'`');
				return $return_string;
			}
			
			foreach ( $tables as $table )
			{
				$table_name = $table['name'];
				
				if ( $table_prefix==$this->_config['table_prefix'] )
					$tables_string .=  '`, `'.$table_name;
				else
					$tables_string .=  '`, `'.$this->_config['table_prefix'].$table_name;
			}
			$tables_string = substr($tables_string, 3).'`';
		}
		else
		{	
			$table_prefix = substr($tables, 0, strlen($this->_config['table_prefix']));
			if ( $table_prefix==$this->_config['table_prefix'] )
				$tables = substr($tables, strlen($this->_config['table_prefix']));

			//TODO the code could be deleted if Kohana has update above.
				
			if ( !$this->db->table_exists($tables) )
				$tables_error .= '`<b>'.$tables.'</b>`';
				
			if ( !empty($tables_error) )	
			{
				$return_string['type'] = 'error';
				$return_string['content'] = __('dbmanager.notexist_table', $this->_config['database'], $tables_error);
				return $return_string;
			}
				
			$tables_string = $this->_config['table_prefix'].$tables;
		}
		
		$return_string['type'] = 'query';
		$return_string['content'] = $tables_string;
		return $return_string;
	}
	
	/**
	 * Detect Mysql
	 *
	 * @return (array) $paths - include mysql and mysqldump application's path.
	 */
	private function detect_mysql()
	{
		$paths = array('mysql' => '', 'mysqldump' => '');
		if(substr(PHP_OS,0,3) == 'WIN') {
			$mysql_install = DB::query(Database::SELECT, "SHOW VARIABLES LIKE 'basedir'")->execute();
 
			if( $mysql_install->count()>0 ) {
				$install_path = str_replace('\\', '/', $mysql_install->get('Value'));
				$paths['mysql'] = $install_path.'bin/mysql.exe';
				$paths['mysqldump'] = $install_path.'bin/mysqldump.exe';
			} else {
				$paths['mysql'] = 'mysql.exe';
				$paths['mysqldump'] = 'mysqldump.exe';
			}
		} else {
			if(function_exists('exec')) {
				$paths['mysql'] = @exec('which mysql');
				$paths['mysqldump'] = @exec('which mysqldump');
			} else {
				$paths['mysql'] = 'mysql';
				$paths['mysqldump'] = 'mysqldump';
			}
		}
		return $paths;
	}
	
	/**
	 * Execute backup
	 *
	 * @param (array) $backup - backup configuration.
	 * @param (string) $command - execute backup command
	 * @return void if success, else reutrn error messages.
	 */
	private function execute_backup($backup, $command) {
		$info = $this->check_backup_files($backup);
		if ( !empty($info) )
			return $info;
		
		if(substr(PHP_OS, 0, 3) == 'WIN') {
			$writable_dir = $backup['filepath'];
			$tmpnam = $writable_dir.'/dbmanager_script.bat';
			$fp = fopen($tmpnam, 'w');
			fwrite($fp, $command);
			fclose($fp);
			system($tmpnam.' > NUL', $error);
			unlink($tmpnam);
		} else {
			passthru($command, $error);
		}
		
		return $error;
	}
	
	/**
	 * Check backup file
	 *
	 * @param (array) $backup - backup configuration.
	 * @return void if success, else reutrn error messages.
	 */
	private function check_backup_files($backup)
	{
		$error = array();
		if ( !file_exists($backup['filepath']))
			mkdir($backup['filepath'], 0666);
			
		if ( !file_exists($backup['mysql']) )
			array_push($error, 'file of mysql.exe is not exist');
		
		if ( !file_exists($backup['mysqldump']) )
			array_push($error, 'file of mysqldump.exe is not exist');
			
		return $error;
	}
	
	/**
	 * Check function
	 *
	 * @return void if those functions exists, else reutrn error messages.
	 */
	private function check_fuctions()
	{
		$error = array();
		if ( !function_exists('passthru') )
			array_push($error, 'function passthru not exist');
			
		if ( !function_exists('system') )
			array_push($error, 'function system not exist');
			
		if ( !function_exists('exec') )
			array_push($error, 'function exec not exist');
		
		return $error;
	}
	
} // End DBManager_Mysql_Driver