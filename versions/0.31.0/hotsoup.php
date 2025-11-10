<?php
/**
 * Plugin Name: HotSoup!
 * Description: A delicious plugin for tracking your reading!
 * Version: 0.31
 * Author: Bryce Davis, Daniel Teberian
 */

// This stops users from directly accessing this file.
if (!defined('ABSPATH'))
{
	// Kick them out.
    exit;
}

// The importer form
require_once plugin_dir_path(__FILE__) . 'includes/importer.php';
// Stuff required for the book database to function. The most important functions in HotSoup are in this file.
require_once plugin_dir_path(__FILE__) . 'includes/bookdb.php';
// The stuff for tracking users' statistics and updating the leaderboards
require_once plugin_dir_path(__FILE__) . 'includes/leaderboards.php';
// Stuff for users to submit new books to the database
require_once plugin_dir_path(__FILE__) . 'includes/user_submission.php';
// Credits users for different contributions.
require_once plugin_dir_path(__FILE__) . 'includes/user_credit.php';
// The website's search feature, the autocompletion stuff, and other related functions.
require_once plugin_dir_path(__FILE__) . 'includes/search.php';
// Provides miscellaneous features that are designed to make GRead more secure.
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
// Track users' statistics.
require_once plugin_dir_path(__FILE__) . 'includes/user_stats.php';
// Administrator utilities for anything to do with points, crediting users, etc.
require_once plugin_dir_path(__FILE__) . 'includes/pointy_utils.php';
// Awards points to users for various contributions
require_once plugin_dir_path(__FILE__) . 'includes/pointy.php';
// Manages the different unlockable items on GRead.
require_once plugin_dir_path(__FILE__) . 'includes/unlockables_manager.php';
// Administrative utilities for managing inaccuracy reports.
require_once plugin_dir_path(__FILE__) . 'includes/inaccuracy_manager.php';
// Book tagging
require_once plugin_dir_path(__FILE__) . 'includes/tagging.php';
// Administrative utilities for managing themes for GRead
require_once plugin_dir_path(__FILE__) . 'includes/admin/theme_manager.php';
// Administrative utilities for adding GRead's identifiers to the books in the book database
require_once plugin_dir_path(__FILE__) . 'includes/admin/index_dbs.php';
require_once plugin_dir_path(__FILE__) . 'includes/reading_sessions.php';

require_once plugin_dir_path(__FILE__) . 'includes/admin/support_manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/api/importer.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/achievements_manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/moderation.php';
require_once plugin_dir_path(__FILE__) . 'includes/profiles/hide_invitations.php';

require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/my_books.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/book_directory.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/total_books.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/tags_manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/widgets/site_activity.php';

register_activation_hook( __FILE__, 'hs_achievements_create_table' );
register_activation_hook(__FILE__, 'hs_reading_sessions_create_table');
register_activation_hook(__FILE__, 'hs_themes_create_table');
register_activation_hook(__FILE__, 'hs_moderation_create_tables');
register_activation_hook(__FILE__, 'support_tickets_create_table');
register_activation_hook(__FILE__, 'hs_book_tags_activate');
register_activation_hook(__FILE__, 'hs_book_tags_create_table');


// On activation, set up the reviews table
function hs_reviews_activate()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_book_reviews';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		rating DECIMAL(3,1) NULL,
		review_text TEXT NULL,
		date_submitted DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY user_book_review (user_id, book_id),
		KEY book_id_index (book_id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
register_activation_hook(__FILE__, 'hs_reviews_activate');

// Add indexes for some important databases. Should improve the website's performance
function hs_index_dbs()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'user_books';

	$wpdb -> query("ALTER TABLE {$table_name} ADD INDEX idx_user_id (user_id)");
	$wpdb -> query("ALTER TABLE {$table_name} ADD INDEX idx_book_id (book_id)");
	$wpdb -> query("ALTER TABLE {$table_name} ADD INDEX idx_user_book (user_id, book_id)");
}
register_activation_hook(__FILE__, 'hs_index_dbs');


// On activation, set up the reward-tracking tables
function hs_rewards_activate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/config_rewards.php';
	hs_configure_rewards();
}
register_activation_hook(__FILE__, 'hs_rewards_activate');

