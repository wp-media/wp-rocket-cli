<?php
/*
 * Plugin Name: WP Rocket CLI
 * Plugin URI:
 * Description:
 * Version: 1.0
 * Author: WP Rocket
 * Author URI: http://wp-rocket.me
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */


if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require( 'command.php' );
}
