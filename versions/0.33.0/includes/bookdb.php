<?php
// This controls the core functionality of HotSoup's book database management

// If the user tries to access this file directly
if (!defined('ABSPATH'))
{
	// GIT OUT
	exit;
}


// Register the custom post type 'book'
/*
	The $labels portion of the function lay out the ways that GRead refers to this post type.
	- The general name is 'Books'
	- The name for a single post of this type is 'Book'
	- The name for the menu (the page in the admin panel) is 'Books'.
	- The button for adding a new post of this type says 'Add Book'.
	- Modifying a post of this type is done via a button that says 'Edit Book'.
*/
function gr_books_register_cpt()
{
    $labels = [
        'name'          => _x('Books', 'Post type general name', 'gr-books'),
        'singular_name' => _x('Book', 'Post type singular name', 'gr-books'),
        'menu_name'     => _x('Books', 'Admin Menu text', 'gr-books'),
        'add_new_item'  => __('Add Book', 'gr-books'),
        'edit_item'     => __('Edit Book', 'gr-books'),
    ];

	/*
	The arugments that are used for the book post type are:

	- labels, which is set to the value of $labels (defined above)
	- public, referring to the default post visibility setting, allows for anybody to see a book post
	- has_archive, allowing for book posts to be archived.
	- rewrite, specifying a custom slug for this post type: "books"
	- supports, describing what is supported by this post type.
	- menu_icon, setting the icon that should be used in the admin panel.
	*/
    $args = [
        'labels'        => $labels,
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'books'],
		'show_in_rest' => true,
        'supports'      => ['title', 'editor', 'thumbnail'],
        'menu_icon'     => 'dashicons-book-alt',
    ];

	// Register the custom book type, call it 'book', and use the arguments specified by $args (explained above).
    register_post_type('book', $args);
}
// When the plugin is initialized, register this custom post type. It is vital to register this CPT immediately, as the rest of the plugin depends on it.
add_action('init', 'gr_books_register_cpt');


// Register ACF fields for the 'book' post type
function gr_books_register_acf_fields()
{
	// If the function 'acf_add_local_field_group' exists
	if (function_exists('acf_add_local_field_group'))
	{
        acf_add_local_field_group([
            'key'    => 'group_book_details',
            'title'  => 'Book Details',
            'fields' => [
                [
			'key' => 'field_book_author',
			'label' => 'Author',
			'name' => 'book_author',
			'type' => 'text',
			'required' => 1,
			'sanitize_callback' => function( $value ) {
				return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
			}
		],

                [
			'key' => 'field_book_isbn',
			'label' => 'ISBN',
			'name' => 'book_isbn',
			'type' => 'text',
			'sanitize_callback' => function( $value )
			{
				return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
			}
		],

                [
			'key' => 'field_publication_year',
			'label' => 'Publication year',
			'name' => 'publication_year',
			'type' => 'number',
			'sanitize_callback' => function( $value )
			{
				return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
			}
		],

                [
			'key' => 'field_nop',
			'label' => 'Number of pages',
			'name' => 'nop',
			'type' => 'number',
			'sanitize_callback' => function( $value )
			{
				return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
			}
		],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'book']]],
        ]);
    }

	// If the function 'acf_add_local_field_group' does not exist, break out of the loop.
}
add_action('acf/init', 'gr_books_register_acf_fields');

// =============================================================================
// SECTION 2: Admin Area Customizations
// =============================================================================

// Adds the custom columns to Book CPT admin list
function gr_books_add_admin_columns($columns) {
    $columns['book_author'] = __('Author', 'gr-books');
    $columns['book_isbn'] = __('ISBN', 'gr-books');
    return $columns;
}
add_filter('manage_book_posts_columns', 'gr_books_add_admin_columns');

// Populate them columnz.
function gr_books_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'book_author':
            echo esc_html(get_field('book_author', $post_id));
            break;
        case 'book_isbn':
            echo esc_html(get_field('book_isbn', $post_id));
            break;
    }
}
add_action('manage_book_posts_custom_column', 'gr_books_custom_column_content', 10, 2);

// =============================================================================
// SECTION 3: Moderation Logic
// NOTE: Kept the more complete version of your moderation functions and removed the conflicting, less complete one.
// =============================================================================

