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

	delete_transient('hs_user_stats_' . $user_id);

	do_action('hs_stats_updated', $user_id);
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
	$books_added = get_user_meta($user_id, 'hs_books_added_count', true) ?: 0;
	$approved_reports = get_user_meta($user_id, 'hs_approved_reports_count', true) ?: 0;
	?>


	<div>Books Completed: <strong> <?php echo number_format_i18n($completed_count); ?> </strong></div>
	<div>Pages Read: <strong> <?php echo number_format_i18n($pages_read); ?> </strong></div>
	<div>Books Added: <strong> <?php echo number_format_i18n($books_added); ?></strong><div>
	<div>Approved Reports: <strong><?php echo number_format_i18n($approved_reports); ?></strong></div>


	<?php


	$current_user_id = get_current_user_id();
	if (is_user_logged_in() && $current_user_id != $user_id && function_exists('hs_is_user_blocked'))
	{
		$is_blocked = hs_is_user_blocked($current_user_id, $user_id);

		echo '<div class="hs-user-moderation-actions" style="margin-top: 10px;">';

		if ($is_blocked)
		{
			echo '<button class="hs-button hs-unblock-user" data-user-id="' . esc_attr($user_id) . '">Unblock User</button>';
		}

		else
		{
			echo '<button class="hs-button hs-block-user" data-user-id="' . esc_attr($user_id) . '">Block User</button>';
		}

		echo '<button class="hs-button hs-report-user-modal-open" data-user-id="' . esc_attr($user_id) . '" style="margin-left: 10px; background-color: #d63638;">Report User</button>';
		echo '<span class="hs-moderation-feedback" style="margin-left: 10px; font-style: italic;"></span>';
		echo '</div>';
	}
		?>

	<?php
}
add_action('bp_before_member_header_meta', 'hs_display_user_stats', 11);


function hs_get_user_stats($user_id)
{
	$cache_key = 'hs_user_stats_' . $user_id;
	$stats = get_transient($cache_key);

	if (false === $stats)
	{
		$stats = [
		'completed_count' => get_user_meta($user_id, 'hs_completed_books_count', true) ?: 0,
		'pages_read' => get_user_meta($user_id, 'hs_total_pages_read', true) ?: 0,
		];

	// For now, cache for 5 minutes.
	set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
	}

	return $stats;
}


// Increment the count of books added by a given user
function hs_increment_books_added($user_id)
{
	if (!$user_id)
	{
		return;
	}

	$current_count = (int)get_user_meta($user_id, 'hs_books_added_count', true);
	update_user_meta($user_id, 'hs_books_added_count', $current_count + 1);

	do_action('hs_stats_updated', $user_id);
}


// Decrement the count of books added by a given user
function hs_decrement_books_added($user_id)
{
	if (!$user_id)
	{
		return;
	}

	$current_count = (int)get_user_meta($user_id, 'hs_books_added_count', true);
	update_user_meta($user_id, 'hs_books_added_count', max(0, $current_count - 1));

	do_action('hs_stats_updated', $user_id);
}

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
