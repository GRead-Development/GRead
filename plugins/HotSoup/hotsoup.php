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
//require_once plugin_dir_path(__FILE__) . 'includes/pointy_utils.php';
require_once plugin_dir_path(__FILE__) . 'includes/pointy.php';
require_once plugin_dir_path(__FILE__) . 'includes/unlockables_manager.php';
//require_once plugin_dir_path(__FILE__) . 'includes/inaccuracy_manager.php';



// On activation, set up the reward-tracking tables
function hs_rewards_activate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/config_rewards.php';
	hs_configure_rewards();
}
register_activation_hook(__FILE__, 'hs_rewards_activate');

/*
// Enqueues the stylesheet for GRead's navigation menu
function hs_enqueue_nav_assets()
{
	wp_enqueue_style(
		'hs-nav-style',
		plugin_dir_url(__FILE__) . 'css/hs-nav.css',
		[],
		'1.0.0'
	);
}
add_action('wp_enqueue_scripts', 'hs_enqueue_nav_assets');

// Injects the HTML for the navigation menu right after the opening body tag.
function hs_inject_nav_html()
{
	if (!is_user_logged_in())
	{
		return;
	}

	?>

	<div id="hs-nav-container">
		<div class="hs-nav-trigger">
			<i class="fas fa-bars"></i>
		</div>

		<div class="hs-nav-menu">
			<a href="<?php echo esc_url(home_url('/')); ?>" title="Activity Feed">
				<i class="fas fa-feed"></i>
			</a>

			<a href="<?php echo esc_url(home_url('/my-books/')); ?>" title=My Library">
				<i class="fas fa-book-open"></i>
			</a>

			<a href="<?php echo esc_url(home_url('/search/')); ?>" title="Search Books">
				<i class="fas fa-search"></i>
			</a>

		</div>
	</div>
	<?php
}
add_action('wp_footer', 'hs_inject_nav_html');
*/

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
		[],
		'1.0.0',
		true
	);
}

add_action('wp_enqueue_scripts', 'ol_enqueue_modal_assets');



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

/*
// Add a box for storing page counts
function hs_add_meta()
{
    add_meta_box(
        'hs_book_details',
        'Book Details',
        'hs_book_details_html',
        'book'
    );
}
// Make the action available
add_action('add_meta_boxes', 'hs_add_meta');
*/

/*
// The HTML for the meta boxes
function hs_book_details_html($post)
{
    // Get the pagecount
    // The variable is calculated by retrieving data from each post
    $pagecount = get_post_meta($post -> ID, 'nop', true);
    // Get the author
    $author = get_post_meta($post -> ID, 'book_author', true);
    // Get the ISBN
    $isbn = get_post_meta($post -> ID, 'book_isbn', true);
    
    wp_nonce_field('hs_save_details', 'hs_nonce');
    ?>

    <p>
        <label for="hs_author" style="display:block; margin-bottom: 5px;"><strong>Author:</strong></label>
        <input type="text" id="hs_author" name="hs_author" value="<?php echo esc_attr($author); ?>" style="width:100%;" />
    </p>
    
    <p>
        <label for="hs_pagecount">Pages:</label>
        <input type="number" id="hs_pagecount" name="hs_pagecount" value="<?php echo esc_attr($pagecount); ?>" />
    </p>

    <p>
        <label for="hs_isbn" style="display:block; margin-bottom: 5px;"><strong>ISBN:</strong></label>
        <input type="text" id="hs_isbn" name="hs_isbn" value="<?php echo esc_attr($isbn); ?>" style="width:100%;" />
    </p>    
    <?php
}
*/
/*
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
*/


 // Enqueue scripts/styles

function hs_enqueue()

