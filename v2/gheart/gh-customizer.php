<?php
// This plugin is used by GHeart in order to allow users to customize their profiles.


if (!defined('ABSPATH'))
{
	exit;
}


define('GR_CUSTOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GR_CUSTOM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GR_CUSTOM_PLUGIN_DIR . 'includes/customization.php';

?>
