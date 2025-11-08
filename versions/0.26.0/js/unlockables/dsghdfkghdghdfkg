jQuery(document).ready(function($)
{
	$('#hs-theme-selector-form').on('submit', function(e)
	{
		e.preventDefault();

		const form = $(this);
		const feedback_div = $('#hs-theme-selector-feedback');
		const submit_button = form.find('input[type="submit"]');

		const selected_theme = form.find('input[name="hs_selected_theme"]:checked').val();

		const data = {
			action: 'hs_save_user_theme',
			hs_theme_nonce: $('#hs_theme_nonce').val(),
			selected_theme: selected_theme
		};

		console.log('Data being submitted:', data);

		$.ajax({
			url: hs_theme_ajax.ajax_url,
			type: 'POST',
			data:
			{
				action: 'hs_save_user_theme',
				hs_theme_nonce: $('#hs_theme_nonce').val(),
				selected_theme: selected_theme
			},

			beforeSend: function()
			{
				submit_button.val('Saving...').prop('disabled', true);
				feedback_div.empty();
			},

			success: function(response)
			{
				if (response.success)
				{
					feedback_div.html('<p class="success">' + response.data.message + '</p>');
					setTimeout(function()
					{
						location.reload();
					}, 1500);
				}

				else
				{
					feedback_div.html('<p class="error">' + response.data.message + '</p>');
					submit_button.prop('disabled', false).val('Save Theme');
				}
			},

			error: function()
			{
				feedback_div.html('<p class="error">Oops! An error occurred!</p>');
				submit_button.prop('disabled', false).val('Save Theme');
			}
		});
	});
});
