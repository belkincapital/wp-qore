<?php

/**
 *  WPQORE_SAFE_PATH
 *  Makes path safe for any OS
 *  Paths should ALWAYS READ be "/"
 * 		uni: /home/path/file.xt
 * 		win:  D:/home/path/file.txt 
 *  @param string $path		The path to make safe
 */
function WPQORE_safe_path($path) {
    return str_replace("\\", "/", $path);
}

/**
 *  WPQORE_BYTESIZE
 *  Display human readable byte sizes
 *  @param string $size		The size in bytes
 */
function WPQORE_bytesize($size) {
    try {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $size >= 1024 && $i < 4; $i++)
            $size /= 1024;
        return round($size, 2) . $units[$i];
    } catch (Exception $e) {
        return "n/a";
    }
}

/**
 *  WPQORE_DIRSIZE
 *  Get the directory size recursively, but don't calc the snapshot directory, exclusion diretories
 *  @param string $directory		The directory to calculate
 */
function WPQORE_dirInfo($directory) {
    try {

        $size = 0;
        $count = 0;
        $folders = 0;
        $flag = false;

        //EXCLUDE: Snapshot directory
        $directory = WPQORE_safe_path($directory);
        if (strstr($directory, WPQORE_SSDIR_PATH)) {
            return;
        }

        //EXCLUDE: Directory Exclusions List
        if ($GLOBALS['WPQORE_bypass-array'] != null) {
            foreach ($GLOBALS['WPQORE_bypass-array'] as $val) {
                if (WPQORE_safe_path($val) == $directory) {
                    return;
                }
            }
        }

        if ($handle = @opendir($directory)) {
            while (false !== ($file = @readdir($handle))) {
                if ($file != '.' && $file != '..') {
                    $nextpath = $directory . '/' . $file;
                    if (is_dir($nextpath)) {
                        $folders++;
                        $result = WPQORE_dirInfo($nextpath);
                        $size += $result['size'];
                        $count += $result['count'];
                        $folders += $result['folders'];
                    } else if (is_file($nextpath) && is_readable($nextpath)) {
                        if (!in_array(@pathinfo($nextpath, PATHINFO_EXTENSION), $GLOBALS['WPQORE_skip_ext-array'])) {
                            $fmod = @filesize($nextpath);
                            if ($fmod === false) {
                                $flag = true;
                            } else {
                                $size += @filesize($nextpath);
                            }
                            $count++;
                        }
                    }
                }
            }
        }
        @closedir($handle);
        $total['size'] = $size;
        $total['count'] = $count;
        $total['folders'] = $folders;
        $total['flag'] = $flag;
        return $total;
    } catch (Exception $e) {
        WPQORE_log("log:fun__dirInfo=>runtime error: " . $e . "\nNOTE: Try excluding the stat failed to the WPQOREs directory exclusion list or change the permissions.");
    }
}

define("WPQORE_SSDIR_NAME",     'wp-snapshots');
define('WPQORE_WPROOTPATH',     str_replace("\\", "/", ABSPATH));
define("WPQORE_SSDIR_PATH",     str_replace("\\", "/", WPQORE_WPROOTPATH . WPQORE_SSDIR_NAME));

class WPQOREUtils {

    /** METHOD: GET_MICROTIME
     * Get current microtime as a float. Can be used for simple profiling.
     */
    static public function GetMicrotime() {
        return microtime(true);
    }

    /** METHOD: ELAPSED_TIME
     * Return a string with the elapsed time.
     * Order of $end and $start can be switched. 
     */
    static public function ElapsedTime($end, $start) {
        return sprintf("%.4f sec.", abs($end - $start));
    }
	
	 /**
     * MySQL server variable
     * @param conn $dbh Database connection handle
     * @return string the server variable to query for
     */
    static public function MysqlVariableValue($variable) {
		global $wpdb;
        $row = $wpdb->get_row("SHOW VARIABLES LIKE '{$variable}'", ARRAY_N);
        return isset($row[1]) ? $row[1] : null;
    }
	
	 /**
     * ListDirs
     * @path path to a system directory
     * @return array of all directories in that path
     */
	static public function ListDirs($path = '.') {
		$dirs = array();

		foreach (new DirectoryIterator($path) as $file) {
			if ($file->isDir() && !$file->isDot()) {
				$dirs[] = WPQORE_safe_path($file->getPathname());
			}
		}

		return $dirs;
	}
    
}

/**
 *  WPQORE_RUN_APC
 *  Runs the APC cache to pre-cache the php files
 *  returns true if all files where cached
 */
