<?php
// Allows users to create notes

// Activation, hooked into the main file for HotSoup
function hs_book_notes_activate()
{
	global $wpdb;

	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix} hs_book_notes (
		id INT PRIMARY KEY AUTO_INCREMENT,
		user_id INT,
		book_id INT,
		note_text LONGTEXT,
		page_number INT,
		page_start INT,
		page_end INT,
		is_public TINYINT(1) DEFAULT 0,
		date_created DATETIME,
		date_updated DATETIME,
		INDEX (user_Id),
		INDEX (book_id),
		INDEX (user_id, book_id)
	)" );

	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix} hs_note_likes (
		id INT PRIMARY KEY AUTO_INCREMENT,
		user_id INT,
		note_id INT,
		date_liked DATETIME,
		UNIQUE KEY user_note (user_id, note_id),
		INDEX (note_id)
	)" );
}


// Create a note for a given book
function hs_create_book_note($user_id, $book_id, $note_text, $page_number = null, $page_start = null, $page_end = null, $is_public = false)
{
	global $wpdb;

	$wpdb -> insert($wpdb -> prefix . 'hs_book_notes', array(
		'user_id' => intval($user_id),
		'book_id' => intval($book_id),
		'note_text' => sanitize_textarea_field($note_text),
		'page_number' => $page_number ? intval($page_number) : null,
		'page_start' => $page_start ? intval($page_start) : null,
		'page_end' => $page_end ? intval($page_end) : null,
		'is_public' => intval($is_public),
		'date_created' => current_time('mysql'),
		'date_update' => current_time('mysql')
	));

	return $wpdb -> insert_id;
}


// Retrieve all notes for a given book for a given user
function hs_get_user_book_notes($user_id, $book_id)
{
	global $wpdb;

	return $wpdb -> get_results($wpdb -> prepare(
		"SELECT * FROM {$wpdb -> prefix} hs_book_notes
		WHERE user_id = %d AND book_id = %d
		ORDER BY COALESCE(page_number, page_start) ASC, date_created DESC",
		intval($user_id),
		intval($book_id)
	));
}


// Retrieve all public notes for a given book
function hs_get_public_book_notes($book_id)
{
	global $wpdb;

	return $wpdb -> get_results($wpdb -> prepare(
		"SELECT n.*, u.display_name FROM {$wpdb -> prefix} hs_book_notes n
		JOIN {$wpdb -> users} u ON n.user_id = u.ID
		WHERE n.book_id = %d AND n.is_public = 1
		ORDER BY COALESCE(n.page_number, n.page_start) ASC",
		intval($book_id)
	));
}


// Retrieve a single note
function hs_get_book_note($note_id)
{
	global $wpdb;

	return $wpdb -> get_row($wpdb -> prepare(
		"SELECT n.*, u.display_name FROM {$wpdb -> prefix} hs_book_notes n
		JOIN {$wpdb -> users} u ON n.user_id = u.ID
		WHERE n.id = %d",
		intval($note_id)
	));
}


// Update a given note
function hs_update_book_note($note_id, $note_text, $page_number = null, $is_public = false)
{
	global $wpdb;

	$note = hs_get_book_note($note_id);

	if (!$note)
	{
		return false;
	}

	// Check permission
	if ($note -> user_id != get_current_user_id() && !current_user_can('manage_options'))
	{
		// TODO: Maybe a message
		return false;
	}

	return $wpdb -> update(
		$wpdb -> prefix . 'hs_book_notes',
		array(
			'note_text' => sanitize_textarea_field($note_text),
			'page_number' => $page_number ? intval($page_number) : null,
			'is_public' => intval($is_public),
			'date_updated' => current_time('mysql')
		),

		array('id' => intval($note_id))
	);
}


// Delete a given note
function hs_delete_book_note($note_id)
{
	global $wpdb;

	$note = hs_get_book_note($note_id);

	if (!$note)
	{
		return false;
	}

	if ($note -> user_id != get_current_user_id() && !current_user_can('manage_options'))
	{
		return false;
	}

	return $wpdb -> delete($wpdb -> prefix . 'hs_book_notes', array('id' => intval($note_id)));
}


// Book note form
function hs_render_book_note_form($book_id)
{
	if (!is_user_logged_in())
	{
		echo '<p>You need to be logged in to add notes!</p>';
		return;
	}

	$user_id = get_current_user_id();
	$nonce = wp_create_nonce('hs_create_note_' . $book_id);

	ob_start(); ?>

	<form method="post" class="hs-book-note-form">
		<h3>Add a Note</h3>

		<textarea name="note_text" rows="5" required placeholder="Write your note"></textarea><br>

		<label>
			Page Number:
			<input type="number" name="page_number" min="1" />
		</label><br>
		// TODO Sanitize the text field
		<label>
			<input type="checkbox" name="is_public" value="1">
			Make this note public
			</label><br>

			<input type="hidden" name="book_id" value="<?php echo esc_attr($book_id); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
			<button type="submit" name="hs_submit_note">Save Note</button>
		</form>

		<?php
		return ob_get_clean();
}
