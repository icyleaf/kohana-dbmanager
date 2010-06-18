<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Database Manager library.
 *
 * @package    DBManager
 * @author     icyleaf
 */
class Controller_Dbmanager_Demo extends Controller_Template {

	// Use the default Kohana template
	public $template = 'dbmanager/demo';
	
	public function __construct()
	{
		$this->auto_render = false;
	}
	
	public function action_index()
	{
		$dbmanager = DBManager::instance();

//		// Database version
//		echo Kohana::debug($dbmanager->version());
//		// Table total 
//		echo Kohana::debug($dbmanager->total_tables());
//
//		// List tables
//		echo '==================<br />List tables<br />==================';
//		$tables = $dbmanager->list_tables();
//		echo Kohana::debug($tables);
//		
//		// List backup files
//		echo '==================<br />List backup files<br />==================';
//		echo Kohana::debug($dbmanager->backup_files());
//
//		// Optimize Tables
//		echo '==================<br />Optimize Tables<br />==================<br />';
//		$result = $dbmanager->optimize_tables();
//		if ($result)
//		{
//			echo Kohana::debug($result);
//		}
//
//		// Repair Tables
//		echo '==================<br />Repair Tables<br />==================<br />';
//		$result = $dbmanager->repair_tables();
//		if ($result)
//		{
//			echo Kohana::debug($result);
//		}
//
//		echo '==================<br />Backup Tables<br />==================<br />';
//		echo 'Next Backup time: '.$dbmanager->next_backup_time().'<br />';
//		echo Kohana::debug($dbmanager->backup_db());
//
//		// Download backup file.
//		$filename = '20100618170114_-_alpaca.sql';
//		$dbmanager->download_backup($filename);
//
//		// Delete backup file. $filename = '1234567890_-_database.sql'
//		echo Kohana::debug($dbmanager->delete_backup($filename));
	}
	
}