function WPQORE_run_apc() {
	if(function_exists('apc_compile_file')){
		$file01 = @apc_compile_file(WPQORE_PLUGIN_PATH . "WPQORE.php");
		return ($file01);
	} else {
		return false;
	}
}

	ob_start();
	phpinfo();
	$serverinfo = ob_get_contents();
	ob_end_clean();
	
	$serverinfo = preg_replace( '%^.*<body>(.*)</body>.*$%ms',  '$1',  $serverinfo);
	$serverinfo = preg_replace( '%^.*<title>(.*)</title>.*$%ms','$1',  $serverinfo);
	$action_response = __("Settings Saved", 'wp-qore');
	$dbvar_maxtime  = WPQOREUtils::MysqlVariableValue('wait_timeout');
	$dbvar_maxpacks = WPQOREUtils::MysqlVariableValue('max_allowed_packet');
	$dbvar_maxtime  = is_null($dbvar_maxtime)  ? __("unknow", 'wp-qore') : $dbvar_maxtime;
	$dbvar_maxpacks = is_null($dbvar_maxpacks) ? __("unknow", 'wp-qore') : $dbvar_maxpacks;	

	global $WPQORESettings;
	global $wp_version;
	global $wpdb;
	
	$action_updated = null;
	if (isset($_POST['action']) && $_POST['action'] == 'save') {
		//General Tab
		$WPQORESettings->Set('uninstall_settings',	isset($_POST['uninstall_settings']) ? "1" : "0");
		$WPQORESettings->Set('uninstall_files',		isset($_POST['uninstall_files'])  ? "1" : "0");
		$WPQORESettings->Set('uninstall_tables',	isset($_POST['uninstall_tables']) ? "1" : "0");
		
		$action_updated  = $WPQORESettings->Save();
	} 
	
	$space = @disk_total_space(WPQORE_WPROOTPATH);
	$space_free = @disk_free_space(WPQORE_WPROOTPATH);
	$perc = @round((100/$space)*$space_free,2);
	

/*

//all available functions to call

<?php _e("Operating System", 'wp-qore'); ?>
<?php echo PHP_OS ?>
					   					   
<?php _e("Web Server", 'wp-qore'); ?>
<?php echo $_SERVER['SERVER_SOFTWARE'] ?>
					   
<?php _e("APC Enabled", 'wp-qore'); ?>
<?php echo WPQORE_run_apc() ? 'Yes' : 'No'  ?>
					   					   
<?php _e("Root Path", 'wp-qore'); ?>
<?php echo WPQORE_WPROOTPATH ?>
					   						   
<?php _e("Plugins Path", 'wp-qore'); ?>
<?php echo WPQORE_safe_path(WP_PLUGIN_DIR) ?>
					   						   
<?php _e("Packages Built", 'wp-qore'); ?>
<?php echo get_option('WPQORE_pack_passcount', 0) ?>
<?php _e("The number of successful packages created.", 'wp-qore'); ?></i>
						   
<?php _e("Version", 'wp-qore'); ?>
<?php echo $wp_version ?>
					   
<?php _e("Langugage", 'wp-qore'); ?>
<?php echo get_bloginfo('language') ?>
					   	
<?php _e("Charset", 'wp-qore'); ?>
<?php echo get_bloginfo('charset') ?>
					   
<?php _e("Memory Limit ", 'wp-qore'); ?>
<?php echo WP_MEMORY_LIMIT ?>
					   
<?php _e("Memory Limit Max", 'wp-qore'); ?>
<?php echo WP_MAX_MEMORY_LIMIT ?>
					   							   
<?php _e("Version", 'wp-qore'); ?>
<?php echo phpversion() ?>

<?php _e("SAPI", 'wp-qore'); ?>					   	
<?php echo PHP_SAPI ?>
					   
<?php _e("User", 'wp-qore'); ?>
<?php echo get_current_user(); ?>
					   
<?php _e("Safe Mode", 'wp-qore'); ?>
<?php echo (((strtolower(@ini_get('safe_mode')) == 'on') || (strtolower(@ini_get('safe_mode')) == 'yes') || (strtolower(@ini_get('safe_mode')) == 'true') ||  (ini_get("safe_mode") == 1 ))) ? __('On', 'wp-qore') : __('Off', 'wp-qore'); ?>
						   
<?php _e("Memory Limit", 'wp-qore'); ?>
<?php echo @ini_get('memory_limit') ?>
					   
<?php _e("Memory In Use", 'wp-qore'); ?>
<?php echo size_format(@memory_get_usage(TRUE), 2) ?>
					   
<?php _e("Max Execution Time", 'wp-qore'); ?>
<?php echo @ini_get( 'max_execution_time' ); ?>
					   					   					   
<?php _e("Version", 'wp-qore'); ?>
<?php echo $wpdb->db_version() ?>
					   
<?php _e("Charset", 'wp-qore'); ?>
<?php echo DB_CHARSET ?>
					   
<?php _e("wait_timeout", 'wp-qore'); ?>
<?php echo $dbvar_maxtime ?>
					   
<?php _e("max_allowed_packet", 'wp-qore'); ?>
<?php echo $dbvar_maxpacks ?>
					   
<?php _e('Free space', 'wp-qore'); ?>
<?php echo $perc;?> 
<?php echo WPQORE_bytesize($space_free);?>
<?php echo WPQORE_bytesize($space);?>
<?php _e("Note: This value is the physical servers hard-drive allocation.", 'wp-qore'); ?> <br/>
<?php _e("On shared hosts check your control panel for the 'TRUE' disk space quota value.", 'wp-qore'); ?>
									  
<?php echo "{$serverinfo}"; ?>

*/

?>
