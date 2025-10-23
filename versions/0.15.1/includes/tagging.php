<?php

// This file provides HotSoup with its ability to tag books and organize user activity around a given book.


if (!defined('ABSPATH'))
{
	exit;
}


// Register the mention type with BuddyPress At.js: #[
function hs_register_book_tagging($settings)
{
	if (!isset($settings['user']))
	{
		return $settings;
	}

	$tagging_config = $settings['user'];


	// Sets the string that is used to trigger tagging
	$tagging_config['at'] = '#[';

	// Sets the string used to close a tag
	$tagging_config['suffix'] = '] ';

	// Sets AJAX action to be called
	$tagging_config['data'] = array(
		'action' => 'hs_ajax_book_search',
		'nonce' => wp_create_nonce('bp-mentions'),
	);

	// Sets the template for dropdown list
	$tagging_config['display_tpl'] = '<li><a href="#"><strong class="book-title">${title}</strong> <span>(${author})</span></a></li>';

	// Sets the template, based on whatever is inserted, wrapped by the prefix and suffix set above
	$tagging_config['insert_tpl'] = 'book-id-${id}';

	// Set internal indentifiers
	$tagging_config['look_up'] = 'title';
	$tagging_config['alias'] = 'book';

	$settings['book'] = $tagging_config;

	return $settings;
}
add_filter('bp_mentions_atjs_settings', 'hs_register_book_tagging', 99);


// AJAX handler for book tag autocompletion
function hs_ajax_book_search()
{
	check_ajax_referer('bp-mentions');

	$search_query = ! empty($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';

	// If the search query is empty, or is shorter than two characters
	if (empty($search_query) || strlen($search_query) < 2)
	{
		wp_send_json_success(array());
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

	if ($book_query -> have_posts())
	{
		while ($book_query -> have_posts())
		{
			$book_query -> the_post();
			$book_id = get_the_ID();
			$author = get_post_meta($book_id, 'book_author', true);

			$suggestions[] = array(
				'id' => $book_id,
				'title' => get_the_title($book_id),
				'author' => !empty($author) ? $author : 'Unknown Author',
			);
		}

		wp_reset_postdata();
	}

	wp_send_json_success(array('suggestions' => $suggestions));
}
add_action('wp_ajax_hs_ajax_book_search', 'hs_ajax_book_search');


// Find tags for books in activity, store them in activity meta
function hs_save_book_mentions($activity)
{
	// Use regex to find all tags
	$pattern = '/#\[book-id-(\d+)\]/';

	if (preg_match_all($pattern, $activity -> content, $matches))
	{
		$book_ids = $matches[1];
		$unique_ids = array_unique($book_ids);

		foreach ($unique_ids as $book_id)
		{
			// Store meta entry for each book that is mentioned
			bp_activity_add_meta($activity -> id, '_hs_book_mention_id', (int) $book_id);

		}
	}
}
add_action('bp_activity_before_save', 'hs_save_book_mentions');


function hs_format_book_mentions_display($content)
{
	$pattern = '/#\[book-id-(\d+)\]/';
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

	if ($book_post && $book_post -> post_type === 'book' && $book_post -> post_status === 'publish')
	{
		$book_title = get_the_title($book_post);
		$book_permalink = get_permalink($book_post);

		return '<a href="' . esc_url($book_permalink) . '" title="' . esc_attr($book_title) . '" class=hs-book-tag-link">#' . esc_html($book_title) . '</a>';
	}

	return $matches[0];
}
