<?php

// This is responsible for providing the users with a form to submit books to the database.


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

        <div id="import-message-area"></div>

        <form id="book-import-form" method="post">
            <input type="hidden" name="action" value="import_book_ajax">

            <?php wp_nonce_field('import_book_nonce'); ?>

            <p>
                <label for="isbn_to_import"><strong>ISBN</strong></label><br>
                <input type="text" id="isbn_to_import" name="isbn_to_import" required>
            </p>

            <p><input type="submit" name="submit" id="import-submit-btn" class="button button-primary" value="Import"></p>
        </form>
    </div>

    <script type="text/javascript">
    // Use jQuery in a safe wrapper
    jQuery(document).ready(function($) {

        var $form = $('#book-import-form');
        var $messageArea = $('#import-message-area');
        var $submitButton = $('#import-submit-btn');

        // Function to generate and display the message HTML
        function displayMessage(type, message, details = '') {
            var messageHtml = '';
            var fullMessage = message;

            if (type === 'error' && details) {
                fullMessage += ' Details: ' + details;
            }

            if (type === 'success') {
                messageHtml = '<div class="notice notice-success"><p>' + fullMessage + '</p></div>';
            } else if (type === 'exists' || type === 'warning') {
                messageHtml = '<div class="notice notice-warning"><p>' + fullMessage + '</p></div>';
            } else if (type === 'error') {
                messageHtml = '<div class="notice notice-error"><p>' + fullMessage + '</p></div>';
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
                // WordPress AJAX endpoint
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                data: $form.serialize(),

                success: function(response) {
                    // Check if WordPress returned a successful JSON response
                    if (response.success) {
                        displayMessage(response.data.type, response.data.message);
                        if (response.data.type === 'success') {
                            // Optional: Clear the ISBN field on success
                            $('#isbn_to_import').val('');
                        }
                    } else {
                        // Handle wp_send_json_error responses (response.success will be false)
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
                    $submitButton.val('Import').prop('disabled', false); // Re-enable button
                }
            });
        });
    });
    </script>

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
