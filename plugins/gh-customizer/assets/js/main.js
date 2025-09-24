jQuery(document).ready(function($)
{
	// Initializes WP's color picker
	$('.gr-color-selector').wpColorPicker();


	// Wrangle the media uploader (background images)
	$('#gr-upload-bg-img').click(function(e)
	{
		e.preventDefault();

		var img_frame;

		if (img_frame)
		{
			img_frame.open();
			return;
		}

		img_frame = wp.media(
		{
			title: 'Select background image',
			multiple: false,
			library:
			{
				type: 'image'
			}
		});

		image_frame.on('select', function()
		{
			var attachment = img_frame.state().get('selection').first().toJSON();
			$('#gr-bg-img-url').val(attachment.url);
			$('#gr-bg-img-preview').html('<img src="' + attachment.url + '" style="max-width:200px; margin-top:10px; border: 1px solid #ddd; padding: 5px;">');
		});

		img_frame.open();
	});
});
