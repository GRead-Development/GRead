<?php
/**
 * Plugin Name: HotSoup!
 * Description: A delicious plugin for tracking your reading!
 * Version: 0.16.1
 * Author: Bryce Davis, Daniel Teberian
 */

// This stops users from directly accessing this file.
if (!defined('ABSPATH'))
{
	// Kick them out.
    exit;
}


// Imports includes/importer.php
require_once plugin_dir_path(__FILE__) . 'includes/importer.php';
// Imports includes/bookdb.php
require_once plugin_dir_path(__FILE__) . 'includes/bookdb.php';
// Imports includes/leaderboards.php
require_once plugin_dir_path(__FILE__) . 'includes/leaderboards.php';
require_once plugin_dir_path(__FILE__) . 'includes/user_submission.php';
require_once plugin_dir_path(__FILE__) . 'includes/user_credit.php';
require_once plugin_dir_path(__FILE__) . 'includes/search.php';
require_once plugin_dir_path(__FILE__) . 'includes/site-stats.php';
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
require_once plugin_dir_path(__FILE__) . 'includes/user_stats.php';
require_once plugin_dir_path(__FILE__) . 'includes/pointy_utils.php';
require_once plugin_dir_path(__FILE__) . 'includes/pointy.php';
require_once plugin_dir_path(__FILE__) . 'includes/unlockables_manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/inaccuracy_manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/tagging.php';
require_once plugin_dir_path(__FILE__) . 'includes/unlockables/themes.php';


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


/*
	wp_localize_script(
		'ol-importer-modal-script',
		'hs_importer_ajax',
		[
			'ajax_url' => admin_url('admin_ajax.php'),
			'nonce' => wp_create_nonce('hs_importer_nonce'),
		]
	); */
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


    if (is_a($post, 'WP_Post') && (has_shortcode($post -> post_content, 'my_books') || has_shortcode($post -> post_content, 'book_directory') || has_shortcode($post -> post_content, 'hs_book_search')) || is_singular('book'))

    {

        wp_enqueue_style('hs_style', plugin_dir_url(__FILE__) . 'hs-style.css');

        wp_enqueue_script('hs-main-js', plugin_dir_url(__FILE__) . 'hs-main.js', ['jquery'], '1.1', true);


        // Pass the data to JS

        wp_localize_script('hs-main-js', 'hs_ajax', [

            'ajax_url' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('hs_ajax_nonce'),

        ]);

    }
	
	// Only load if it's an activity page or if a shortcode is present
 //   $is_activity_or_related = (function_exists('bp_is_activity') && bp_is_activity()) || 
 //                             (is_a($post, 'WP_Post') && (has_shortcode($post -> post_content, 'my_books') || has_shortcode($post -> post_content, 'book_directory') || has_shortcode($post -> post_content, //'hs_book_search')));

   // if ($is_activity_or_related)
   // {
        // Enqueue jQuery UI and its styling for the autocomplete widget
   //     wp_enqueue_script('jquery-ui-autocomplete');
        
        // Enqueue a standard jQuery UI theme for basic autocomplete styling
    //    wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');
        
        // Enqueue your custom JS/CSS for the autocomplete logic
     //   wp_enqueue_script('hs-bp-autocomplete', plugin_dir_url(__FILE__) . 'js/bp-autocomplete.js', ['jquery', 'jquery-ui-autocomplete'], '1.0', true);

        // Pass the data to JS
     //   wp_localize_script('hs-bp-autocomplete', 'hs_ajax_bp', [
       //     'ajax_url' => admin_url('admin-ajax.php'),
      //  ]);
   // }
}

// Add the action

add_action('wp_enqueue_scripts', 'hs_enqueue'); 

// The shortcodes

