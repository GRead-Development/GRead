<?php

// This part of HotSoup handles tracking user statistics.


if (!defined('ABSPATH'))
{
	exit;
}


function hs_update_user_stats($user_id)
{
	if(!$user_id)
	{
		return;
	}

	global $wpdb;
	$user_books_table = $wpdb -> prefix . 'user_books';


	// Calculate total pages read for a user
	$total_pages_read = $wpdb -> get_var($wpdb -> prepare(
		"SELECT SUM(current_page) FROM $user_books_table WHERE user_id = %d",
		$user_id
	));
	update_user_meta($user_id, 'hs_total_pages_read', intval($total_pages_read));



	// Calculate how many books a user has completed
	$completed_books_count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(ub.book_id)
		FROM $user_books_table AS ub
		JOIN {$wpdb -> postmeta} AS pm ON ub.book_id = pm.post_id
		WHERE ub.user_id = %d
		AND pm.meta_key = 'nop'
		AND ub.current_page >= pm.meta_value
		AND CAST(pm.meta_value AS UNSIGNED) > 0",
		$user_id
	));
	update_user_meta($user_id, 'hs_completed_books_count', intval($completed_books_count));
}


// Displays user statistics on their profile
function hs_display_user_stats()
{
	$user_id = bp_displayed_user_id();

	if(!$user_id)
	{
		return;
	}

	// Retrieve user's statistics from user meta. This defaults to 0
	$completed_count = get_user_meta($user_id, 'hs_completed_books_count', true) ?: 0;
	$pages_read = get_user_meta($user_id, 'hs_total_pages_read', true) ?: 0;
	?>


	<div>Books Completed: <strong> <?php echo number_format_i18n($completed_count); ?> </strong></div>
	<div>Pages Read: <strong> <?php echo number_format_i18n($pages_read); ?> </strong></div>



	<?php
}
add_action('bp_before_member_header_meta', 'hs_display_user_stats', 11);


function hs_user_stats_styles()
{
	echo '<style>
	.hs-user-stats-widget
	{
		margin: 20px 0;
		padding: 20px;
		background-color: #fff;
		border: 1px solid #e9ecef;
		border-radius: 8px;
		box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
	}

	.hs-user-stats-widget h3
	{
		margin-top: 0;
		margin-bottom: 15px;
		padding-bottom: 10px;
		border-bottom: 1px solid #e9ecef;
		font-size: 16px;
		color: #343a40;
	}

	.hs-user-stats-widget ul
	{
		list-style: none;
		padding: 0;
		color: #495057;
	}
	</style>';
}
//add_action('wp_head', 'hs_user_stats_styles');
