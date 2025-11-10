<?php

// Removes the invitations tab on member profiles
function hs_hide_invitations_tab()
{
	if (function_exists('bp_core_remove_nav_item'))
	{
		bp_core_remove_nav_item('invitations');
	}
}
add_action('bp_setup_nav', 'hs_hide_invitations_tab');