// [book_directory]: Display a list of available books
function hs_book_directory_shortcode()
{
    if (is_user_logged_in())
    {
        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb -> prefix . 'user_books';
        $user_book_ids = $wpdb -> get_col("SELECT book_id FROM $table_name WHERE user_id = $user_id");
	}
    $args =
    [
        'post_type' => 'book',
        'posts_per_page' => -1,
    ];

    $books = new WP_Query($args);
    $output = '<div class="hs-container"><ul class="hs-book-list">';

    if ($books -> have_posts())
    {
        while ($books -> have_posts())
        {
            $books -> the_post();
            $book_id = get_the_ID();
            $author = get_post_meta($book_id, 'book_author', true);
            $pagecount = get_post_meta($book_id, 'nop', true);
            $isbn = get_post_meta($book_id, 'book_isbn', true);

            $output .= '<li>';
            $output .= '<h3>' . get_the_title() . '</h3>';
            
            if (!empty($author))
            {
                $output .= '<p><strong>Author:</strong> ' . esc_html($author) . '</p>';
            }

            if (!empty($pagecount))
            {
                $output .= '<p><strong>Pages:</strong> ' . esc_html($pagecount) . '</p>';
            }

            if (!empty($isbn))
            {
                $output .= '<p><strong>ISBN:</strong> ' . esc_html($isbn) . '</p>';
            }

			if (is_user_logged_in())
			{
				if (in_array($book_id, $user_book_ids))
				{
					$output .= '<button class="hs-button" disabled>Added</button>';
				}
				else
				{
					$output .= '<button class="hs-button hs-add-book" data-book-id="' . $book_id . '">Add to Library</button>';

				}
			}

            $output .= '</li>';
            }
        }

        else
        {
            $output .= '<li>No books are available. Check back later!</li>';
        }

        $output .= '</ul></div>';
        wp_reset_postdata();
        return $output;
}
// Add the shortcode
add_shortcode('book_directory', 'hs_book_directory_shortcode');


/**
 * Shortcode: [my_books]
 * Displays the user's reading library with options for sorting and filtering.
 *
 * Sorting is done via shortcode attributes or GET parameters:
 * - sort_by: 'title' (default), 'author', or 'progress'
 * - sort_order: 'asc' (default) or 'desc'
 * - include_completed: 'yes' or 'no' (default)
 */
