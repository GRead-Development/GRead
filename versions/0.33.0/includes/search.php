<?php

// This is the code that powers the database's search.


if (!defined('ABSPATH')) {
    exit;
}


define('HS_SEARCH_TABLE', $GLOBALS['wpdb']->prefix . 'hs_book_search_index');

function hs_search_add_to_index($post_id)
{
    if (get_post_type($post_id) !== 'book' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    global $wpdb;
    $book = get_post($post_id);
    $table_name = HS_SEARCH_TABLE;

    if ($book->post_status !== 'publish') {
        $wpdb->delete($table_name, ['book_id' => $post_id], ['%d']);
        return;
    }

	// Retrieve metadata
	$author = get_post_meta($post_id, 'book_author', true);
	$isbn = get_post_meta($post_id, 'book_isbn', true);
	$permalink = get_permalink($post_id);
	$title = get_post_field('post_title', $post_id);

    $wpdb->replace(
        $table_name,
        [
            'book_id'   => $post_id,
            'title'     => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'author'    => html_entity_decode($author, ENT_QUOTES, 'UTF-8'),
            'isbn'      => html_entity_decode($isbn, ENT_QUOTES, 'UTF-8'),
            'permalink' => $permalink
        ],
        ['%d', '%s', '%s', '%s', '%s']
    );
}
add_action('save_post_book', 'hs_search_add_to_index', 20);
//add_action('updated_post_meta', 'hs_search_update_on_meta_change', 10, 4);

function hs_search_rm_from_index($post_id)
{
    if (get_post_type($post_id) !== 'book') {
        return;
    }
    global $wpdb;
    $wpdb->delete(HS_SEARCH_TABLE, ['book_id' => $post_id], ['%d']);
}
add_action('delete_post', 'hs_search_rm_from_index');


function hs_search_admin_notice()
{
    // Check if we are on the tools page to avoid showing redundant notices.
    $current_screen = get_current_screen();
    if ($current_screen && $current_screen->id === 'tools_page_hs-search-tools') {
        return;
    }

    if (get_option('hs_search_needs_indexing') === 'true' && current_user_can('manage_options')) {
?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>HotSoup Notice:</strong> The search index needs to be built.
                <a href="#" id="hs-build-index-button" class="button button-primary" style="margin-left: 10px;">Build Index</a>
                <span id="hs-indexing-spinner" class="spinner" style="float: none; vertical-align: middle; margin-left: 5px;"></span>
            </p>
        </div>
    <?php
    }
}
add_action('admin_notices', 'hs_search_admin_notice');


function hs_search_admin_scripts()
{
    if (get_option('hs_search_needs_indexing') === 'true' && current_user_can('manage_options')) {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#hs-build-index-button').on('click', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const spinner = $('#hs-indexing-spinner');

                    button.prop('disabled', true);
                    spinner.addClass('is-active');

                    $.post(ajaxurl, {
                        action: 'hs_build_index',
                        _ajax_nonce: '<?php echo wp_create_nonce("hs_reindex_nonce"); ?>'
                    }, function(response) {
                        if (response.success) {
                            button.closest('.notice').find('p').html('<strong>HotSoup Search:</strong> ' + response.data);
                        } else {
                            button.closest('.notice').removeClass('notice-warning').addClass('notice-error');
                            button.closest('.notice').find('p').html('<strong>HotSoup Search:</strong> [ERR]: ' + response.data);
                        }
                    });
                });
            });
        </script>
    <?php
    }
}
add_action('admin_footer', 'hs_search_admin_scripts');