// Handles actions when a book submission is approved.
function gr_handle_book_approval($new_status, $old_status, $post) {
    if ('book' !== $post->post_type || 'pending' !== $old_status || 'publish' !== $new_status) {
        return;
    }
    $user_id = $post->post_author;
    if (!$user_id) {
        return;
    }
    // TODO: Award points, track approved books for each user
    if (function_exists('messages_new_message')) {
        messages_new_message(array(
            'sender_id'  => bp_loggedin_user_id(),
            'recipients' => array($user_id),
            'subject'    => 'Your book submission has been approved!',
            'content'    => "Congratulations! Your submission for the book \"" . esc_html($post->post_title) . "\" has been approved and is now live.",
            'error_type' => 'wp_error',
        ));
    }
}
add_action('transition_post_status', 'gr_handle_book_approval', 10, 3);

// Adds a meta box to the 'book' post type editor for rejecting submissions.
function gr_add_rejection_meta_box() {
    global $post;
    if ($post && $post->post_type === 'book' && $post->post_status === 'pending') {
        add_meta_box('gr_rejection_box', 'Submission Review', 'gr_rejection_meta_box_html', 'book', 'side', 'high');
    }
}
add_action('add_meta_boxes', 'gr_add_rejection_meta_box');

// Renders the HTML for the rejection meta box.
function gr_rejection_meta_box_html($post) {
    wp_nonce_field('gr_reject_book_action', 'gr_reject_nonce');
    ?>
    <p>
        <label for="gr_rejection_reason"><strong>Rejection Reason (optional):</strong></label>
        <textarea name="gr_rejection_reason" id="gr_rejection_reason" rows="4" style="width:100%;"></textarea>
    </p>
    <p>
        <button type="submit" name="gr_reject_submission" class="button button-danger" style="width:100%; text-align:center;">Reject and Notify User</button>
    </p>
    <?php
}

// Handles the logic for rejecting a book submission.
function gr_handle_book_rejection($post_id, $post) {
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !isset($_POST['gr_reject_submission']) || !isset($_POST['gr_reject_nonce']) || !wp_verify_nonce($_POST['gr_reject_nonce'], 'gr_reject_book_action')) {
        return;
    }
    if ($post->post_type !== 'book') {
        return;
    }
    $user_id = $post->post_author;
    if ($user_id && function_exists('messages_new_message')) {
        $reason_text = trim($_POST['gr_rejection_reason']);
        if (!empty($reason_text)) {
            $reason = "The reason provided was: <br><br><em>" . wp_kses_post(stripslashes($reason_text)) . "</em>";
        } else {
            $reason = "No specific reason was provided.";
        }
        messages_new_message(array(
            'sender_id'  => bp_loggedin_user_id(),
            'recipients' => array($user_id),
            'subject'    => 'Your book submission was not approved',
            'content'    => "We're sorry, but your submission for \"" . esc_html($post->post_title) . "\" was not approved. <br><br>" . $reason,
        ));
    }
    wp_delete_post($post_id, true);
    wp_safe_redirect(admin_url('edit.php?post_type=book&post_status=pending'));
    exit;
}
add_action('save_post', 'gr_handle_book_rejection', 10, 2);

// =============================================================================
// SECTION 4: Frontend Shortcodes
// =============================================================================

// --- CONFLICT RESOLVED: Removed the duplicate, simpler [book_list] shortcode and kept your more advanced one with DataTables. ---