function hs_my_books_shortcode($atts)
{
    if (!is_user_logged_in()) {
        // Return message if the user is not logged in
        return '<p>Please log in to track your reading!</p>';
    }

    // 1. Parse Shortcode Attributes for Sorting and Filtering
    $atts = shortcode_atts(array(
        'sort_by' => 'title',
        'sort_order' => 'asc',
        'include_completed' => 'no',
    ), $atts, 'my_books');

    // Clean and validate attributes, preferring GET parameters if present for interactive sorting
    $sort_by = isset($_GET['sort_by']) ? sanitize_key($_GET['sort_by']) : $atts['sort_by'];
    $sort_order = isset($_GET['sort_order']) ? sanitize_key($_GET['sort_order']) : $atts['sort_order'];
    $include_completed = isset($_GET['include_completed']) ? ('yes' === sanitize_key($_GET['include_completed'])) : ('yes' === $atts['include_completed']);

    // Ensure valid values after checking GET/defaults
    $sort_by = in_array($sort_by, array('title', 'author', 'progress')) ? $sort_by : 'title';
    $sort_order = in_array(strtolower($sort_order), array('asc', 'desc')) ? strtolower($sort_order) : 'asc';


    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'user_books';

	// Retrieve user's reviews
	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';
	$user_reviews_raw = $wpdb -> get_results($wpdb -> prepare("SELECT book_id, rating, review_text FROM {$reviews_table} WHERE user_id = %d", $user_id));
	$user_reviews = [];

	foreach ($user_reviews_raw as $review)
	{
		$user_reviews[$review -> book_id] = $review;
	}

    // Fetch all books for the current user
    $my_book_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if (empty($my_book_entries)) {
        return '<p>You have not added any books to your library. Browse the book database and add what books you are reading to your library. If you cannot find the book you are reading, submit it to the database and get some rewards!</p>';
    }

    // 2. Collect All Book Data into a single array
    $all_books_data = [];
    foreach ($my_book_entries as $book_entry) {
        $book = get_post($book_entry->book_id);
        if ($book) {
            $total_pages = (int)get_post_meta($book_entry->book_id, 'nop', true);
            $current_page = (int)$book_entry->current_page;
            $progress = ($total_pages > 0) ? round(($current_page / $total_pages) * 100) : 0;
            $is_completed = ($total_pages > 0 && $current_page >= $total_pages);

            // Assuming the author is the post author for simplicity; adjust meta key if needed
            $author = get_post_meta($book_entry->book_id, 'book_author', true);

		// Check if the user has reviewed the book
		$has_review = isset($user_reviews[$book_entry -> book_id]);
		$user_rating = $has_review ? $user_reviews[$book_entry -> book_id] -> rating : null;
		$user_review_text = $has_review ? $user_reviews[$book_entry -> book_id] -> review_text : '';

            $all_books_data[] = [
                'entry' => $book_entry,
                'post' => $book,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'progress' => $progress,
                'is_completed' => $is_completed,
                'author' => $author,
		'has_review' => $has_review,
		'user_rating' => $user_rating,
		'user_review_text' => $user_review_text
            ];
        }
    }

    // 3. Implement Sorting Logic (custom comparison function)
    usort($all_books_data, function($a, $b) use ($sort_by, $sort_order) {
        $a_val = '';
        $b_val = '';

        switch ($sort_by) {
            case 'author':
                $a_val = $a['author'];
                $b_val = $b['author'];
                break;
            case 'progress':
                $a_val = $a['progress'];
                $b_val = $b['progress'];
                break;
            case 'title':
            default:
                $a_val = $a['post']->post_title;
                $b_val = $b['post']->post_title;
        }

        // String comparison for title/author
        if ($sort_by === 'author' || $sort_by === 'title') {
            $result = strcasecmp($a_val, $b_val);
            return ($sort_order === 'asc') ? $result : -$result;
        }
        // Numeric comparison for progress
        else {
            if ($a_val == $b_val) return 0;
            if ($sort_order === 'asc') {
                return ($a_val < $b_val) ? -1 : 1;
            } else {
                return ($a_val > $b_val) ? -1 : 1;
            }
        }
    });

    // Initialize HTML containers
    $reading_books_html = '';
    $completed_books_html = '';
    $completed_count = 0;
    $main_list_html = ''; // For combined list when filtering is off

    // 4. Generate HTML based on the sorted list and current filter settings
    foreach ($all_books_data as $data) {
        $book_entry = $data['entry'];
        $book = $data['post'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];
        $progress = $data['progress'];
        $is_completed = $data['is_completed'];
        $author = $data['author'];
	$has_review = $data['has_review'];
	$user_rating = $data['user_rating'];
	$user_review_text = $data['user_review_text'];

	error_log("Book ID {$book_entry -> book_id}: current_page={$current_page}, total_pages={$total_pages}, is_completed=" . ($is_completed ? 'YES' : 'NO'));

        $li_class = $is_completed ? 'hs-my-book completed' : 'hs-my-book';
        $bar_class = $is_completed ? 'hs-progress-bar golden' : 'hs-progress-bar';

	// Data-reviewed attribute
	$book_html = '<li class="' . esc_attr($li_class) . '" data-list-book-id="' . esc_attr($book_entry -> book_id) . '" date-reviewed="' . ($has_review ? 'true' : 'false') . '">';

        // HTML for a single book item
        $book_html = '<li class="' . esc_attr($li_class) . '" data-list-book-id="' . esc_attr($book_entry->book_id) . '">';
        $book_html .= '<h3><a style="color: #0056b3;" href="' . esc_url(get_permalink($book->ID)) . '">' . esc_html($book->post_title) . '</a></h3>';
        $book_html .= '<p class="hs-book-author">By: ' . esc_html($author) . '</p>'; // Display Author
        $book_html .= '<div class="hs-progress-bar-container"><div class="' . esc_attr($bar_class) . '" style="width: ' . esc_attr($progress) . '%;"></div></div>';
        $book_html .= '<p>Progress: ' . esc_html($progress) . '% (' . esc_html($current_page) . ' / ' . esc_html($total_pages) . ' pages)</p>';

        $book_html .= '<form class="hs-progress-form">';
        $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
        $book_html .= '<label>Update current page number:</label>';
        $book_html .= '<input type="number" name="current_page" min="0" max="' . esc_attr($total_pages) . '" value="' . esc_attr($current_page) . '">';
        $book_html .= '<button type="submit" class="hs-button">Save Progress</button>';
        $book_html .= '<span class="hs-feedback"></span>';
        $book_html .= '</form>';

        $book_html .= '<button class="hs-button hs-remove-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Remove</button>';


	if ($is_completed) {
		error_log("Book ID {book_entry -> book_id}: ADDING REVIEW SECTION");
            $book_html .= '<div class="hs-review-section">';
            
            $review_button_text = $has_review ? 'Edit Review' : 'Review Book';
            $rating_display = $has_review && !is_null($user_rating) ? 'You rated this: <strong>' . esc_html($user_rating) . '/10</strong>' : '';

            $book_html .= '<span class="hs-user-rating-display">' . $rating_display . '</span>';
            $book_html .= '<button class="hs-button hs-toggle-review-form" data-book-id="' . esc_attr($book_entry->book_id) . '">' . $review_button_text . '</button>';

            // Hidden Review Form
            $book_html .= '<form class="hs-review-form" style="display:none;">';
            $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
            $book_html .= '<h4>Your Review</h4>';
            
            $book_html .= '<div class="hs-review-field">';
            $book_html .= '<label for="hs_rating_' . esc_attr($book_entry->book_id) . '">Rating (1.0 - 10.0):</label>';
            $book_html .= '<input type="number" id="hs_rating_' . esc_attr($book_entry->book_id) . '" name="hs_rating" min="1.0" max="10.0" step="0.1" value="' . esc_attr($user_rating) . '">';
            $book_html .= '</div>';
            
            $book_html .= '<div class="hs-review-field">';
            $book_html .= '<label for="hs_review_text_' . esc_attr($book_entry->book_id) . '">Review (optional, +20 points):</label>';
            $book_html .= '<textarea id="hs_review_text_' . esc_attr($book_entry->book_id) . '" name="hs_review_text" rows="4">' . esc_textarea($user_review_text) . '</textarea>';
            $book_html .= '</div>';

            $book_html .= '<button type="submit" class="hs-button">Submit Review</button>';
            $book_html .= '<span class="hs-review-feedback"></span>';
            $book_html .= '</form>'; // end .hs-review-form
            
            $book_html .= '</div>'; // end .hs-review-section
        }

        $book_html .= '</li>';

        // Append to the correct section string based on the filter setting
        if ($include_completed) {
            $main_list_html .= $book_html;
        } else {
            if ($is_completed) {
                $completed_books_html .= $book_html;
            } else {
                $reading_books_html .= $book_html;
            }
        }

        if ($is_completed) {
            $completed_count++; // Still count for summary
        }
    }


    // 5. Assemble the final output, starting with the sort/filter form

    // Determine the current URL to ensure form submission works correctly.
    $form_action = esc_url(remove_query_arg(array('sort_by', 'sort_order', 'include_completed')));

    $output = '<div class="hs-container">';

    // Form to control sorting and filtering
    $output .= '<form class="hs-sort-filter-form" action="' . $form_action . '" method="get">';

    // Hidden fields for existing GET parameters to maintain context if shortcode is on a complex page
    $current_url_params = $_GET;
    unset($current_url_params['sort_by'], $current_url_params['sort_order'], $current_url_params['include_completed']);
    foreach ($current_url_params as $key => $value) {
        $output .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }

    // Sort By Selector
    $output .= '<div class="hs-sort-group">';
    $output .= '<label for="hs_sort_by">Sort By:</label>';
    $output .= '<select name="sort_by" id="hs_sort_by" onchange="this.form.submit()">';
    $output .= '<option value="title"' . selected($sort_by, 'title', false) . '>Title</option>';
    $output .= '<option value="author"' . selected($sort_by, 'author', false) . '>Author</option>';
    $output .= '<option value="progress"' . selected($sort_by, 'progress', false) . '>Progress</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Sort Order Selector
    $output .= '<div class="hs-sort-group">';
    $output .= '<label for="hs_sort_order">Order:</label>';
    $output .= '<select name="sort_order" id="hs_sort_order" onchange="this.form.submit()">';
    $output .= '<option value="asc"' . selected($sort_order, 'asc', false) . '>Ascending (A-Z, 0-100%)</option>';
    $output .= '<option value="desc"' . selected($sort_order, 'desc', false) . '>Descending (Z-A, 100%-0%)</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Include Completed Checkbox
    $output .= '<div class="hs-filter-group">';
    $output .= '<label for="hs_include_completed">';
    // Use an input type="checkbox" but ensure the value is only submitted if checked
    $output .= '<input type="checkbox" name="include_completed" id="hs_include_completed" value="yes" ' . checked($include_completed, true, false) . ' onchange="this.form.submit()">';
    $output .= 'Include Completed in Main List';
    $output .= '</label>';
    $output .= '</div>';

    $output .= '</form>';


    // Display the book lists
    if ($include_completed) {
        // Combined List
        $output .= '<h2>My Full Library (Sorted by ' . esc_html(ucfirst($sort_by)) . ')</h2>';
        if (!empty($main_list_html)) {
            $output .= '<ul class="hs-my-book-list hs-combined-list" id="hs-combined-books-list">' . $main_list_html . '</ul>';
        } else {
            $output .= '<p>Your library is empty.</p>';
        }
    } else {
        // Separated Lists (Currently Reading / Completed)

        // "Currently Reading" Section
        $output .= '<h2>Currently Reading</h2>';
        if (!empty($reading_books_html)) {
            $output .= '<ul class="hs-my-book-list" id="hs-reading-books-list">' . $reading_books_html . '</ul>';
        } else {
            $output .= '<p>You are not currently reading any books.</p>';
        }

        // "Completed Books" Section
        if ($completed_count > 0) {
            $output .= '<details class="hs-completed-section">';
            $output .= '<summary><h3>Completed Books (' . $completed_count . ')</h3></summary>';
            $output .= '<ul class="hs-my-book-list" id="hs-completed-books-list">' . $completed_books_html . '</ul>';
            $output .= '</details>';
        }
    }

    $output .= '</div>';
    return $output;
}
// Add the shortcode
add_shortcode('my_books', 'hs_my_books_shortcode');




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

    if ($current_page > $total_pages)
    {
        $current_page = $total_pages;
    }

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

			if ($point_to_deduct > 0 && function_exists('hs_deduct_points'))
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
        hs_update_user_stats($user_id);
        wp_send_json_success(['message' => 'Added!']);
    }
    else
    {
        wp_send_json_error(['message' => 'Yikes!']);
    }
}
add_action('wp_ajax_hs_add_book', 'hs_add_book_to_library');


