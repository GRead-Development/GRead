<?php

// Prevent users from accessing this, directly
if (!defined('ABSPATH'))
{
	// Quit
	exit;
}


// When the plugin is activated, create all the tables that GRead requires.
function gr_create_tables()
{
	// The Wordpress database
	global $wpdb
	require once (ABSPATH . 'wp-admin/includes/upgrade.php');
	$charset_collate = $wpdb -> get_charset_collate();


	// The table used for tracking reading progress
	$table_progress = $wpdb -> prefix . 'gr_user_book_progress';

	$sql_progress = "CREATE TABLE $table_progress (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			userid BIGINT(20) UNSIGNED NOT NULL,
			bookid BIGINT(20) UNSIGNED NOT NULL,
			pages_read INT(5) DEFAULT 0 NOT NULL,
			status VARCHAR(20) DEFAULT 'to-read' NOT NULL,
			started DATETIME,
			completed DATETIME,
			last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_book (userid, bookid),
			KEY userid (userid),
			KEY bookid (bookid)) $charset_collate;";

	dbDelta($sql_progress);


	// The table that is used to store and retrieve users' notes
	$table_notes = $wpdb -> prefix . 'gr_user_notes';

	$sql_notes = "CREATE TABLE $table_notes (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		userid BIGINT(20) UNSIGNED NOT NULL,
		bookid BIGINT(20) UNSIGNED NOT NULL,
		note TEXT NOT NULL,
		page_number INT(5),
		privacy_setting VARCHAR(20) DEFAULT 'private' NOT NULL,
		privacy_meta BIGINT(20) UNSIGNED,
		created DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY user_book_index (userid, bookid)) $charset_collate;";

	dbDelta($sql_notes);
}
