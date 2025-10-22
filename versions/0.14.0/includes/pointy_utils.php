<?php

// This provides administrators with different utilities for managing Pointy.

if (!defined('ABSPATH'))
{
	exit;
}


function pointy_tools_page()
{
	add_submenu_page(
		'tools.php',
		'Pointy Tools',
		'Pointy Tools',
		'manage_options',
		'pointy-tools',
		'pointy_tools_page_html'
	);
}
add_action('admin_menu', 'pointy_tools_page');


// The HTML for the Pointy Tools page
function pointy_tools_page_html()
{
	if (isset($_GET['recal_status']))
	{
		$count = isset($_GET['count']) ? intval($_GET['count']) : 0;

		if ($_GET['recal_status'] === 'success')
		{
			echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> Points have been recalculated, successfully. ' . $count . ' users were reviewed.</p></div>';
		}
	}
	?>

// TODO: Recalculate for a given user
	<div class="wrap">
		<h1>Pointy Management Tools</h1>
		<p>Management tools for Pointy!</p>

		<h2>Recalculate points for all users</h2>
		<p>This will reset all users' points to zero and recalculate their points.</p>
		<p><strong>Warning:</strong> This is a demanding task for the server, and this will affect <strong>every user</strong> on the site.</p>

		<form method="post" action="">
			</php wp_nonce_field('pointy_recalculate_all', 'pointy_recalculate_nonce'); ?>
			<p>
				<button type="submit" name="pointy_recalculate_all_points" class="button button-primary" onclick="return confirm('Are you sure that you want to recalculate points for all users?');">Recalculate Points</button>
			</p>
		</form>
	</div>
	<?php
}


function pointy_recalculation_handler()
{
	if (isset($_POST['pointy_recalculate_all']) && isset($_POST['pointy_recalculate_nonce']) && wp_verify_nonce($_POST['pointy_recalculate_nonce'], 'pointy_recalculate_all'))
	{
		global $wpdb;
		$meta_key = 'user_points';

		$wpdb -> delete($wpdb -> usermeta, ['meta_key' => $meta_key]);

		$users = get_users(['fields' => 'ID']);


		foreach($users as $user_id)
		{
			$total_points = 0;

			$book_count += count_user_posts($user_id, 'book');
			$total_points += $book_count * 10;


			$comment_count = get_comments(['user_id' => $user_id, 'status' => 'approve', 'count' => true]);
			$total_points += $comment_count * 2;

			if (function_exists('bp_activity_get_specific'))
			{
				$activity_count = bp_activity_get_total_mention_count_for_user($user_id);
				$total_points += $activity_count * 2;
			}

			if ($total_points > 0)
			{
				update_user_meta($user_id, $meta_key, $total_points);
			}
		}

		wp_safe_redirect(admin_url('tools.php?page=pointy-tools&recal_status=success&count=' . count($users)));
		exit;
	}
}
add_action('admin_init', 'pointy_recalculation_handler');




// TODO:
// Add log of points awarded to each user, when, and for what. Includes points removed
// Create variables for the value of each activity so that we can change points awarded and not have to rewrite the tools