// A custom widget that displays activity from HotSoup!
class HotSoup_Activity_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'hotsoup_activity_widget',
            'HotSoup! Activity Feed',
            ['description' => __('Shows the latest activity for books being added, progress being updated, users registering, etc.', 'hotsoup')]
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        $limit = !empty($instance['number']) ? intval($instance['number']) : 5;

        echo '<div class="hotsoup-widget-feed">';
        echo '<ul>';

        if (function_exists('bp_activity_get')) {
            $activities = bp_activity_get([
                'object' => 'hotsoup',
                'per_page' => $limit,
                'display_comments' => 'stream',
            ]);

            if (!empty($activities['activities'])) {
                foreach ($activities['activities'] as $activity) {
                    echo '<li>' . $activity->action . '<span class="activity-time-since">' . bp_core_time_since($activity->date_recorded) . '</span></li>';
                }
            } else {
                echo '<li>No recent activity :(</li>';
            }
        } else {
            echo '<li>You should not be seeing this message.</li>';
        }

        echo '</ul>';
        echo '</div>';

        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : __('Recent Activity', 'hotsoup');
        $number = !empty($instance['number']) ? $instance['number'] : 5;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number'); ?>">Number of items to show:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($number); ?>" size="3">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['number'] = (!empty($new_instance['number'])) ? intval($new_instance['number']) : 5;
        return $instance;
    }
}