function hs_build_index_callback()
{
    if (!function_exists('check_ajax_referer') || !check_ajax_referer('hs_reindex_nonce', false, false) || !current_user_can('manage_options')) {
        wp_send_json_error('Security check failed.');
    }

    global $wpdb;
    $table_name = HS_SEARCH_TABLE;
    $wpdb->query("TRUNCATE TABLE $table_name");

    $book_query = new WP_Query(['post_type' => 'book', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);

    if ($book_query->have_posts()) {
        foreach ($book_query->posts as $book_id) {
            hs_search_add_to_index($book_id);
        }
    }

    delete_option('hs_search_needs_indexing');
    wp_send_json_success('The search index was built successfully. ' . count($book_query->posts) . ' books have been indexed.');
}
add_action('wp_ajax_hs_build_index', 'hs_build_index_callback');


function hs_search_enqueue_assets()
{
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'hs_book_search')) {
        wp_enqueue_style('hs-search-css', plugin_dir_url(__FILE__) . '../css/hs-search.css', [], '2.5.0');
        wp_enqueue_script('hs-search-js', plugin_dir_url(__FILE__) . '../js/hs-search.js', ['jquery'], '2.5.0', true);
        wp_localize_script('hs-search-js', 'hs_search_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('hs_search_nonce'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'hs_search_enqueue_assets');


function hs_search_callback()
{
    if (!function_exists('check_ajax_referer')) {
        wp_send_json_error(['message' => 'A critical WordPress function is missing. The installation may be corrupt.']);
        return;
    }

    check_ajax_referer('hs_search_nonce', 'nonce');

    try {
        global $wpdb;
        $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $search_by = isset($_POST['search_by']) ? sanitize_key($_POST['search_by']) : 'all';

        if (strlen($search_query) < 3) {
            wp_send_json_error(['message' => 'The search query must be at least 3 characters long.']);
        }

        $table_name = HS_SEARCH_TABLE;
        $like_query = '%' . $wpdb->esc_like($search_query) . '%';

        $where_clauses = [];
        $query_params = [];

        if ($search_by === 'title' || $search_by === 'all') {
            $where_clauses[] = "title LIKE %s";
            $query_params[] = $like_query;
        }
        if ($search_by === 'author' || $search_by === 'all') {
            $where_clauses[] = "author LIKE %s";
            $query_params[] = $like_query;
        }
        if ($search_by === 'isbn' || $search_by === 'all') {
            $where_clauses[] = "isbn LIKE %s";
            $query_params[] = $like_query;
        }

        if (empty($where_clauses)) {
            wp_send_json_success(['results' => [],'user_library' => []]);
		return;
        }

        $sql = "SELECT book_id as id, title, author, isbn, permalink FROM $table_name WHERE " . implode(' OR ', $where_clauses) . " LIMIT 20";
        $query = $wpdb->prepare($sql, $query_params);
        $results = $wpdb->get_results($query, ARRAY_A);

	$user_library = [];
	if (is_user_logged_in())
	{
		$user_id = get_current_user_id();
		$user_books_table = $wpdb -> prefix . 'user_books';
		$user_library = $wpdb -> get_col($wpdb -> prepare("SELECT book_id FROM $user_books_table WHERE user_id = %d", $user_id));
	}

	$response_data =
	[
		'results' => $results,
		'user_library' => $user_library,
	];


        wp_send_json_success($response_data);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'A server error occurred.', 'details' => $e->getMessage()]);
    }
}
add_action('wp_ajax_hs_search', 'hs_search_callback');
add_action('wp_ajax_nopriv_hs_search', 'hs_search_callback');


function hs_search_shortcode()
{
    return '
    <div id="hs-search-container">
        <h2>Find Your New Favorite Book</h2>
        <div class="hs-search-form-wrapper">
            <input type="text" id="hs-book-search-input" placeholder="Find a book" autocomplete="off" />
            <div class="hs-search-options">
                <label>Search by:</label>
                <label><input type="radio" name="hs-search-by" value="all" checked> All</label>
                <label><input type="radio" name="hs-search-by" value="title"> Title</label>
                <label><input type="radio" name="hs-search-by" value="author"> Author</label>
                <label><input type="radio" name="hs-search-by" value="isbn"> ISBN</label>
            </div>
        </div>
        <div id="hs-search-results"></div>
    </div>';
}
add_shortcode('hs_book_search', 'hs_search_shortcode');

