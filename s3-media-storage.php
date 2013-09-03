<?php
/**
 * @package S3 Media Storage
 */
/*
Plugin Name: S3 Media Storage
Description: Store media library contents onto S3 directly without the need for temporarily storing files on the filesystem/cron jobs. This is more ideal for multiple web server environemnts.
Version: 0.9 beta
Author: Anthony Gentile
Author URI: http://agentile.com
*/

/*  Copyright 2013  Anthony Gentile  (email : asgentile@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'S3 Media Storage WordPress Plugin';
	exit;
}

define('S3MS_PLUGIN_VERSION', '0.9');
define('S3MS_PLUGIN_URL', plugin_dir_url( __FILE__ ));

function install() {
}

register_activation_hook(__FILE__, 'install');

function uninstall() {
}

register_uninstall_hook(__FILE__, 'uninstall');

if ( is_admin() )
	require_once dirname( __FILE__ ) . '/admin.php';
    