function hs_search_activate()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = HS_SEARCH_TABLE;

    $sql = "CREATE TABLE $table_name (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        title TEXT NOT NULL,
        author VARCHAR(255),
        isbn VARCHAR(255),
        permalink VARCHAR(2048),
        PRIMARY KEY (book_id),
        INDEX author_index (author),
        INDEX isbn_index (isbn)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Use update_option to ensure the value is set, even if it already exists.
    update_option('hs_search_needs_indexing', 'true');
}
register_activation_hook(__FILE__, 'hs_search_activate');

function ol_enqueue_modal_assets()
{
	$plugin_url = plugin_dir_url(__FILE__);

	wp_enqueue_style(
		'ol-importer-modal-style',
		$plugin_url . 'css/user_submission.css'
	);

	wp_enqueue_script(
		'ol-importer-modal-script',
		$plugin_url . 'js/user_submission.js',
		['jquery'],
		'1.0.1',
		true
	);

	if (function_exists('bp_is_my_profile') && bp_is_my_profile() && bp_is_settings_component())
	{
		wp_enqueue_script(
			'hs-themes-script',
			$plugin_url . 'js/unlockables/themes.js',
			['jquery'],
			'1.0.0',
			true
		);

		wp_localize_script(
			'hs-themes-script',
			'hs_themes_ajax',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
			]
		);
	}
}
add_action('wp_enqueue_scripts', 'ol_enqueue_modal_assets');

// register creation of inaccuracy report table
// MUST be in this file
register_activation_hook( __FILE__, 'hs_inaccuracies_create_table' );

// When activated, create the table to use
function hs_activate()
{
	// The Wordpress database
    global $wpdb;
	// Set the table name to be the prefix and 'user_books'
    $table_name = $wpdb -> prefix . 'user_books';
	// Collate the character set
    $charset_collate = $wpdb -> get_charset_collate();

    // Actually create the table
    $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            book_id BIGINT(20) UNSIGNED NOT NULL,
            current_page MEDIUMINT(9) DEFAULT 0 NOT NULL,
            status VARCHAR(20) DEFAULT 'reading' NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_book_unique (user_id, book_id))
            $charset_collate;";

			// Import wp-admin/includes/upgrade.php
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
}
// Register the activation hook. HotSoup = Voltron
register_activation_hook(__FILE__, 'hs_activate');


// Make sure books are indexed as soon as they are added.
add_action('save_post_book', function($post_id)
{
	if(wp_is_post_revision($post_id))
	{
		return;
	}

	global $wpdb;
	$isbn = get_field('book_isbn', $post_id);
	$pages = get_field('book_nop', $post_id);
	$title = get_post_field('post_title', $post_id);
	$author = get_field('book_author', $post_id);

	$wpdb -> insert($wpdb -> prefix . 'hs_search_table', array(
		'book_id' => $post_id,
		'title' => $title,
		'author' => $author,
		'isbn' => $isbn,
		'pages' => $pages,
		'permalink' => get_permalink($post_id),
	), array('%d', '%s', '%s', '%s', '%d', '%s'));
}, 10, 1);

function hs_support_tickets_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'support_tickets';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) NOT NULL,
		user_email varchar(100) NOT NULL,
		subject varchar(255) NOT NULL,
		message text NOT NULL,
		admin_response text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}




