<?php
/**
 * Plugin Name: HotSoup!
 * Description: A delicious plugin for tracking your reading!
 * Version: 0.14
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

            $all_books_data[] = [
                'entry' => $book_entry,
                'post' => $book,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'progress' => $progress,
                'is_completed' => $is_completed,
                'author' => $author,
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

        $li_class = $is_completed ? 'hs-my-book completed' : 'hs-my-book';
        $bar_class = $is_completed ? 'hs-progress-bar golden' : 'hs-progress-bar';

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

    $total_pages = get_post_meta($book_id, 'nop', true);

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

    $progress = ($total_pages > 0) ? round(($current_page / $total_pages) * 100) : 0;
    $progress_text = "Progress: " . $progress . "% (" . $current_page . " / " . $total_pages . " pages)";

    if ($result !== false)
    {
        hs_update_user_stats($user_id);
        wp_send_json_success(['message' => 'Progress saved.', 'progress_html' => $progress_text, 'progress_percent' => $progress, 'completed' => $completed]);
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
		$details_html .= '<li><button id="hs-open-report-modal" class="hs-button">Report Inaccuracy</button></li>';
		$details_html .= '</ul>';

		$details_html .= '</div>';

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

