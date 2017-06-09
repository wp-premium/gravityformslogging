<?php
/**
Plugin Name: Gravity Forms Logging Add-On
Plugin URI: http://www.gravityforms.com
Description: Gravity Forms Logging Add-On to be used with Gravity Forms and other Gravity Forms Add-Ons.
Version: 1.3
Author: rocketgenius
Author URI: http://www.rocketgenius.com
Text Domain: gravityformslogging
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2016 Rocketgenius
last updated: October 20, 2010

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

define( 'GF_LOGGING_VERSION', '1.3' );

// If Gravity Forms is loaded, bootstrap the Logging Add-On.
add_action( 'gform_loaded', array( 'GF_Logging_Bootstrap', 'load' ), 5 );

/**
 * Class GF_Logging_Bootstrap
 *
 * Handles the loading of the Logging Add-On and registers with the Add-On Framework.
 */
class GF_Logging_Bootstrap {

	/**
	 * If the Add-On Framework exists, Logging Add-On is loaded.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) || class_exists( 'GFLogging' ) ) {
			return;
		}

		require_once( 'class-gf-logging.php' );

		GFAddOn::register( 'GFLogging' );

	}
}

if ( ! function_exists( 'gf_logging' ) ) {
	/**
	 * Returns an instance of the GFLogging class
	 *
	 * @see    GFLogging::get_instance()
	 * @return object GFLogging
	 */
	function gf_logging() {
		return GFLogging::get_instance();
	}
}