{

    // Only load pages that use the shortcodes

    global $post;


    if (is_a($post, 'WP_Post') && (has_shortcode($post -> post_content, 'my_books') || has_shortcode($post -> post_content, 'book_directory') || has_shortcode($post -> post_content, 'hs_book_search')))

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


// [my_books]: Displays user's books/reading progress
function hs_my_books_shortcode()
{
    if (!is_user_logged_in()) {
        return '<p>Please log in to track your reading!</p>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'user_books';

    $my_books = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if (empty($my_books)) {
        return '<p>You have not added any books to your library. Browse the book database and add what books you are reading to your library. If you cannot find the book you are reading, submit it to the database and get some rewards!</p>';
    }

    // Initialize HTML strings for each section
    $reading_books_html = '';
    $completed_books_html = '';
    $completed_count = 0;

    foreach ($my_books as $book_entry) {
        $book = get_post($book_entry->book_id);
        if ($book) {
            $total_pages = get_post_meta($book_entry->book_id, 'nop', true);
            $current_page = $book_entry->current_page;
            $progress = ($total_pages > 0) ? round(($current_page / $total_pages) * 100) : 0;
            
            // This variable determines if a book is complete
            $is_completed = ($total_pages > 0 && $current_page >= $total_pages);
            
            $li_class = $is_completed ? 'hs-my-book completed' : 'hs-my-book';
            $bar_class = $is_completed ? 'hs-progress-bar golden' : 'hs-progress-bar';
            
            // Initialize the HTML for THIS book item.
            $book_html = '<li class="' . $li_class . '" data-list-book-id="' . esc_attr($book_entry->book_id) . '">';
            $book_html .= '<h3><a style="color: #0056b3;" href="' . esc_url(get_permalink($book->ID)) . '">' . esc_html($book->post_title) . '</a></h3>';
            $book_html .= '<div class="hs-progress-bar-container"><div class="' . $bar_class . '" style="width: ' . $progress . '%;"></div></div>';
            $book_html .= '<p>Progress: ' . $progress . '% (' . esc_html($current_page) . ' / ' . esc_html($total_pages) . ' pages)</p>';
            
            $book_html .= '<form class="hs-progress-form">';
            $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
            $book_html .= '<label>Update current page number:</label>';
            $book_html .= '<input type="number" name="current_page" min="0" max="' . esc_attr($total_pages) . '" value="' . esc_attr($current_page) . '">';
            $book_html .= '<button type="submit" class="hs-button">Save Progress</button>';
            $book_html .= '<span class="hs-feedback"></span>';
            $book_html .= '</form>';
            
            $book_html .= '<button class="hs-button hs-remove-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Remove</button>';
            $book_html .= '</li>';
            
            // Append the book's HTML to the correct section string.
            if ($is_completed) {
                $completed_books_html .= $book_html;
                $completed_count++;
            } else {
                $reading_books_html .= $book_html;
            }
        }
    }

    // Assemble the final output after the loop has finished
    $output = '<div class="hs-container">';
    
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
		$details_html .= '<li><strong>This book is in </strong>' . intval($total_readers) . '<strong>libraries.</strong></li>';
		$details_html .= '<li><strong>This book has been completed by </strong>' . intval($completed_count) . ' user(s).</li>';
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

/**
 * Converts #book-title tags in BuddyPress activity/comments into clickable links.
 * This runs after the content is saved/retrieved.
 *
 * @param string $content The BuddyPress activity or comment content.
 * @return string The modified content with book tags replaced by links.
 */
function hs_autolink_book_tags($content) {
    // Look for a pattern: # followed by one or more (letters, numbers, spaces, or hyphens)
    // Spaces will be replaced with hyphens later to match post slugs.
    $pattern = '/#([a-zA-Z0-9\s-]+)/';

    // Check if the content matches the pattern
    if (preg_match_all($pattern, $content, $matches)) {
        // $matches[1] holds the captured group (e.g., "A Great Book" or "a-great-book")

        // Iterate through all matches found in the content
        foreach ($matches[1] as $key => $book_identifier) {
            $original_tag = $matches[0][$key]; // e.g., "#A Great Book"
            $clean_identifier = trim($book_identifier);

            // Convert the identifier to a slug to reliably match the book's post_name
            $book_slug = sanitize_title($clean_identifier);

            // Find the book (custom post type 'book') by its slug
            $args = array(
                'name'               => $book_slug,
                'post_type'          => 'book',
                'post_status'        => 'publish',
                'posts_per_page'     => 1,
                'suppress_filters'   => false
            );
            $books_query = new WP_Query($args);

            if ($books_query->have_posts()) {
                $books_query->the_post();
                $book_title = get_the_title();
                $book_permalink = get_permalink();

                // Create the replacement HTML link
                $link_html = '<a href="' . esc_url($book_permalink) . '" title="' . esc_attr($book_title) . '" class="book-tag-link">#' . esc_html($book_title) . '</a>';

                // Replace the original tag in the activity content.
                $content = str_replace($original_tag, $link_html, $content);
            }
            wp_reset_postdata(); // Reset global post data
        }
    }

    // Return the modified content
    return $content;
}

// Hook the function into BuddyPress activity content filters.
add_filter('bp_get_activity_content_body', 'hs_autolink_book_tags', 10);
add_filter('bp_activity_comment_content', 'hs_autolink_book_tags', 10);

/**
 * AJAX handler to search for book titles for At.js autocomplete.
 * This function must return data in a specific structure for At.js.
 */
function hs_bp_book_autocomplete_search() {
    // 1. Security Check (At.js sends the nonce in the POST data)
    check_ajax_referer( 'bp-mentions' ); 
    
    // 2. Retrieve the search term (At.js sends it as $_POST['q'])
    $search_term = ! empty( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

    // Exit if search term is too short
    if ( empty( $search_term ) || strlen( $search_term ) < 2 ) {
        // Must return an empty array on failure, wrapped in 'suggestions'
        wp_send_json_success( array( 'suggestions' => array() ) ); 
    }

    // 3. Query for 'book' custom post types
    $args = array(
        'post_type'          => 'book',
        'post_status'        => 'publish',
        'posts_per_page'     => 10,
        // Use 's' for general search or 'title' for title-only search
        's'                  => $search_term, 
        'suppress_filters'   => false
    );

    $books_query = new WP_Query( $args );
    $suggestions = array();

    if ( $books_query->have_posts() ) {
        while ( $books_query->have_posts() ) {
            $books_query->the_post();
            $book_id = get_the_ID();
            $author = get_post_meta( $book_id, 'book_author', true );
            
            // In the hs_bp_book_autocomplete_search function, inside the loop:

            $suggestions[] = array(
                // Use 'display_name' to match the At.js template
                'display_name' => get_the_title( $book_id ), 
                'author'       => ! empty( $author ) ? $author : 'Unknown',
                'id'           => $book_id, 
            );
        }
        wp_reset_postdata();
    }

    // 4. Critical: Must wrap the suggestions array in a 'suggestions' key for At.js
    wp_send_json_success( array( 'suggestions' => $suggestions ) );
}

// Hook the AJAX handler
add_action( 'wp_ajax_hs_bp_book_autocomplete_search', 'hs_bp_book_autocomplete_search' );

/**
 * Directly filters the At.js settings array to include the book mention configuration (#).
 * This is the most reliable method for adding a custom mention type.
 *
 * @param array $settings The array of At.js configuration settings.
 * @return array The modified settings array.
 */
function hs_bp_register_book_mentions_settings( $settings ) {
    // Check if the base 'user' configuration exists to clone
    if ( ! isset( $settings['user'] ) ) {
        return $settings;
    }

    // Clone the standard user configuration
    $book_mentions_config = $settings['user'];

    // --- Configuration for Book Tags (#) ---

    // 1. Change the at sign/delimiter to the hash symbol (#)
    $book_mentions_config['at'] = '#'; 
    
    // 2. Change the server-side AJAX action
    $book_mentions_config['data'] = array(
        'action' => 'hs_bp_book_autocomplete_search',
        'nonce'  => wp_create_nonce( 'bp-mentions' ),
    );

    // 3. Configure display template (must match 'display_name' and 'author' in AJAX response)
    $book_mentions_config['display_tpl'] = '<li><a href="#"><strong class="book-title">${display_name}</strong> <span>(${author})</span></a></li>';

    // 4. Configure insertion template
    $book_mentions_config['insert_tpl'] = '#${display_name} ';

    // 5. Set look_up key
    $book_mentions_config['look_up'] = 'book';
    $book_mentions_config['alias']   = 'book';

    // Add the new configuration under the 'book' key
    $settings['book'] = $book_mentions_config;

    return $settings;
}

// Hook into the filter that specifically modifies the At.js configuration array.
add_filter( 'bp_mentions_atjs_settings', 'hs_bp_register_book_mentions_settings' );