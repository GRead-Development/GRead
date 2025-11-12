<?php
/**
 * Book Tag Assignment Tool
 * 
 * Add this to your hotsoup.php file temporarily, then visit:
 * /wp-admin/tools.php?page=hs-assign-tags
 * 
 * After running once, you can remove this code.
 */

// Add admin menu item for tag assignment
function hs_add_tag_assignment_page()
{
	add_submenu_page(
		'tools.php',
		'Assign Book Tags',
		'Assign Book Tags',
		'manage_options',
		'hs-assign-tags',
		'hs_tag_assignment_page_html'
	);
}
add_action('admin_menu', 'hs_add_tag_assignment_page');


// Render the tag assignment page
function hs_tag_assignment_page_html()
{
	// Check if the assignment was just run
	if (isset($_GET['tags_assigned'])) {
		$count = intval($_GET['tags_assigned']);
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>Success!</strong> Tags have been assigned to ' . $count . ' books.</p>';
		echo '</div>';
	}
	
	global $wpdb;
	
	// Get count of books
	$book_count = wp_count_posts('book');
	$total_books = $book_count->publish ?? 0;
	
	// Check if search index exists
	$search_table_exists = false;
	if (defined('HS_SEARCH_TABLE')) {
		$table_name = HS_SEARCH_TABLE;
		$search_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
	}
	
	?>
	<div class="wrap">
		<h1>Assign Book Tags</h1>
		<p>This tool will assign tags to all books in your database so they can be mentioned in activity posts.</p>
		
		<div class="card">
			<h2>Current Status</h2>
			<ul>
				<li><strong>Total Books:</strong> <?php echo number_format($total_books); ?></li>
				<li><strong>Search Index:</strong> <?php echo $search_table_exists ? '✓ Exists' : '✗ Not Built'; ?></li>
			</ul>
		</div>
		
		<?php if (!$search_table_exists) : ?>
			<div class="notice notice-warning">
				<p><strong>Warning:</strong> The search index hasn't been built yet. You should build it first for better tagging performance.</p>
				<p><a href="<?php echo admin_url('tools.php?page=hs-search-tools'); ?>" class="button">Go to Search Tools</a></p>
			</div>
		<?php endif; ?>
		
		<div class="card">
			<h2>What This Does</h2>
			<p>When you run this tool, it will:</p>
			<ol>
				<li>Loop through all published books</li>
				<li>Ensure each book is in the search index (for fast tagging)</li>
				<li>Verify the tag format (#book123) works for each book</li>
			</ol>
			<p><strong>This is safe to run multiple times.</strong> It will only update/add data, not delete anything.</p>
		</div>
		
		<form method="post" action="">
			<?php wp_nonce_field('hs_assign_tags_action', 'hs_assign_tags_nonce'); ?>
			<p>
				<button type="submit" name="hs_assign_tags" class="button button-primary button-hero">
					Assign Tags to All Books
				</button>
			</p>
		</form>
	</div>
	
	<style>
		.card {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			margin: 20px 0;
			box-shadow: 0 1px 1px rgba(0,0,0,.04);
		}
		.card h2 {
			margin-top: 0;
			border-bottom: 1px solid #eee;
			padding-bottom: 10px;
		}
	</style>
	<?php
}


// Handle the tag assignment process
function hs_process_tag_assignment()
{
	// Check if form was submitted
	if (!isset($_POST['hs_assign_tags']) || 
	    !isset($_POST['hs_assign_tags_nonce']) || 
	    !wp_verify_nonce($_POST['hs_assign_tags_nonce'], 'hs_assign_tags_action') ||
	    !current_user_can('manage_options')) {
		return;
	}
	
	global $wpdb;
	$books_processed = 0;
	
	// Make sure the search table exists
	$search_table = HS_SEARCH_TABLE ?? ($wpdb->prefix . 'hs_book_search_index');
	
	// Create search table if it doesn't exist
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $search_table (
		book_id BIGINT(20) UNSIGNED NOT NULL,
		title TEXT NOT NULL,
		author VARCHAR(255),
		isbn VARCHAR(255),
		permalink VARCHAR(2048),
		tag_slug VARCHAR(50),
		PRIMARY KEY (book_id),
		INDEX author_index (author),
		INDEX isbn_index (isbn),
		INDEX tag_slug_index (tag_slug)
	) $charset_collate;";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	// Get all published books
	$args = array(
		'post_type' => 'book',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids'
	);
	
	$book_ids = get_posts($args);
	
	// Process each book
	foreach ($book_ids as $book_id) {
		$book = get_post($book_id);
		if (!$book) continue;
		
		$author = get_post_meta($book_id, 'book_author', true);
		$isbn = get_post_meta($book_id, 'book_isbn', true);
		$permalink = get_permalink($book_id);
		$tag_slug = 'book' . $book_id;
		
		// Insert or update in search index
		$wpdb->replace(
			$search_table,
			array(
				'book_id' => $book_id,
				'title' => $book->post_title,
				'author' => $author,
				'isbn' => $isbn,
				'permalink' => $permalink,
				'tag_slug' => $tag_slug
			),
			array('%d', '%s', '%s', '%s', '%s', '%s')
		);
		
		$books_processed++;
	}
	
	// Mark search as indexed
	delete_option('hs_search_needs_indexing');
	
	// Redirect with success message
	wp_safe_redirect(admin_url('tools.php?page=hs-assign-tags&tags_assigned=' . $books_processed));
	exit;
}
add_action('admin_init', 'hs_process_tag_assignment');


/**
 * Quick Test Function - Add to your functions.php temporarily
 * Visit: /wp-admin/admin-ajax.php?action=hs_test_tag_search&q=test
 */
function hs_test_tag_search() {
	if (!current_user_can('manage_options')) {
		wp_die('Not allowed');
	}
	
	$search_query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
	
	if (empty($search_query)) {
		echo '<h2>Book Tag Search Test</h2>';
		echo '<p>Add ?q=yourquery to the URL to test</p>';
		echo '<p>Example: ?action=hs_test_tag_search&q=gatsby</p>';
		return;
	}
	
	global $wpdb;
	$table_name = HS_SEARCH_TABLE ?? ($wpdb->prefix . 'hs_book_search_index');
	$like_query = '%' . $wpdb->esc_like($search_query) . '%';
	
	$results = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$table_name} 
		WHERE title LIKE %s OR author LIKE %s
		ORDER BY title ASC
		LIMIT 10",
		$like_query,
		$like_query
	));
	
	echo '<h2>Search Results for: ' . esc_html($search_query) . '</h2>';
	echo '<p>Found ' . count($results) . ' books</p>';
	
	if (!empty($results)) {
		echo '<table border="1" cellpadding="10">';
		echo '<tr><th>Book ID</th><th>Title</th><th>Author</th><th>Tag Slug</th></tr>';
		foreach ($results as $book) {
			echo '<tr>';
			echo '<td>' . esc_html($book->book_id) . '</td>';
			echo '<td>' . esc_html($book->title) . '</td>';
			echo '<td>' . esc_html($book->author) . '</td>';
			echo '<td>#' . esc_html($book->tag_slug) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	exit;
}
add_action('wp_ajax_hs_test_tag_search', 'hs_test_tag_search');
