<?php
// This provides us with a menu item for importing books into the database.


// Add the importer page to the admin panel.
function add_importer_page()
{
    /*
        Title: Importer
        Heading: Import Books
        Requires that the user can manage_options
        Uses the slug "import-books" (/import-books)
        The code for the page is in 'ol_render_importer_page'
        Use the book icon for the page's entry in the admin panel.
        20 is the priority of the page, I think.
    */
    add_menu_page(
        'Importer',
        'Import Books',
        'manage_options',
        'import-books',
        'ol_render_importer_page',
        'dashicons-book-alt',
        20
    );
}
// Using the admin_menu hook, add the importer page.
add_action('admin_menu', 'add_importer_page');


// Renders the HTML for the importer's page in the administator panel
function ol_render_importer_page()
{
    ?>
    <div class="wrap">
        // The heading for the book importer page
        <h1>HotSoup! Book Importer</h1>
        // The text displayed below the heading on the importer page
        <p>Enter an ISBN and the importer will (probably) do all the work!</p>

        <?php
        // OLD MESSAGE LOGIC REMOVED
        // The AJAX message area will be injected here.
        ?>

        <div id="admin-message-area"></div>

        <form id="admin-import-form" method="post">
            <input type="hidden" name="action" value="import_book_ajax">
            <?php wp_nonce_field('import_book_nonce'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="isbn_to_import">ISBN</label>
                    </th>

                    <td>
                        <input type="text" id="isbn_to_import" name="isbn_to_import" class="regular-text" required />
                        <p class="description">Enter the 10/13 digits ISBN.</p>
                    </td>
                </tr>
            </table>

            <p><input type="submit" name="submit" id="admin-import-submit-btn" class="button button-primary" value="Import!"></p>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {

        var $form = $('#admin-import-form');
        var $messageArea = $('#admin-message-area');
        var $submitButton = $('#admin-import-submit-btn');

        // Function to generate and display the message HTML
        function displayMessage(type, message, details = '') {
            var messageHtml = '';
            var fullMessage = message;

            if (type === 'error' && details) {
                fullMessage += ' Details: ' + details;
            }

            // Map AJAX response types to WordPress notice classes
            if (type === 'success') {
                messageHtml = '<div class="notice notice-success is-dismissible"><p>Successfully imported the book!</p></div>';
            } else if (type === 'exists') {
                messageHtml = '<div class="notice notice-warning is-dismissible"><p>Oops! It looks like there is already a book in the database with that ISBN.</p></div>';
            } else if (type === 'error') {
                messageHtml = '<div class="notice notice-error is-dismissible"><p>ERROR: ' + fullMessage + '</p></div>';
            }
            $messageArea.html(messageHtml);
        }

        // Handle the form submission
        $form.on('submit', function(e) {
            e.preventDefault(); // <-- The key step: Prevents the page redirect

            $messageArea.empty(); // Clear previous messages
            $submitButton.val('Importing...').prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: ajaxurl, // global variable available in the WordPress admin area
                data: $form.serialize(),

                success: function(response) {
                    if (response.success) {
                        // Success or 'exists' messages from wp_send_json_success
                        displayMessage(response.data.type, response.data.message);
                        if (response.data.type === 'success') {
                            $('#isbn_to_import').val(''); // Clear field on success
                        }
                    } else {
                        // Error messages from wp_send_json_error
                        var message = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                        var details = response.data && response.data.details ? response.data.details : '';
                        displayMessage('error', message, details);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    displayMessage('error', 'A critical network error occurred. Check your console for details.');
                },
                complete: function() {
                    $submitButton.val('Import!').prop('disabled', false); // Re-enable button
                }
            });
        });
    });
    </script>

    <?php
}



/**
 * Handles the ISBN import form submission via AJAX.
 * Returns a JSON response instead of redirecting.
 */
