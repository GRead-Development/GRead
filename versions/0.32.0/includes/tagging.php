<?php

// This file provides HotSoup with its ability to tag books and organize user activity around a given book.

if (!defined('ABSPATH'))
{
	exit;
}

// Enqueue the BuddyPress mentioning script
function hs_enqueue_mentions_script()
{
	// Make sure we're on a page that needs mentions
	if (!function_exists('bp_is_active') || !bp_is_active('activity')) {
		return;
	}
	
	// Enqueue BP's mentions script
	wp_enqueue_script('bp-mentions');
	
	// Enqueue our custom script to extend mentions
	wp_enqueue_script(
		'hs-book-mentions',
		plugin_dir_url(__FILE__) . '../js/book-mentions.js',
		array('jquery', 'bp-mentions'),
		'1.0.0',
		true
	);
	
	// Enqueue mentions CSS
	wp_enqueue_style(
		'hs-book-mentions-css',
		plugin_dir_url(__FILE__) . '../css/book-mentions.css',
		array(),
		'1.0.0'
	);
	
	// Pass data to our script
	wp_localize_script('hs-book-mentions', 'hsMentions', array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('bp-mentions')
	));
}
add_action('wp_enqueue_scripts', 'hs_enqueue_mentions_script', 20);


// Register the mention type with BuddyPress At.js
function hs_register_book_tagging($settings)
{
	if (!isset($settings['user']))
	{
		return $settings;
	}

	// Clone the user settings as a base
	$tagging_config = $settings['user'];

	// Customize for books
	$tagging_config['at'] = '#';
	$tagging_config['suffix'] = ''; // Remove the space suffix so we can add our own closer
	
	// Set the AJAX callback
	$tagging_config['callbacks'] = array(
		'remoteFilter' => 'hsMentions.searchBooks'
	);
	
	// Template for dropdown items (removed data-value to fix the bar issue)
	$tagging_config['displayTpl'] = '<li><strong>${title}</strong> <span class="hs-book-author" style="color: #999;">(${author})</span></li>';
	
	// What gets inserted into the textarea (with closing bracket to preserve spaces)
	$tagging_config['insertTpl'] = '#[book-id-${id}:${title}]';
	
	// Search by title
	$tagging_config['searchKey'] = 'title';
	
	// Unique identifier
	$tagging_config['alias'] = 'book';
	
	// Minimum characters before search triggers
	$tagging_config['minLen'] = 2;
	
	// Maximum results
	$tagging_config['limit'] = 10;

	$settings['book'] = $tagging_config;

	return $settings;
}
add_filter('bp_mentions_atjs_settings', 'hs_register_book_tagging', 99);


// AJAX handler for book tag autocompletion
function hs_ajax_book_search()
{
	check_ajax_referer('bp-mentions', 'nonce');

	$search_query = !empty($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';

	// If the search query is empty, or is shorter than two characters
	if (empty($search_query) || strlen($search_query) < 2)
	{
		wp_send_json_success(array());
		exit;
	}

	$args = array(
		'post_type' => 'book',
		'post_status' => 'publish',
		'posts_per_page' => 10,
		's' => $search_query,
		'suppress_filters' => false
	);
	
	$book_query = new WP_Query($args);

	$suggestions = array();

	if ($book_query->have_posts())
	{
		while ($book_query->have_posts())
		{
			$book_query->the_post();
			$book_id = get_the_ID();
			$author = get_post_meta($book_id, 'book_author', true);

			$suggestions[] = array(
				'id' => $book_id,
				'title' => get_the_title($book_id),
				'author' => !empty($author) ? $author : 'Unknown Author',
				'name' => get_the_title($book_id) // Some versions of At.js need this
			);
		}

		wp_reset_postdata();
	}

	wp_send_json_success($suggestions);
	exit;
}
add_action('wp_ajax_hs_ajax_book_search', 'hs_ajax_book_search');
add_action('wp_ajax_nopriv_hs_ajax_book_search', 'hs_ajax_book_search');


// Find tags for books in activity, store them in activity meta
function hs_save_book_mentions($activity)
{
	// Updated pattern to match both old and new format
	$pattern = '/#\[book-id-(\d+)(?::([^\]]+))?\]/';

	if (preg_match_all($pattern, $activity->content, $matches))
	{
		$book_ids = $matches[1];
		$unique_ids = array_unique($book_ids);

		foreach ($unique_ids as $book_id)
		{
			// Store meta entry for each book that is mentioned
			bp_activity_add_meta($activity->id, '_hs_book_mention_id', (int) $book_id);
		}
	}
}
add_action('bp_activity_before_save', 'hs_save_book_mentions');


// Format book mentions for display
function hs_format_book_mentions_display($content)
{
	// Updated pattern to match both old and new format
	$pattern = '/#\[book-id-(\d+)(?::([^\]]+))?\]/';
	$content = preg_replace_callback($pattern, 'hs_format_book_mention_callback', $content);

	return $content;
}
add_filter('bp_get_activity_content_body', 'hs_format_book_mentions_display');
add_filter('bp_activity_comment_content', 'hs_format_book_mentions_display');


// Callback function that replaces a book's tag with a formatted link to that book's page
function hs_format_book_mention_callback($matches)
{
	$book_id = (int) $matches[1];
	$book_post = get_post($book_id);

	if ($book_post && $book_post->post_type === 'book' && $book_post->post_status === 'publish')
	{
		$book_title = get_the_title($book_post);
		$book_permalink = get_permalink($book_post);

		return '<a href="' . esc_url($book_permalink) . '" title="' . esc_attr($book_title) . '" class="hs-book-tag-link">#' . esc_html($book_title) . '</a>';
	}

	return $matches[0];
}