// Register the widget with WordPress
function hs_register_activity_widget()
{
    register_widget('HotSoup_Activity_Widget');
}
add_action('widgets_init', 'hs_register_activity_widget');


// Basic styling
function hs_widget_styles()
{
    echo '<style>
        .hotsoup-widget-feed ul { list-style: none; margin-left: 0; padding-left: 0; }
        .hotsoup-widget-feed li { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
        .hotsoup-widget-feed li .activity-time-since { display: block; color: #888; font-size: 0.8em; margin-top: 4px; }
        </style>';
}
add_action('wp_head', 'hs_widget_styles');


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


	// Server-side validation. Sweet!
	$user_books_table = $wpdb -> prefix . 'user_books';
	$book_entry = $wpdb -> get_row($wpdb -> prepare(
		"SELECT current_page FROM $user_books_table WHERE user_id = %d AND book_id = %d",
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
	if (!is_null($rating) && ($rating < 0.0 || $rating > 10.0))
	{
		wp_send_json_error(['message' => 'Your rating must be greater than 0, and less than 10.']);
		return;
	}

	$has_rating = !is_null($rating);
	$has_text = !empty(trim($review_text));

	if (!$has_rating && !has_text)
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
	if ($has_rating && has_text)
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

			foreach ($written_reviews as $review)
			{
				$user_info = get_userdata($review -> user_id);
				$display_name = $user_info ? $user_info -> display_name : 'Anonymous';

				$details_html .= '<li>';
				$details_html .= '<div class="hs-review-header">';
				$details_html .= '<strong class="hs-review-author">' . esc_html($display_name) . '</strong>';

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

