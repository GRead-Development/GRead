// Reading sessions
jQuery(document).ready(function($)
{
	function create_session_modal()
	{
		if ($('#hs-session-modal').length > 0)
		{
			return;
		}

	const modal_html = `
		           <div id="hs-session-modal" style="display:none;">
                <div class="hs-modal-content">
                    <span class="hs-modal-close">&times;</span>
                    <h3>Create Reading Session</h3>
                    <p>Invite friends to read this book together!</p>
                    <div class="hs-modal-field">
                        <label for="hs-invite-users">Invite Users (optional):</label>
                        <input type="text" id="hs-invite-users" placeholder="Enter user IDs, comma-separated (e.g., 5, 12, 23)">
                        <p class="description">Leave blank to start reading alone and invite others later</p>
                    </div>
                    <div class="hs-modal-actions">
                        <button id="hs-confirm-session" class="hs-button">Create Session</button>
                        <button id="hs-cancel-session" class="hs-button" style="background-color: #999;">Cancel</button>
                    </div>
                    <span id="hs-session-feedback"></span>
                </div>
            </div>
        `;

	$('body').append(modal_html);
	}


	// Handles clicking the "Read Together" button
	$(document).on('click', '.hs-create-session', function(e)
	{
		e.preventDefault();

		const button = $(this);
		const book_id = button.data('book-id');

		create_session_modal();


		$('#hs-invite-users').val('');
		$('#hs-session-feedback').text('');


		$('#hs-confirm-session').data('book-id', book_id);

		$('#hs-session-modal').fadeIn(200);
	});


	// Close the modal if user clicks "x"
	$(document).on('click', '.hs-modal-close', function()
	{
		$('#hs-session-modal').fadeOut(200);
	});


	// Close the modal if user clicks the cancel button
	$(document).on('click', '#hs-cancel-session', function()
	{
		$('#hs-session-modal').fadeOut(200);
	});


	// Close the modal if the user clicks outside of the modal
	$(document).on('click', '#hs-session-modal', function(e)
	{
		if (e.target.id === 'hs-session-modal')
		{
			$(this).fadeOut(200);
		}
	});

	// Handles session creation
	$(document).on('click', '#hs-confirm-session', function(e)
	{
		e.preventDefault();

		const button = $(this);
		const book_id = button.data('book-id');
		const invite_input = $('#hs-invite-users').val().trim();
		const feedback = $('#hs-session-feedback');


		// Parse user IDs
		let user_ids = [];

		if (invite_input)
		{
			user_ids = invite_input.split(',').map(function(id)
			{
				return id.trim();
			}).filter(function(id)
			{
				return id !== '';
			});
		}


		$.ajax({
			url: hs_ajax.ajax_url,
			type: 'POST',
			data:
			{
				action: 'hs_create_reading_session',
				nonce: hs_ajax.nonce,
				book_id: book_id,
				invited_users: user_ids
			},

			beforeSend: function()
			{
				button.text('Creating session...').prop('disabled', true);
				$('#hs-cancel-session').prop('disabled', true);
				feedback.text('Please wait...').css('color', '#666');
			},

			success: function(response)
			{
				if (response.success)
				{
					feedback.text('Session created.').css('color', 'green');

					setTimeout(function()
					{
						$('#hs-session-modal').fadeOut(200);
						// Reset button states
						button.text('Create Session').prop('disabled', false);
						$('#hs-cancel-session').prop('disabled', false);

						// Show the user a success message
						alert('Your session has been created successfully. You can now invite other members to read with you!');

						// Reload the page in order to display the updated UI
						location.reload();
					}, 1500);
				}

				else
				{
					feedback.text('ERROR: ' + response.data.message).css('color', 'red');
					button.text('Create Session').prop('disabled', false);
					$('#hs-cancel-session').prop('disabled', false);
				}
			},

			error: function()
			{
				feedback.text('A connection error has occurred!').css('color', 'red');
				button.text('Create Session').prop('disabled', false);
				$('#hs-cancel-session').prop('disabled', false);
			}
		});
	});

});