// Enqueues DataTables assets for the book list.
// While reviewing this, I thought it was written in Spanish for a moment.
function gr_enqueue_datatable_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'book_list')) {
        wp_enqueue_style('datatables-css', '//cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
        wp_enqueue_script('datatables-js', '//cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), null, true);
        $init_script = "jQuery(document).ready(function($) { $('.gr-book-list').DataTable(); });";
        wp_add_inline_script('datatables-js', $init_script);
    }
}
add_action('wp_enqueue_scripts', 'gr_enqueue_datatable_assets');

// Renders the book list via [book_list] shortcode
function gr_books_display_list_shortcode() {
    $args = ['post_type' => 'book', 'posts_per_page' => -1, 'post_status' => 'publish'];
    $books_query = new WP_Query($args);
    ob_start();
    if ($books_query->have_posts()) { ?>
        <table class="gr-book-list display">
            <thead><tr><th>Name</th><th>Author</th><th>Publication Year</th><th>Pages</th><th>ISBN</th></tr></thead>
            <tbody>
                <?php while ($books_query->have_posts()) : $books_query->the_post(); ?>
                    <tr>
                        <td><?php the_title(); ?></td>
                        <td><?php echo esc_html(get_field('book_author')); ?></td>
                        <td><?php echo esc_html(get_field('publication_year')); ?></td>
                        <td><?php echo esc_html(get_field('nop')); ?></td>
                        <td><?php echo esc_html(get_field('book_isbn')); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php wp_reset_postdata();
    } else {
        echo '<p>There are no books in the database!</p>';
    }
    return ob_get_clean();
}
add_shortcode('book_list', 'gr_books_display_list_shortcode');

// Processes the frontend submission form
function gr_handle_submission() {
    if ('POST' !== $_SERVER['REQUEST_METHOD'] || !isset($_POST['gr_book_nonce']) || !wp_verify_nonce($_POST['gr_book_nonce'], 'gr_book_submission')) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    if (empty(trim($_POST['book_title'])) || empty(trim($_POST['book_author']))) {
        wp_die('You need to specify an author and a title.');
    }
    // CRITICAL FIX: 'book' must be a string.
    $new_book = array('post_title' => sanitize_text_field($_POST['book_title']), 'post_status' => 'pending', 'post_author' => get_current_user_id(), 'post_type' => 'book');
    $post_id = wp_insert_post($new_book);
    if ($post_id && !is_wp_error($post_id)) {
        update_field('book_author', sanitize_text_field($_POST['book_author']), $post_id);
        update_field('book_isbn', sanitize_text_field($_POST['book_isbn']), $post_id);
        update_field('publication_year', intval($_POST['publication_year']), $post_id);
        update_field('nop', intval($_POST['nop']), $post_id);
        $redirect_url = add_query_arg('submission_success', 'true', wp_get_referer());
        wp_safe_redirect($redirect_url);
        exit;
    }
}
add_action('template_redirect', 'gr_handle_submission');

// Renders book submission form via [submission_form] shortcode
function gr_submission_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You need to log in in order to submit a book!</p>';
    }
    ob_start();
    ?>
    <?php if (isset($_GET['submission_success']) && $_GET['submission_success'] == 'true') : ?>
        <p style="color: green;">Your submission has been received! Thank you for your help! A moderator will review your submission and you will get an update on its status. Check your messages!</p>
    <?php endif; ?>
    <form id="book-submission-form" method="post">
        <p><label for="book_title">Title*</label><br><input type="text" id="book_title" name="book_title" required></p>
        <p><label for="book_author">Author*</label><br><input type="text" id="book_author" name="book_author" required></p>
        <p><label for="book_isbn">ISBN</label><br><input type="text" id="book_isbn" name="book_isbn" required></p>
        <p><label for="publication_year">Publication year</label><br><input type="number" id="publication_year" name="publication_year" max="<?php echo date('Y'); ?>" required></p>
        <p><label for="nop">Number of pages</label><br><input type="number" id="nop" name="nop" min="1" required></p>
        <?php wp_nonce_field('gr_book_submission', 'gr_book_nonce'); ?>
        <p><input type="submit" value="Submit"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('submission_form', 'gr_submission_shortcode');


// Add an ISBN to a book post
function hs_add_book_isbn($book_id, $isbn, $edition = '', $year = null)
{
	global $wpdb;
	$wpdb -> insert( $wpdb -> prefix . 'hs_book_isbns', array(
		'book_id' => intval($book_id),
		'isbn' => sanitize_text_field($edition),
		'edition' => sanitize_text_field($edition),
		'publication_year' => $year ? intval($year) : null,
	));
}


// Retrieve a book post by an ISBN
function hs_get_book_by_isbn($isbn)
{
	global $wpdb;
	return $wpdb -> get_row($wpdb -> prepare(
		"SELECT book_id FROM {$wpdb -> prefix}hs_book_isbns WHERE isbn = %s LIMIT 1",
		sanitize_text_field($isbn)
	));
}


// Retrieve all ISBNS for a book post
function hs_get_book_isbns($book_id)
{
	global $wpdb;
	return $wpdb -> get_col($wpdb -> prepare(
		"SELECT isbn FROM {$wpdb -> prefix}hs_book_isbns WHERE book_id = %d",
		intval($book_id)
	));
}
