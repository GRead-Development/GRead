<?php

/**
 * Book Tags Manager
 * Provides an admin interface for adding/modifying book tags
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Create the book tags table on plugin activation
 */
function hs_book_tags_create_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'hs_book_tags';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		tag_name VARCHAR(100) NOT NULL,
		tag_slug VARCHAR(100) NOT NULL,
		usage_count INT DEFAULT 1,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY book_tag (book_id, tag_slug),
		INDEX (tag_slug),
		INDEX (book_id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

// Create the admin page for adding/viewing tags
function hs_book_tags_admin_menu()
{
	/* Submenu Page
		- Put it in the "tools" section of the admin panel
		- Name it "Book Tags Manager"
		- Make the title of the page "Book Tags Manager"
		- Require that a user can "manage_options" in order to access the page
		- Use the "hs_book_tags_admin_page" to render the page
		- I am tired and I forget what the last part means
	*/

	add_submenu_page(
		'tools.php',
		'Book Tags Manager',
		'Book Tags Manager',
		'manage_options',
		'hs-book-tags',
		'hs_book_tags_admin_page'
	);
}
// Add the action required to create the page
add_action('admin_menu', 'hs_book_tags_admin_menu');


// Render the admin page for adding/modifying tags
function hs_book_tags_admin_page()
{
	// Stop LOSERS from getting in
	if (!current_user_can('manage_options'))
	{
		// Crisis averted
		wp_die('You are not an admin! Buzz off!');
	}


	// Enqueues jQuery
	wp_enqueue_script('jquery');
	// Enqueues the required JavaScript for the admin page
	wp_enqueue_script('hs-book-tags-admin', plugin_dir_url(__FILE__) . '../../js/admin/tags_manager.js', ['jquery'], '1.0.0', true);
	// Enqueue the required CSS for the admin page
	wp_enqueue_style('hs-book-tags-admin', plugin_dir_url(__FILE__) . '../../css/admin/tags_manager.css', [], '1.0.0');

	// Localize the script
	wp_localize_script(
		'hs-book-tags-admin',
		'hsBTA',

		[

			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('hs_book_tags_nonce'),
		]);

	?>

	// The HTML
	// TODO: Move this out of the script, if possible
	<div class="wrap">
		<h1>Book Tags Manager</h1>
		<p>Manage tags for books. Separate multiple tags with commas.</p>

		<div class="hs-book-tags-container">
			<!-- Search and Add Section -->
			<div class="hs-bta-form-section">
				<h2>Add/Modify Tags for a Book</h2>
				<form id="hs-book-tags-form" class="hs-bta-form">
					<div class="hs-bta-form-group hs-search-group">
						<label for="hs-book-search">Select a Book:</label>
						<div class="hs-search-input-wrapper">
							<input
								type="text"
								id="hs-book-search"
								class="hs-book-search-input"
								placeholder="Type book title or author..."
								required
							>
							<input type="hidden" id="hs-book-id" name="book_id" value="">
							<div id="hs-search-results" class="hs-search-results"></div>
						</div>
					</div>

					<div class="hs-bta-form-group">
						<label for="hs-book-tags">Tags (comma-separated):</label>
						<textarea
							id="hs-book-tags"
							name="tags"
							class="hs-book-tags-input"
							placeholder="e.g., fantasy, adventure, sci-fi"
							rows="4"
						></textarea>
						<small>Separate tags with commas. Tags will be automatically slugified.</small>
					</div>

					<button type="submit" class="button button-primary">Save Tags</button>
					<span id="hs-form-message" class="hs-form-message"></span>
				</form>
			</div>

			<!-- Current Tags Display -->
			<div class="hs-bta-display-section">
				<h2>Current Tags for Selected Book</h2>
				<div id="hs-current-tags" class="hs-current-tags">
					<p class="hs-no-selection">Select a book to view its tags.</p>
				</div>
			</div>
		</div>

		<!-- Statistics Section -->
		<div class="hs-bta-stats-section">
			<h2>Tag Statistics</h2>
			<div id="hs-tag-stats" class="hs-tag-stats">
				<p>Loading statistics...</p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * AJAX handler to search for books
 */
function hs_ajax_search_books() {
	check_ajax_referer('hs_book_tags_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$search_query = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';

	if (strlen($search_query) < 2) {
		wp_send_json_success([]);
	}

	$args = [
		'post_type' => 'book',
		'post_status' => 'publish',
		'posts_per_page' => 10,
		's' => $search_query,
		'suppress_filters' => false
	];

	$book_query = new WP_Query($args);
	$results = [];

	if ($book_query->have_posts()) {
		while ($book_query->have_posts()) {
			$book_query->the_post();
			$book_id = get_the_ID();
			$author = get_post_meta($book_id, 'book_author', true);

			$results[] = [
				'id' => $book_id,
				'title' => get_the_title($book_id),
				'author' => !empty($author) ? $author : 'Unknown Author',
			];
		}
		wp_reset_postdata();
	}

	wp_send_json_success($results);
}
add_action('wp_ajax_hs_search_books', 'hs_ajax_search_books');

/**
 * AJAX handler to get existing tags for a book
 */
function hs_ajax_get_book_tags() {
	check_ajax_referer('hs_book_tags_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;

	if (!$book_id) {
		wp_send_json_error(['message' => 'Invalid book ID']);
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hs_book_tags';

	$tags = $wpdb->get_results($wpdb->prepare(
		"SELECT id, tag_name, usage_count FROM {$table_name} WHERE book_id = %d ORDER BY tag_name ASC",
		$book_id
	));

	if (empty($tags)) {
		wp_send_json_success(['message' => 'No tags found for this book', 'tags' => []]);
	}

	wp_send_json_success([
		'tags' => $tags,
		'count' => count($tags)
	]);
}
add_action('wp_ajax_hs_get_book_tags', 'hs_ajax_get_book_tags');

/**
 * AJAX handler to save/update tags for a book
 */
function hs_ajax_save_book_tags() {
	check_ajax_referer('hs_book_tags_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
	$tags_input = isset($_POST['tags']) ? sanitize_textarea_field($_POST['tags']) : '';

	if (!$book_id) {
		wp_send_json_error(['message' => 'Invalid book ID']);
	}

	// Verify the book exists
	$book = get_post($book_id);
	if (!$book || $book->post_type !== 'book') {
		wp_send_json_error(['message' => 'Invalid book']);
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hs_book_tags';

	// Parse tags from input
	$tags_array = array_map('trim', explode(',', $tags_input));
	$tags_array = array_filter($tags_array); // Remove empty values
	$tags_array = array_unique(array_map('strtolower', $tags_array)); // Remove duplicates and lowercase

	// Delete existing tags for this book
	$wpdb->delete($table_name, ['book_id' => $book_id], ['%d']);

	$inserted_count = 0;
	$errors = [];

	// Insert new tags
	foreach ($tags_array as $tag_name) {
		if (strlen($tag_name) > 100) {
			$errors[] = "Tag '{$tag_name}' is too long (max 100 characters)";
			continue;
		}

		$tag_slug = sanitize_title($tag_name);

		$result = $wpdb->insert(
			$table_name,
			[
				'book_id' => $book_id,
				'tag_name' => $tag_name,
				'tag_slug' => $tag_slug,
				'usage_count' => 1
			],
			['%d', '%s', '%s', '%d']
		);

		if ($result) {
			$inserted_count++;
		} else {
			$errors[] = "Failed to insert tag: {$tag_name}";
		}
	}

	if ($inserted_count > 0 || empty($tags_array)) {
		$message = $inserted_count === 0
			? 'Tags cleared successfully'
			: "Successfully saved {$inserted_count} tag(s)";

		wp_send_json_success([
			'message' => $message,
			'tags_count' => $inserted_count,
			'errors' => $errors
		]);
	} else {
		wp_send_json_error([
			'message' => 'Failed to save tags',
			'errors' => $errors
		]);
	}
}
add_action('wp_ajax_hs_save_book_tags', 'hs_ajax_save_book_tags');

/**
 * AJAX handler to delete a specific tag
 */
function hs_ajax_delete_book_tag() {
	check_ajax_referer('hs_book_tags_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;

	if (!$tag_id) {
		wp_send_json_error(['message' => 'Invalid tag ID']);
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hs_book_tags';

	$result = $wpdb->delete($table_name, ['id' => $tag_id], ['%d']);

	if ($result) {
		wp_send_json_success(['message' => 'Tag deleted successfully']);
	} else {
		wp_send_json_error(['message' => 'Failed to delete tag']);
	}
}
add_action('wp_ajax_hs_delete_book_tag', 'hs_ajax_delete_book_tag');

/**
 * AJAX handler to get tag statistics
 */
function hs_ajax_get_tag_statistics() {
	check_ajax_referer('hs_book_tags_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hs_book_tags';

	$total_tags = $wpdb->get_var("SELECT COUNT(DISTINCT tag_slug) FROM {$table_name}");
	$total_tagged_books = $wpdb->get_var("SELECT COUNT(DISTINCT book_id) FROM {$table_name}");
	$most_used = $wpdb->get_results("SELECT tag_name, tag_slug, COUNT(*) as usage_count FROM {$table_name} GROUP BY tag_slug ORDER BY usage_count DESC LIMIT 10");

	wp_send_json_success([
		'total_tags' => intval($total_tags),
		'total_tagged_books' => intval($total_tagged_books),
		'most_used_tags' => $most_used
	]);
}
add_action('wp_ajax_hs_get_tag_statistics', 'hs_ajax_get_tag_statistics');
