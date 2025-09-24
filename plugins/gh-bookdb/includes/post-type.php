<?php

// This creates the post type "Book", which is used by the plugin.


// If accessed directly
if (!defined('ABSPATH'))
{
	// Git out
	exit;
}



// Register the custom post type
function gr_books_register_cpt()
{
	$labels = [
		'name'		=>	_x('Books', 'Post type general name', 'gr-books'),
		'singular_name' =>	_x('Book', 'Post type singular name', 'gr-books'),
		'menu_name'	=>	_x('Books', 'Admin Menu text', 'gr-books'),
		'add_new_item'	=>	__('Add Book', 'gr-books'),
		'edit_item'	=>	__('Edit Book', 'gr-books'),
	];

	$args = [
		'labels'	=> $labels,
		'public'	=> true,
		'has_archive'	=> true,
		'rewrite'	=> ['slug' => 'books'],
		'supports'	=> ['title', 'editor', 'thumbnail'],
		'menu_icon'	=> 'dashicons-book-alt',
	];

	register_post_type('book', $args);
}

add_action('init', 'gr_books_register_cpt');


// Register ACF fields for our custom post type, using PHP
function gr_books_register_acf_fields()
{
	if (function_exists('acf_add_local_field_group'))
	{
		acf_add_local_field_group([
			'key'	=> 'group_book_details',
			'title' => 'Book Details',
			'fields'=> [
				[
					'key' => 'field_book_author',
					'label' => 'Author',
					'name' => 'book_author',
					'type' => 'text',
					'required' => 1,
				],

				[
					'key' => 'field_book_isbn',
					'label' => 'ISBN',
					'name' => 'book_isbn',
					'type' => 'text',
				],

				[
					'key' => 'field_publication_year',
					'label' => 'Publication year',
					'name' => 'publication_year',
					'type' => 'number',
				],

				[
					'key' => 'field_nop',
					'label' => 'Number of pages',
					'name' => 'nop',
					'type' => 'number',
				],
			],

			'location' => [[[
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'book',
			]]],
		]);
	}
}

add_action('acf/init', 'gr_books_register_acf_fields');