// Save the data from the meta box
function hs_save_details($postid)
{
    // Erorr handling stuff
    if (!isset($_POST['hs_nonce']) || !wp_verify_nonce($_POST['hs_nonce'], 'hs_save_details'))
    {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
    {
        return;
    }

    // Don't show the user something they have no control over.
    if (!current_user_can('edit_post', $postid))
    {
        return;
    }

    if (isset($_POST['hs_pagecount']))
    {
        update_post_meta($postid, 'nop', sanitize_text_field($_POST['hs_pagecount']));
    }

    // Save author data
    if (isset($_POST['hs_author']))
    {
        update_post_meta($postid, 'book_author', sanitize_text_field($_POST['hs_author']));
    }

    // Save ISBN data
    if (isset($_POST['hs_isbn']))
    {
        update_post_meta($postid, 'book_isbn', sanitize_text_field($_POST['hs_isbn']));
    }
}
// Make the action available
add_action('save_post', 'hs_save_details');



 // Enqueue scripts/styles

function hs_enqueue()

{

    // Only load pages that use the shortcodes

    global $post;


    if (is_a($post, 'WP_Post') && (has_shortcode($post -> post_content, 'my_books') || has_shortcode($post -> post_content, 'book_directory') || has_shortcode($post -> post_content, 'hs_book_search')) || is_singular('book') || (function_exists('bp_is_user') && bp_is_user()))

    {

        wp_enqueue_style('hs_style', plugin_dir_url(__FILE__) . 'hs-style.css');

        wp_enqueue_script('hs-main-js', plugin_dir_url(__FILE__) . 'hs-main.js', ['jquery'], '1.1', true);


        // Pass the data to JS

        wp_localize_script('hs-main-js', 'hs_ajax', [

            'ajax_url' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('hs_ajax_nonce'),

        ]);

    }
}


// Add the action

add_action('wp_enqueue_scripts', 'hs_enqueue'); 


function hs_enqueue_universal_theme_override()
{
	wp_enqueue_style(
		'hs-universal-theme-overrides',
		plugin_dir_url(__FILE__) . 'css/hs-universal-overrides.css',
		[],
		'1.0'
	);
}
//add_action('wp_enqueue_scripts', 'hs_enqueue_universal_theme_overrides', 15);


// Update user's progress for a given book
function hs_update_progress()
{
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in() || !isset($_POST['book_id']) || !isset($_POST['current_page']))
    {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    global $wpdb;
    $table_name = $wpdb -> prefix . 'user_books';
    $user_id = get_current_user_id();
    $book_id = intval($_POST['book_id']);
    $current_page = intval($_POST['current_page']);

    $total_pages = (int)get_post_meta($book_id, 'nop', true);

    if ($current_page > $total_pages && $total_pages > 0)
    {
        $current_page = $total_pages;
    }
/*
	// Get the old page value before updating
	$old_entry = $wpdb -> get_row($wpdb -> prepare(
		"SELECT current_page FROM $table_name WHERE user_id = %d AND book_id = %d",
		$user_id,
		$book_id
	));
	$old_page = $old_entry ? (int)$old_entry -> current_page : 0;
*/

    $result = $wpdb -> update(
        $table_name,
        ['current_page' => $current_page],
        ['user_id' => $user_id, 'book_id' => $book_id],
        ['%d'],
        ['%d', '%d']
    );

	$completed = ($total_pages > 0 && $current_page >= $total_pages);

	// Logic to handle those jerks who mark a book as "completed" so they can write a review, only to remove the book from their completed list
	$review_deleted = false;

	if (!$completed)
	{
		// Book is going to be marked "incomplete", find their review and delete it.
		$reviews_table = $wpdb -> prefix . 'hs_book_reviews';
		$existing_review = $wpdb -> get_row($wpdb -> prepare(
			"SELECT id, rating, review_text FROM $reviews_table WHERE user_id = %d AND book_id = %d",
			$user_id,
			$book_id
		));

		if ($existing_review)
		{
			$points_to_deduct = 0;
			$has_rating = !is_null($existing_review -> rating) && $existing_review -> rating >= 1.0;
			$has_text = !empty(trim($existing_review -> review_text));

			// If the user has added a text review and rated the book
			if ($has_rating && $has_text)
			{
				$points_to_deduct = 25;
			}

			elseif ($has_rating)
			{
				$points_to_deduct = 5;
			}

			// This should be unneccesary, given how the text is optional.
			elseif ($has_text)
			{
				$points_to_deduct = 20;
			}

			if ($points_to_deduct > 0 && function_exists('hs_deduct_points'))
			{
				hs_deduct_points($user_id, $points_to_deduct);
			}


			// Get dat crap outta here
			$wpdb -> delete($reviews_table, ['id' => $existing_review -> id], ['%d']);
			$review_deleted = true;

			// Fix the book's average rating
			hs_update_book_average_rating($book_id);
		}
	}

	// Update user statistics incrementally
	//hs_update_stats_on_progress_change($user_id, $book_id, $old_page, $new_page);



    $progress = ($total_pages > 0) ? round(($current_page / $total_pages) * 100) : 0;
    $progress_text = "Progress: " . $progress . "% (" . $current_page . " / " . $total_pages . " pages)";

    if ($result !== false)
    {
        hs_update_user_stats($user_id);
        wp_send_json_success(['message' => 'Progress saved.', 'progress_html' => $progress_text, 'progress_percent' => $progress, 'completed' => $completed, 'review_deleted' => $review_deleted]);
    }
    else
    {
        wp_send_json_error(['message' => 'Oops! Could not save progress.']);
    }
}
add_action('wp_ajax_hs_update_progress', 'hs_update_progress');


// Add a book to library
function hs_add_book_to_library()
{
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in() || !isset($_POST['book_id']))
    {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    global $wpdb;
    $table_name = $wpdb -> prefix . 'user_books';
    $user_id = get_current_user_id();
    $book_id = intval($_POST['book_id']);

    $result = $wpdb -> insert(
        $table_name,
        ['user_id' => $user_id, 'book_id' => $book_id],
        ['%d', '%d']
    );

    if ($result)
    {
	hs_increment_books_added($user_id);
        hs_update_user_stats($user_id);
        wp_send_json_success(['message' => 'Added!']);
    }
    else
    {
        wp_send_json_error(['message' => 'Yikes!']);
    }
}
add_action('wp_ajax_hs_add_book', 'hs_add_book_to_library');

// Stop users from being sent to the WordPress dashboard.
function redirect_users($redirect_to, $request, $user)
{
	if (isset($user -> roles) && is_array($user -> roles))
	{
		if (in_array('subscriber', $user -> roles))
		{
			return home_url();
		}
	}

	return $redirect_to;
}
add_filter('login_redirect', 'redirect_users', 10, 3);



// Remove a book from a user's library
function hs_remove_book()
{
	check_ajax_referer('hs_ajax_nonce', 'nonce');

	if (!is_user_logged_in() || !isset($_POST['book_id']))
	{
		wp_send_json_error(['message' => 'Invalid request.']);
	}


	global $wpdb;
	$table_name = $wpdb -> prefix . 'user_books';
	$user_id = get_current_user_id();
	$book_id = intval($_POST['book_id']);

	$result = $wpdb -> delete(
		$table_name,
		['user_id' => $user_id, 'book_id' => $book_id],
		['%d', '%d']
	);

	if ($result !== false)
	{
		hs_decrement_books_added($user_id);
        	hs_update_user_stats($user_id);
		wp_send_json_success(['message' => 'Book begone!']);
	}
	else
	{
		wp_send_json_error(['message' => 'Oops!']);
	}
}
add_action('wp_ajax_hs_remove_book', 'hs_remove_book');


// AJAX handler for submitting a book review
function hs_submit_review()
{
	check_ajax_referer('hs_ajax_nonce', 'nonce');

	if (!is_user_logged_in() || !isset($_POST['book_id']))
	{
		wp_send_json_error(['message' => 'Invalid request.']);
		return;
	}

	global $wpdb;
	$user_id = get_current_user_id();
	$book_id = intval($_POST['book_id']);

	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';

	// Server-side validation. Sweet!
	$user_books_table = $wpdb -> prefix . 'user_books';
	$book_entry = $wpdb -> get_row($wpdb -> prepare(
		"SELECT current_page FROM $user_books_table WHERE user_id = %d AND book_id = %d",
		$user_id,
		$book_id
	));
	$total_pages = (int)get_post_meta($book_id, 'nop', true);

	// (maybe)
	// TODO: Make a global function for marking books as complete, so we don't keep making this calculation
	if (!$book_entry || $total_pages <= 0 || (int)$book_entry -> current_page < $total_pages)
	{
		wp_send_json_error(['message' => 'You cannot rate books that you have not read.']);
		return;
	}

	// Sanitize inputs
	$rating = isset($_POST['rating']) && !empty($_POST['rating']) ? floatval($_POST['rating']) : null;
	$review_text = isset($_POST['review_text']) ? sanitize_textarea_field($_POST['review_text']) : '';

	// Validate rating
	if (!is_null($rating) && ($rating < 1.0 || $rating > 10.0))
	{
		wp_send_json_error(['message' => 'Your rating must be greater than 0, and less than 10.']);
		return;
	}

	$has_rating = !is_null($rating);
	$has_text = !empty(trim($review_text));

	if (!$has_rating && !$has_text)
	{
		wp_send_json_error(['message' => 'Please provide a rating or a written review.']);
		return;
	}

	// Check for existing review in order to determine points
	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';
	$existing_review = $wpdb -> get_row($wpdb -> prepare(
		"SELECT rating, review_text FROM $reviews_table WHERE user_id = %d AND book_id = %d",
		$user_id, $book_id
	));

	$old_points = 0;

	if ($existing_review)
	{
		$old_has_rating = !is_null($existing_review -> rating) && $existing_review -> rating >= 0.0;
		$old_has_text = !empty(trim($existing_review -> review_text));

		if ($old_has_rating && $old_has_text)
		{
			$old_points = 25;
		}

		elseif ($old_has_rating)
		{
			$old_points = 5;
		}

		elseif ($old_has_text)
		{
			$old_points = 20;
		}
	}

	// Calculate points
	$new_points = 0;
	if ($has_rating && $has_text)
	{
		$new_points = 25;
	}

	elseif ($has_rating)
	{
		$new_points = 5;
	}

	elseif ($has_text)
	{
		$new_points = 20;
	}

	// REPLACE INTO to insert new, or update existing, review
	$result = $wpdb -> replace(
		$reviews_table,
		[
			'user_id' => $user_id,
			'book_id' => $book_id,
			'rating' => $has_rating ? $rating: null,
			'review_text' => $review_text,
			'date_submitted' => current_time('mysql')
		],

		['%d', '%d', '%f', '%s', '%s']
	);

	if ($result === false)
	{
		wp_send_json_error(['message' => 'Your review could not be submitted.']);
		return;
	}

	// Update points
	$points_diff = $new_points - $old_points;

	if ($points_diff > 0 && function_exists('award_points'))
	{
		award_points($user_id, $points_diff);
	}

	elseif ($points_diff < 0 && function_exists('hs_deduct_points'))
	{
		hs_deduct_points($user_id, abs($points_diff));
	}

	// Recalculate the book's average rating
	hs_update_book_average_rating($book_id);

	wp_send_json_success([
		'message' => 'Your review was submitted! Thank you for making GRead better!',
		'new_rating_html' => $has_rating ? 'You rated this: <strong>' . number_format($rating, 1) . '/10/</strong>' : 'Review saved.'
	]);
}
add_action('wp_ajax_hs_submit_review', 'hs_submit_review');


// Update a book's average rating
function hs_update_book_average_rating($book_id)
{
	if (!$book_id)
	{
		return;
	}

	global $wpdb;
	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';

	// Calculate the average rating, ignore null
	$average_rating = $wpdb -> get_var($wpdb -> prepare(
		"SELECT AVG(rating) FROM $reviews_table WHERE book_id = %d AND rating IS NOT NULL",
		$book_id
	));

	// Calculate the total number of reviews (rating not needed)
	$review_count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(id) FROM $reviews_table WHERE book_id = %d",
		$book_id
	));

	// Count the number of reviews that include ratings
	$rating_count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(id) FROM $reviews_table WHERE book_id = %d AND rating IS NOT NULL",
		$book_id
	));

	update_post_meta($book_id, 'hs_average_rating', round($average_rating, 2));
	update_post_meta($book_id, 'hs_review_count', (int)$review_count);
	update_post_meta($book_id, 'hs_rating_count', (int)$rating_count);
}

