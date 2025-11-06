jQuery(document).ready(function($)
{
	$('#hs-theme-selector-form').on('submit', function(e)
	{
		e.preventDefault();

		const form = $(this);
		const feedback_div = $('#hs-theme-selector-feedback');
		const submit_button = form.find('input[type="submit"]');

		const selected_theme = form.find('input[name="hs_selected_theme"]:checked').val();
		const nonce = $('#hs_theme_nonce').val();

		console.log('Submitting theme:', selected_theme);
		console.log('Nonce:', nonce);
		console.log('AJAX URL:', hs_theme_ajax.ajaxurl);

		$.ajax({
			url: hs_theme_ajax.ajaxurl,
			type: 'POST',
			data: {
				action: 'hs_save_user_theme',
				hs_theme_nonce: nonce,
				selected_theme: selected_theme
			},

			beforeSend: function()
			{
				submit_button.val('Saving...').prop('disabled', true);
				feedback_div.removeClass('success error').empty();
			},

			success: function(response)
			{
				console.log('Response:', response);
				
				if (response && response.success)
				{
					feedback_div.addClass('success').html('<p>' + response.data.message + '</p>');
					setTimeout(function()
					{
						location.reload();
					}, 1500);
				}
				else if (response && response.data && response.data.message)
				{
					feedback_div.addClass('error').html('<p>' + response.data.message + '</p>');
					submit_button.prop('disabled', false).val('Save Theme');
				}
				else
				{
					feedback_div.addClass('error').html('<p>An unknown error occurred.</p>');
					submit_button.prop('disabled', false).val('Save Theme');
				}
			},

			error: function(xhr, status, error)
			{
				console.error('AJAX Error:', status, error);
				console.error('Response:', xhr.responseText);
				feedback_div.addClass('error').html('<p>Oops! An error occurred. Check the console for details.</p>');
				submit_button.prop('disabled', false).val('Save Theme');
			}
		});
	});
});
