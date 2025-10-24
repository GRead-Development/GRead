<?php

// This file provides HotSoup with some security features.


// This is like ABSPATH, but better.
if (!defined('WPINC'))
{
	// Kick em out
	die;
}


// Blocks access to the admin panel if the user is not an admin
function admin_panel_redirect()
{
	// If this is an AJAX request, exit immediately.
	if (wp_doing_ajax())
	{
		return;
	}

	if (is_admin() && !current_user_can('manage_options'))
	{
		wp_redirect(home_url());
		exit;
	}

	// If the user is an administrator, this function never runs, and they can access the panel.
}
add_action('init', 'admin_panel_redirect');
