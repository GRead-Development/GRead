<?php

// This provides GRead with a theme management system for administrators, a database for storing themes, and a much easier interface for creating and editing themes.


if (!defined('ABSPATH'))
{
	return;
}


// Database setup/configuration


// When the plugin is activated, create the themes database
function hs_themes_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_themes';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name(
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		slug varchar(50) NOT NULL,
		name varchar(100) NOT NULL,
		preview_color varchar(7) NOT NULL,
		bg_color varchar(7) NOT NULL,
		text_color varchar(7) NOT NULL,
		link_color varchar(7) NOT NULL,
		link_hover_color varchar(7) NOT NULL,
		header_bg varchar(7) NOT NULL,
		widget_bg varchar(7) NOT NULL,
		border_color varchar(7) NOT NULL,
		button_bg varchar(7) NOT NULL,
		button_hover_bg varchar(7) NOT NULL,
		unlock_metric varchar(50),
		unlock_message text,
		is_default tinyint(1) DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);


	$default_exists = $wpdb -> get_var("SELECT id FROM $table_name WHERE slug = 'default'");

	if (!default_exists)
	{
		$wpdb -> insert($table_name, [
		'slug' => 'classic',
		'name' => 'Classic',
		'preview_color' => '#0073aa',
		'bg_color' => '#ffffff',
		'text_color' => '#333333',
		'link_color' => '#0073aa',
		'link_hover_color' => '#005a87',
		'header_bg' => '#ffffff',
		'widget_bg' => '#f9f9f9',
		'border_color' => '#e0e0e0',
		'button_bg' => '#0073aa',
		'button_hover_bg' => '#005a87',
		'is_default' => 1
		]);
	}
}
register_activation_hook(__FILE__, 'hs_themes_create_table');
