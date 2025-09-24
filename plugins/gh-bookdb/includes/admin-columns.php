<?php

// This provides us with a Books list in our dashboard. Nice!

if (!defined('ABSPATH'))
{
	exit;
}



// Adds the custom columns to Book CPT admin list
function gr_books_add_admin_columns($columns)
{
	$columns['book_author'] = __('Author', 'gr-books');
	$columns['book_isbn'] = __('ISBN', 'gr-books');

	return $columns;
}

add_filter('manage_book_posts_columns', 'gr_books_add_admin_columns');


// Populate them columnz.
function gr_books_custom_column_content($column, $post_id)
{
	switch ($column)
	{
		case 'book_author':
			echo esc_html(get_field('book_author', $post_id));
			break;

		case 'book_isbn':
			echo esc_html(get_field('book_isbn', $post_id));
			break;
	}
}

add_action('manage_book_posts_custom_column', 'gr_books_custom_column_content', 10, 2);
