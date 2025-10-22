<?php
// This provides us with a menu item for importing books into the database.


function add_importer_page()
{
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
add_action('admin_menu', 'add_importer_page');


// Renders the HTML for the importer's page in the administator panel
function ol_render_importer_page()
{
	?>
	<div class="wrap">
		<h1>HotSoup! Book Importer</h1>
		<p>Enter an ISBN and the importer will (probably) do all the work!</p>

		<?php

		// Return messages, based on response
		if (isset($_GET['message']))
		{
			$message = sanitize_text_field($_GET['message']);

			if ($message === 'success')
			{
				echo '<div class="notice notice-success is-dismissible"><p>Successfully imported the book!</p></div>';
			}

			elseif ($message === 'exists')
			{
				echo '<div class="notice notice-warning is-dismissible"><p>Oops! It looks like there is already a book in the database with that ISBN.</p></div>';
			}

			else
			{
				echo '<div class="notice notice-error is-dismissible"><p>ERROR: ' . esc_html(urldecode($_GET['details'])) . '</p></div>';
			}
		}

		?>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="import_book">
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

			<p><input type="submit" name="submit" class="button button-primary" value="Import!"></p>
		</form>
	</div>
	<?php
}



function handle_import_form_submission()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'import_book_nonce'))
	{
		wp_die('The security check failed. You shall not pass.');
	}
/*
	if (!current_user_can('manage_options'))
	{
		wp_die('You are not allowed to do that. Get lost!');
	}
*/

	$isbn = sanitize_text_field($_POST['isbn_to_import']);

	if (empty($isbn))
	{
		wp_die('You need to provide literally one thing, and you did not provide it.');
	}


	$result = import_by_isbn($isbn);


	$redirect_url = admin_url('admin.php?page=import-books');

	if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to']))
	{
		$redirect_url = esc_url_raw($_POST['redirect_to']);
	}


	if (is_wp_error($result))
	{
		$redirect_url = add_query_arg(['message' => 'error', 'details' => urlencode($result -> get_error_message())], $redirect_url);
	}

	elseif ($result === 'exists')
	{
		$redirect_url = add_query_arg('message', 'exists', $redirect_url);
	}

	else
	{
		$redirect_url = add_query_arg('message', 'success', $redirect_url);
	}

	wp_redirect($redirect_url);
	exit;
}
add_action('admin_post_import_book', 'handle_import_form_submission');


// Importer's logic
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

	return $post_id;
}
