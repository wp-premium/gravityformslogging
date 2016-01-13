<?php
/*
Plugin Name: Gravity Forms Logging Add-On
Plugin URI: http://www.gravityforms.com
Description: Gravity Forms Logging Add-On to be used with Gravity Forms and other Gravity Forms Add-Ons
Version: 1.0
Author: rocketgenius
Author URI: http://www.rocketgenius.com

------------------------------------------------------------------------
Copyright 2009 rocketgenius

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

add_action('init',  array('GFLogging', 'init'));
register_activation_hook( __FILE__, array("GFLogging", "add_permissions"));

class GFLogging {

    private static $path = "gravityformslogging/logging.php";
    private static $url = "http://www.gravityforms.com";
    private static $slug = "gravityformslogging";
    private static $version = "1.0";
    private static $min_gravityforms_version = "1.6.2";
    private static $loggers = array();
    private static $max_file_size = 409600; //bytes
    private static $max_file_count = 10;
    private static $date_format_log_file = "YmdGis";

    //Plugin starting point. Will load appropriate files
    public static function init(){

    	self::include_logger();

        if(RG_CURRENT_PAGE == "plugins.php"){
            //loading translations
            load_plugin_textdomain('gravityformslogging', FALSE, '/gravityformslogging/languages' );

            add_action('after_plugin_row_' . self::$path, array('GFLogging', 'plugin_row') );

           //force new remote request for version info on the plugin page
            self::flush_version_info();
        }

        if(!self::is_gravityforms_supported()){
           return;
        }

        if(is_admin()){
            //loading translations
            load_plugin_textdomain('gravityformslogging', FALSE, '/gravityformslogging/languages' );

            add_filter("transient_update_plugins", array('GFLogging', 'check_update'));
            add_filter("site_transient_update_plugins", array('GFLogging', 'check_update'));
            add_action('install_plugins_pre_plugin-information', array('GFLogging', 'display_changelog'));

            //creates a new Settings page on Gravity Forms' settings screen
            if(self::has_access("gravityforms_logging")){
                RGForms::add_settings_page("Logging", array("GFLogging", "settings_page"), "");
            }
        }

        //integrating with Members plugin
        if(function_exists('members_get_capabilities'))
            add_filter('members_get_capabilities', array("GFLogging", "members_get_capabilities"));

        if(self::is_logging_page()){
            //loading upgrade lib
            require_once("plugin-upgrade.php");
        }
    }

    //--------------   Automatic upgrade ---------------------------------------------------

    public static function flush_version_info(){
        require_once("plugin-upgrade.php");
        RGLoggingUpgrade::set_version_info(false);
    }

    public static function plugin_row(){
        if(!self::is_gravityforms_supported()){
            $message = sprintf(__("Gravity Forms " . self::$min_gravityforms_version . " is required. Activate it now or %spurchase it today!%s"), "<a href='http://www.gravityforms.com'>", "</a>");
            RGLoggingUpgrade::display_plugin_message($message, true);
        }
        else{
            $version_info = RGLoggingUpgrade::get_version_info(self::$slug, self::get_key(), self::$version);

            if(!$version_info["is_valid_key"]){
                $new_version = version_compare(self::$version, $version_info["version"], '<') ? __('There is a new version of the Gravity Forms Logging Add-On available.', 'gravityformslogging') .' <a class="thickbox" title="Gravity Forms Logging Add-On" href="plugin-install.php?tab=plugin-information&plugin=' . self::$slug . '&TB_iframe=true&width=640&height=808">'. sprintf(__('View version %s Details', 'gravityformslogging'), $version_info["version"]) . '</a>. ' : '';
                $message = $new_version . sprintf(__('%sRegister%s your copy of Gravity Forms to receive access to automatic upgrades and support. Need a license key? %sPurchase one now%s.', 'gravityformslogging'), '<a href="admin.php?page=gf_settings">', '</a>', '<a href="http://www.gravityforms.com">', '</a>') . '</div></td>';
                RGLoggingUpgrade::display_plugin_message($message);
            }
        }
    }

    //Displays current version details on Plugin's page
    public static function display_changelog(){
        if($_REQUEST["plugin"] != self::$slug)
            return;

        //loading upgrade lib
        require_once("plugin-upgrade.php");

        RGLoggingUpgrade::display_changelog(self::$slug, self::get_key(), self::$version);
    }

    public static function check_update($update_plugins_option){
        require_once("plugin-upgrade.php");

        return RGLoggingUpgrade::check_update(self::$path, self::$slug, self::$url, self::$slug, self::get_key(), self::$version, $update_plugins_option);
    }

    private static function get_key(){
        if(self::is_gravityforms_supported())
            return GFCommon::get_key();
        else
            return "";
    }
    //---------------------------------------------------------------------------------------

    //Returns true if the current page is an Feed pages. Returns false if not
    private static function is_logging_page(){
        $current_page = trim(strtolower(rgget("page")));
        $logging_pages = array("gf_logging");

        return in_array($current_page, $logging_pages);
    }

    public static function settings_page(){

        require_once("plugin-upgrade.php");

        //get the plugins that support logging
        $supported_plugins = apply_filters("gform_logging_supported", array());
        asort($supported_plugins);

        if(!rgempty("uninstall")){
	    	check_admin_referer("uninstall", "gf_logging_uninstall");
	        self::uninstall();

	        ?>
	        <div class="updated fade" style="padding:20px;"><?php _e(sprintf("Gravity Forms Logging Add-On has been successfully uninstalled. It can be re-activated from the %splugins page%s.", "<a href='plugins.php'>","</a>"), "gravityformslogging")?></div>
	        <?php
	        return;
        }
        //see if the form was submitted
        elseif(!rgempty("gf_logging_submit")){
            //update database with settings
            check_admin_referer("update", "gf_logging_update");
            $settings = array();
            foreach($supported_plugins as $slug => $name){
            	$field_name = "gf_" . $slug . "_log_level";
            	$settings[$slug] = rgpost($field_name);
			}
            update_option("gf_logging_settings", $settings);
		}
		else
		{
			//not a database update; pull settings from database
			$settings = get_option("gf_logging_settings");
		}

        ?>
        <form method="post" action="">
            <?php wp_nonce_field("update", "gf_logging_update") ?>
            <h3><?php _e("Plugin Logging Settings", "gravityformslogging") ?></h3>
            <table class="form-table">
        <?php
        $upload_dir = RGFormsModel::get_upload_root();
		$upload_url = self::get_upload_url_root();
		$log_dir = $upload_dir . "logs/";
		$log_url = $upload_url . "logs/";

		foreach($supported_plugins as $slug => $name){
			$field_name = "gf_" . $slug . "_log_level";
			?>
			<tr>
				<td>
					<label for="<?php echo $field_name ?>" class="inline"><?php echo $name; ?></label>
				</td>
				<td>
            		<select id="<?php echo $field_name ?>" name="<?php echo $field_name ?>">
	                    <option value="<?php echo KLogger::OFF ?>" <?php echo rgar($settings, $slug) == KLogger::OFF ? "selected='selected'" : "" ?>><?php _e("Off", "gravityformslogging") ?></option>
	                    <option value="<?php echo KLogger::DEBUG ?>" <?php echo rgar($settings, $slug) == KLogger::DEBUG ? "selected='selected'" : "" ?>><?php _e("Log all messages", "gravityformslogging") ?></option>
	                    <option value="<?php echo KLogger::ERROR ?>" <?php echo rgar($settings, $slug) == KLogger::ERROR ? "selected='selected'" : "" ?>><?php _e("Log errors only", "gravityformslogging") ?></option>
					</select>
	                <?php
    				$log_file = $slug . ".txt";
	                if(file_exists($log_dir . $log_file)){
	                    ?>
	                    &nbsp;<a href="<?php echo $log_url . $log_file ?>" target="_blank"><?php _e("view log", "gravityformslogging") ?></a>
	                    <?php
	                }
	                ?>
	            </td>
	        </tr>
		<?php
		}
		?>
			<tr>
            	<td colspan="2" ><input type="submit" name="gf_logging_submit" class="button-primary" value="<?php _e("Save Settings", "gravityformslogging") ?>" /></td>
	        </tr>
		</table>
        </form>
        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_logging_uninstall") ?>
            <?php
            if(GFCommon::current_user_can_any("gravityforms_logging_uninstall")){ ?>
                <div class="hr-divider"></div>

                <h3><?php _e("Uninstall Gravity Forms Logging Add-On", "gravityformslogging") ?></h3>
                <div class="delete-alert"><?php _e("Warning! This operation removes ALL log files and logging settings.", "gravityformslogging") ?>
                    <input type="submit" name="uninstall" value="<?php _e("Uninstall Logging Add-On", "gravityformslogging") ?>" class="button" onclick="return confirm('<?php _e("Warning! ALL log files and logging settings will be removed. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravityformslogging") ?>'); "/>
                </div>
            <?php
            } ?>
        </form>
        <?php
    }

    public static function add_permissions(){
        global $wp_roles;
        $wp_roles->add_cap("administrator", "gravityforms_logging");
        $wp_roles->add_cap("administrator", "gravityforms_logging_uninstall");
    }

    //Target of Member plugin filter. Provides the plugin with Gravity Forms lists of capabilities
    public static function members_get_capabilities( $caps ) {
        return array_merge($caps, array("gravityforms_logging", "gravityforms_logging_uninstall"));
    }

    public static function disable_logging(){
        delete_option("gf_logging_settings");
    }

    public static function uninstall(){

        if(!GFLogging::has_access("gravityforms_logging_uninstall"))
            die(__("You don't have adequate permission to uninstall the Gravity Forms Logging Add-On.", "gravityformslogging"));

        //removing options
        delete_option("gf_logging_settings");

        //deleting log files
        self::delete_log_files();

        //Deactivating plugin
        $plugin = "gravityformslogging/logging.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
    }

    private static function delete_log_files(){
        $dir = self::get_log_dir();
        if(is_dir($dir)){
            $files = glob("{$dir}{,.}*", GLOB_BRACE); // get all file names
            foreach($files as $file){ // iterate files
              if(is_file($file))
                unlink($file); // delete file
            }
            rmdir($dir);
        }
    }

	public static function log_message($plugin, $message = null, $message_type = Klogger::DEBUG){
        //abort if message is blank
        if(empty($message))
            return;

        self::include_logger();

        $settings = get_option("gf_logging_settings");
        $log_level = rgempty($plugin, $settings) ? KLogger::OFF : $settings[$plugin];

        //abort if logging is turned off
        if($log_level == KLogger::OFF)
            return;

        //getting logger
        $log = self::get_logger($plugin, $log_level);
        $log->Log($message, $message_type);
	}

    private static function reset_logs($file_path, $gmt_offset){
        $path = pathinfo($file_path);
        $folder = $path["dirname"] . "/";
        $file_base = $path["filename"];
        $file_ext  = $path["extension"];
        //check size of current file, if greater than certain size, rename using year, month, day, hour, minute, second
        if (file_exists($file_path) && filesize($file_path) > self::$max_file_size)
        {
            $adjusted_date = gmdate(self::$date_format_log_file, time() + $gmt_offset);
            $new_name = $file_base . "_" . $adjusted_date . "." . $file_ext;
            rename($file_path, $folder . $new_name);
        }
        //check quantity of files and delete older ones if too many
        //get files which match the base filename
        $aryFiles = glob($folder . $file_base . "*.*");
        $fileCount = count($aryFiles);

        if ($aryFiles != false && $fileCount > self::$max_file_count)
        {
            //sort by date so oldest are first
            usort($aryFiles, create_function('$a,$b', 'return filemtime($a) - filemtime($b);'));
            $countDelete = $fileCount - self::$max_file_count;
            for ($i=0;$i<$countDelete;$i++)
            {
                if (file_exists($aryFiles[$i]))
                {
                    unlink($aryFiles[$i]);
                }
            }
        }
    }

    private static function get_log_file_name($plugin_name){
        $log_dir = self::get_log_dir();
        wp_mkdir_p($log_dir);
        return $log_dir . $plugin_name . ".txt";
    }

    private static function get_log_dir(){
        $upload_dir = RGFormsModel::get_upload_root();
        $log_dir = $upload_dir . "logs/";
        return $log_dir;
    }

    private static function get_logger($plugin, $log_level){
        if(isset(self::$loggers[$plugin])){
            //using existing logger
            $log = self::$loggers[$plugin];
        }
        else{
            //creating new logger

            //get time offset
            $offset = get_option('gmt_offset') * 3600;

            //getting log file name
            $log_file_name = self::get_log_file_name($plugin);

            $log = new KLogger($log_file_name, $log_level, $offset, $plugin);
            self::$loggers[$plugin] = $log;

            //clean up log files
            self::reset_logs($log_file_name, $offset);
        }

        return $log;
    }

    private static function is_gravityforms_installed(){
        return class_exists("RGForms");
    }

    private static function is_gravityforms_supported(){
        if(class_exists("GFCommon")){
            $is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
            return $is_correct_version;
        }
        else{
            return false;
        }
    }

    private static function has_access($required_permission){
        $has_members_plugin = function_exists('members_get_capabilities');
        $has_access = $has_members_plugin ? current_user_can($required_permission) : current_user_can("level_7");
        if($has_access)
            return $has_members_plugin ? $required_permission : "level_7";
        else
            return false;
    }

    //Returns the url of the plugin's root folder
    private static function get_base_url(){
        return plugins_url(null, __FILE__);
    }

    //Returns the physical path of the plugin's root folder
    private static function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }

    private static function get_upload_url_root(){
        $dir = wp_upload_dir();

        if($dir["error"])
            return null;

        return $dir["baseurl"] . "/gravity_forms/";
    }

    public static function include_logger(){
		if(!class_exists("KLogger")){
            require_once(self::get_base_path() . "/KLogger.php");
        }
    }

}

if(!function_exists("rgar")){
function rgar($array, $name){
    if(isset($array[$name]))
        return $array[$name];

    return '';
}
}
if(!function_exists("rgempty")){
function rgempty($name, $array = null){
    if(!$array)
        $array = $_POST;

    $val = rgget($name, $array);
    return empty($val);
}
}
if(!function_exists("rgget")){
function rgget($name, $array=null){
    if(!isset($array))
        $array = $_GET;

    if(isset($array[$name]))
        return $array[$name];

    return "";
}
}
?>
