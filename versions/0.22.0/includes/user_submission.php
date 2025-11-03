<?php

// This is responsible for providing the users with a form to submit books to the database.


function user_import_book_shortcode( $atts = [], $content = null )
{
	if (!is_user_logged_in() || !current_user_can('publish_posts'))
	{
		return '<p>You are not allowed to use this feature.</p>';
	}


	ob_start();
	?>

	<div class="book-importer-form">
		<h2>Add Book</h2>
		<p>If you cannot find the book that you are reading, you can add it to the database and get credit for it! Enter the ISBN of the book, press the "Import" button, and the importer will do the rest.</p>

		<?php

		if (isset($_GET['message']))
		{
			$message = sanitize_text_field($_GET['message']);
			$details = isset($_GET['details']) ? esc_html(urldecode($_GET['details'])) : '';

			if ($message === 'success')
			{
				echo '<div class="notice notice-success"><p>The book has been successfully imported! Thank you for making GRead even better!</p></div>';
			}

			elseif ($message === 'exists')
			{
				echo '<div class="notice notice-warning"><p>Oops! There is already a book with that ISBN in the database!</p></div>';
			}

			else
			{
				echo '<div class="notice notice-error"><p>Oops! An error has occurred: ' . $details . '</p></div>';
			}
		}
		?>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="import_book">

			<?php wp_nonce_field('import_book_nonce'); ?>

			<input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">

			<p>
				<label for="isbn_to_import"><strong>ISBN</strong></label><br>
				<input type="text" id="isbn_to_import" name="isbn_to_import" required>
			</p>

			<p><input type="submit" name="submit" class="button button-primary" value="Import"></p>
		</form>
	</div>

	<?php
	return ob_get_clean();
}

add_shortcode('book_importer_form', 'user_import_book_shortcode');



function user_submission_modal_shortcode( $atts = [], $content = null)
{
	$form_html = do_shortcode('[book_importer_form]');

	ob_start();
	?>

	<button id="ol-open-modal-btn" class="button button-primary">Add Book</button>

	<div id="ol-importer-modal" class="ol-modal">

		<div class="ol-modal-content">
			<span class="ol-modal-close">&times;</span>
			<?php echo $form_html; ?>
		</div>

	</div>

	<?php
	return ob_get_clean();
}

add_shortcode('book_importer_modal', 'user_submission_modal_shortcode');
