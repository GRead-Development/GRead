<?php

// A shortcode for adding notes
add_shortcode('hs_book_note_form', function($atts)
{
	$att = shortcode_atts(['book_id' => get_the_ID()], $atts);
	return hs_render_book_note_form($atts['book_id']);
});
