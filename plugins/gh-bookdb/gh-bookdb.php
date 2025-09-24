<?php
/**
 * Plugin Name:		GH-BookDB
 * Description:		Establishes the database that is used to store and display books.
 * Version:		1.0
 * Author:		Daniel Teberian
 */


if (!defined('ABSPATH'))
{
	exit;
}


define('GR_BOOKDB_PLUGIN_DIR', plugin_dir_path(__FILE__));


// Includes
require_once GR_BOOKDB_PLUGIN_DIR . 'includes/post-type.php';
require_once GR_BOOKDB_PLUGIN_DIR . 'includes/admin-columns.php';
require_once GR_BOOKDB_PLUGIN_DIR . 'includes/shortcode.php';