// Displays book info on each book's page, respectively
function hs_book_details_page($content)
{
	if (is_singular('book') && in_the_loop() && is_main_query())
	{
		global $wpdb;
		global $post;

		$book_id = $post -> ID;


		// Book's meta data
		$author = get_post_meta($book_id, 'book_author', true);
		$isbn = get_post_meta($book_id, 'book_isbn', true);
		$pub_year = get_post_meta($book_id, 'publication_year', true);
		$total_pages = get_post_meta($book_id, 'nop', true);


		// Retrieve user statistics
		$table_name = $wpdb -> prefix . 'user_books';

		// Find out how many users have a given book in their respective library
		$total_readers = $wpdb -> get_var($wpdb -> prepare(
			"SELECT COUNT(user_id) FROM {$table_name} WHERE book_id = %d",
			$book_id
		));


		$completed_count = 0;

		if (!empty($total_pages) && $total_pages > 0)
		{
			$completed_count = $wpdb -> get_var($wpdb -> prepare(
				"SELECT COUNT(user_id) FROM {$table_name} WHERE book_id = %d AND current_page >= %d",
				$book_id,
				$total_pages
			));
		}


		// Get average rating
		$avg_rating = get_post_meta($book_id, 'hs_average_rating', true);
		$rating_count = (int)get_post_meta($book_id, 'hs_rating_count', true);

		$details_html = '<div class="hs-single-book-details">';

		$details_html .= '<h2>Book Information</h2>';
		$details_html .= '<ul>';

		if (!empty($author))
		{
			$details_html .= '<li><strong>Author:</strong> ' . esc_html($author) . '</li>';
		}

		if (!empty($isbn))
		{
			$details_html .= '<li><strong>ISBN:</strong> ' . esc_html($isbn) . '</li>';
		}

		if (!empty($pub_year))
		{
			$details_html .= '<li><strong>Pages:</strong> ' . esc_html($total_pages) . '</li>';
		}

		$details_html .= '</ul>';


		// Community statistics
		$details_html .= '<h2>Community Statistics</h2>';
		$details_html .= '<ul>';
		$details_html .= '<li><strong>This book is in </strong>' . intval($total_readers) . ' <strong>libraries.</strong></li>';
		$details_html .= '<li><strong>This book has been completed by </strong>' . intval($completed_count) . ' user(s).</li>';

		// Average rating
		if ($rating_count > 0 && !empty($avg_rating))
		{
			$rating_label = _n('rating', 'ratings', $rating_count, 'hotsoup');
			$details_html .= '<li><strong>Average Rating:</strong> ' . esc_html(number_format($avg_rating, 2)) . ' / 10.0 (from ' . $rating_count . ' ' . $rating_label . ')</li>';
		}

		else
		{
			$details_html .= '<li><strong>Average Rating:</strong> Not yet rated. :(</li>';
		}

		$details_html .= '<li><button id="hs-open-report-modal" class="hs-button">Report Inaccuracy</button></li>';
		$details_html .= '</ul>';

		$details_html .= '</div>';

		// Show written reviews
		$reviews_table = $wpdb -> prefix . 'hs_book_reviews';

		$written_reviews = $wpdb -> get_results($wpdb -> prepare(
			"SELECT user_id, rating, review_text, date_submitted
			FROM $reviews_table
			WHERE book_id = %d AND review_text IS NOT NULL AND review_text != ''
			ORDER BY date_submitted DESC",
			$book_id
		));


		if (!empty($written_reviews))
		{
			$details_html .= '<div class="hs-book-reviews-list">';
			$details_html .= '<h2>User Reviews</h2>';
			$details_html .= '<ul>';

			// TODO: Make sure that blocked users do not impact the average rating or force a recalculation
			$current_user_id = get_current_user_id();
			foreach ($written_reviews as $review)
			{
				$user_info = get_userdata($review -> user_id);
				$display_name = $user_info ? $user_info -> display_name : 'Anonymous';

				$profile_url = function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($review -> user_id) : '#';

				$details_html .= '<li>';
				$details_html .= '<div class="hs-review-header">';
				$details_html .= '<strong class="hs-review-author"><a href="' . esc_url($profile_url) . '">' . esc_html($display_name) . '</a></strong>';

				if (!is_null($review -> rating))
				{
					$details_html .= '<span class="hs-review-rating">rated it <strong>' . esc_html($review -> rating) . '/10</strong></span>';
				}

				$details_html .= '</div>';
				$details_html .= '<blockquote class="hs-review-text">' . wp_kses_post(wpautop($review -> review_text)) . '</blockquote>';
				$details_html .= '</li>';
			}

			$details_html .= '</ul>';
			$details_html .= '</div>';
		}

		$content .= $details_html;
	}

	return $content;
}
add_filter('the_content', 'hs_book_details_page');

