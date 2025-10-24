<?php

// This is used to set up the user unlocks table and the available unlocks table.

if (!defined('ABSPATH'))
{
	exit;
}


function hs_configure_rewards()
{
	global $wpdb;
	$charset_collate = $wpdb -> get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';


	// Milestones table
	$table_unlockables = $wpdb -> prefix . 'hs_unlockables';

	$sql_unlockables = "
		CREATE TABLE $table_unlockables(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text,
			icon_url varchar(255),
			metric varchar(50) NOT NULL,
			requirement int(11) NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";
	dbDelta($sql_unlockables);


	// Table for tracking users' unlocks
	$table_user_unlocks = $wpdb -> prefix . 'hs_user_unlocks';

	$sql_user_unlocks = "
		CREATE TABLE $table_user_unlocks(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			unlockable_id mediumint(9) NOT NULL,
			date_unlocked datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_unlockable_unique (user_id, unlockable_id)
		) $charset_collate;";
	dbDelta($sql_user_unlocks);
}
