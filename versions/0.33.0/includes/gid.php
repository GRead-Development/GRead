<?php
// Provides HotSoup with a way to track books across different printings.
// The goal is to merge multiple printings of a book into a single post.


// Activate the GID system
function hs_gid_activate()
{
	global $wpdb;
	
	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix} hs_gid
	(
		if INT PRIMARY KEY AUTO_INCREMENT,
		post_id INT UNIQUE,
		gid INT,
		merged_by INT,
		merge_reason TEXT,
		date_merged DATETIME,
		is_canonical TINYINT(1) DEFAULT 0,
		INDEX (gid),
		INDEX (post_id)
	)");
	
	// Duplicate reports
	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix} hs_duplicate_reports (
		id INT PRIMARY KEY AUTO_INCREMENT,
		reporter_id INT,
		primary_book_id INT,
		reason TEXT,
		status ENUM('pending', 'merged', 'rejected') DEFAULT 'pending',
		date_reported DATETIME,
		reviewed_by INT,
		INDEX (status),
		INDEX (primary_book_id),
		UNIQUE KEY report_unique (reporter_id, primary_book_id, duplicate_book_id)
		)");
}