function handle_import_book_ajax()
{
    // Security check
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'import_book_nonce'))
    {
        wp_send_json_error(['message' => 'The security check failed. You shall not pass.']);
        wp_die();
    }
    
    // Check user capability (uncomment if needed)
    /*
    if (!current_user_can('manage_options'))
    {
        wp_send_json_error(['message' => 'You are not allowed to do that. Get lost!']);
        wp_die();
    }
    */

    $isbn = sanitize_text_field($_POST['isbn_to_import']);

    if (empty($isbn))
    {
        wp_send_json_error(['message' => 'You need to provide literally one thing, and you did not provide it.']);
        wp_die();
    }


    $result = import_by_isbn($isbn);

    if (is_wp_error($result))
    {
        wp_send_json_error([
            'message' => 'Oops! An error has occurred.',
            'details' => $result->get_error_message()
        ]);
    }

    elseif ($result === 'exists')
    {
        wp_send_json_success([
            'message' => 'Oops! There is already a book with that ISBN in the database!',
            'type' => 'exists'
        ]);
    }

    else
    {
        wp_send_json_success([
            'message' => 'The book has been successfully imported! Thank you for making GRead even better!',
            'type' => 'success'
        ]);
    }

    // Must die after an AJAX call
    wp_die();
}
add_action('wp_ajax_import_book_ajax', 'handle_import_book_ajax');
// If you want logged-out users to be able to submit (only if the shortcode allowed it):
// add_action('wp_ajax_nopriv_import_book_ajax', 'handle_import_book_ajax');

// Importer's logic (remains unchanged)
function import_by_isbn($isbn)
{
    $args = [
        'post_type' => 'book',
        'post_status' => 'any',
        'meta_query' => [
            [
                'key' => 'book_isbn',
                'value' => $isbn,
                'compare' => '=',
            ],
        ],

        'posts_per_page' => 1,
    ];

    $existing_books = new WP_Query($args);

    if ($existing_books -> have_posts())
    {
        return 'exists';
    }


    $api_url = sprintf('https://openlibrary.org/api/books?bibkeys=ISBN:%s&format=json&jscmd=data', $isbn);
    $response = wp_remote_get($api_url);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200)
    {
        return new WP_Error('api_error', 'Could not connect via the OpenLibrary API.');
    }


    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $book_data_key = 'ISBN:' . $isbn;

    if (empty($data) || !isset($data[$book_data_key]))
    {
        return new WP_Error('not_found', 'Could not find that book in the OpenLibrary database.');
    }

    $book_info = $data[$book_data_key];

    $post_title = isset($book_info['title']) ? sanitize_text_field($book_info['title']) : 'Untitled';
    $post_content = isset($book_info['notes']) ? sanitize_textarea_field($book_info['notes']) : 'No description available.';

    $new_post_args = [
        'post_type' => 'book',
        'post_title' => $post_title,
        'post_content' => $post_content,
        'post_status' => 'publish',
        'post_name' => $isbn,
        'post_author' => get_current_user_id()
    ];


    $post_id = wp_insert_post($new_post_args);

    if (is_wp_error($post_id))
    {
        return $post_id;
    }

    update_field('book_isbn', $isbn, $post_id);

    if (isset($book_info['authors']))
    {
        $author_names = array_map(function($author)
        {
            return sanitize_text_field($author['name']);
        },

        $book_info['authors']);
        update_field('book_author', implode(', ', $author_names), $post_id);
    }

    if (isset($book_info['publish_date']))
    {
        preg_match('/(\d{4})/', $book_info['publish_date'], $matches);
        $year = $matches[0] ?? null;

        if ($year)
        {
            update_field('publication_year', intval($year), $post_id);
        }
    }

    if (isset($book_info['number_of_pages']))
    {
        update_field('nop', intval($book_info['number_of_pages']), $post_id);
    }

    if (isset($book_info['cover']['large']))
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');


        $image_url = $book_info['cover']['large'];
        $attachment_id = media_sideload_image($image_url, $post_id, $post_title, 'id');

        if (!is_wp_error($attachment_id))
        {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    $author_id = get_current_user_id();

    if ($author_id && function_exists('hs_increment_books_added'))
    {
        hs_increment_books_added($author_id);
    }

    return $post_id;
}