// Styling for book details page
function hs_book_details_page_styles()
{
	if (is_singular('book'))
	{
		echo '<style>
		.post-navigation,
		.nav-links
		{
			display: none !important;
		}

		.hs-single-book-details
		{
			margin-top: 30px;
			padding: 20px;
			background-color: #f9f9f9;
			border: 1px solid #e0e0e0;
			border-radius: 5px;
		}

		.hs-single-book-details h2
		{
			margin-top: 0;
			border-bottom: 2px solid #e0e0e0;
			padding-bottom: 10px;
			margin-bottom: 15px;
		}

		.hs-single-book-details ul
		{
			list-style-type: none;
			padding-left: 0;
			margin-left: 0;
		}

		.hs-single-book-details li
		{
			padding: 6px 0;
			border-bottom: 1px solid #eee;
		}

		.hs-single-book-details li:last-child
		{
			border-bottom: none;
		}
	</style>';
	}
}
add_action('wp_head', 'hs_book_details_page_styles');


function hs_book_tags_activate()
{
	global $wpdb;
	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix} hs_book_tags (
		id INT PRIMARY KEY AUTO_INCREMENT,
		book_id INT,
		tag_name VARCHAR(100),
		tag_slug VARCHAR(100),
		usage_count INT DEFAULT 1,
		UNIQUE KEY book_tag (book_id, tag_slug),
		INDEX (tag_slug)
	)" );
}
