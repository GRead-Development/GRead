<?php

// Allows admins to index the most frequently used databases


function hs_index_databases()
{
	global $wpdb;

	$user_books_table = $wpdb -> prefix . 'user_books';
	$wpdb -> query("ALTER TABLE {$user_books_table} ADD INDEX idx_user_id (user_id)");
	$wpdb -> query("ALTER TABLE {$user_books_table} ADD INDEX idx_book_id (book_id)");

	// Index the reviews table
	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';
	$wpdb -> query("ALTER TABLE {$reviews_table} ADD INDEX idx_book_id (book_id)");

}


// Enable the tool page for admins
function hs_index_db_page()
{
	if (isset($_GET['page']) && $_GET['page'] === 'hs-add-indexes' && current_user_can('manage_options'))
	{
		hs_index_databases();
	}
}
add_action('admin_init', 'hs_index_db_page');