// Adds a page to the admin panel for manually reindexing the database.
function hs_search_add_tools_page()
{
	// Add the submenu page
	/*
		Located at /tools.php
		Called "HotSoup Search Tools"
		Title is "HotSoup Search"
		Requires that user can "manage_options" in order to use the page
		Uses 'hs-search-tools' function for the main code.
		Uses 'hs_search_tools_page_html' for the page's HTML
	*/
    add_submenu_page(
        'tools.php',
        'HotSoup Search Tools',
        'HotSoup Search',
        'manage_options',
        'hs-search-tools',
        'hs_search_tools_page_html'
    );
}
// Add the tools page to the admin panel.
add_action('admin_menu', 'hs_search_add_tools_page');

/**
 * Renders the HTML for the tools page.
 */
function hs_search_tools_page_html()
{
    // Check for status messages from the handler.
    if (isset($_GET['hs_status'])) {
        if ($_GET['hs_status'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> The activation routine completed. The "Build Index" notice should now be visible on your Dashboard.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> The activation routine failed to create the database table. Please check database user permissions.</p></div>';
        }
    }
?>
    <div class="wrap">
        <h1>HotSoup Search Tools</h1>
        <p>If the "Build Index" notice is not appearing on your dashboard after activating the plugin, you can use this tool to manually run the setup process.</p>
        <p>This will create the necessary database table and set the option required to show the indexing button.</p>
        <form method="post" action="">
            <?php wp_nonce_field('hs_manual_activate_action', 'hs_manual_activate_nonce'); ?>
            <p>
                <button type="submit" name="hs_run_activation" class="button button-primary">Run Activation Routine</button>
            </p>
        </form>
    </div>
<?php
}

/**
 * Handles the form submission from the tools page.
 */
function hs_search_handle_manual_activation()
{
    // Ensure this only runs if our button was clicked and the nonce is valid.
    if (isset($_POST['hs_run_activation']) && isset($_POST['hs_manual_activate_nonce']) && wp_verify_nonce($_POST['hs_manual_activate_nonce'], 'hs_manual_activate_action')) {

        // Manually call the activation function.
        hs_search_activate();

        // Verify that the table was created.
        global $wpdb;
        $table_name = HS_SEARCH_TABLE;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            wp_safe_redirect(admin_url('tools.php?page=hs-search-tools&hs_status=success'));
        } else {
            wp_safe_redirect(admin_url('tools.php?page=hs-search-tools&hs_status=error'));
        }
        exit;
    }
}
add_action('admin_init', 'hs_search_handle_manual_activation');


// Index all ISBNs for a book in the search table, called whenever a book is saved
function hs_search_index_all_isbns($post_id)
{
	if (get_post_type($post_id) !== 'book' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id))
	{
		return;
	}

	global $wpdb;
	$book = get_post($post_id);
	$table_name = HS_SEARCH_TABLE;

	if ($book -> post_status !== 'publish')
	{
		return;
	}


	// Retrieve all ISBNs for a book
	$isbns = hs_get_book_isbns($post_id);
	$title = html_entity_decode(get_post_field('post_title', $post_id), ENT_QUOTES, 'UTF-8');
	$author = html_entity_decode(get_post_meta($post_id, 'book_author', true), ENT_QUOTES, 'UTF-8');
	$permalink = get_permalink($post_id);

	// Insert an entry for each ISBN
	foreach($isbns as $isbn)
	{
		$wpdb -> insert($table_name, array(
			'book_id' => intval($post_id),
			'title' => $title,
			'author' => $author,
			'isbn' => html_entity_decode($isbn, ENT_QUOTES, 'UTF-8'),
			'permalink' => $permalink,
		), array('%d', '%s', '%s', '%s', '%s'));
	}
}
add_action('hs_isbn_added', 'hs_search_index_all_isbns', 21);
