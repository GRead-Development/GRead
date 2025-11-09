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

	// Check for the recalculation log transient
	$recal_log = get_transient('pointy_recal_log');
	if ($recal_log)
	{
		// Delete the transient so it only shows once
		delete_transient('pointy_recal_log');
		
		echo '<div class="wrap">'; // Wrap the log in its own 'wrap' div for styling
		echo '<h2>Recalculation Log</h2>';
		echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">';
		echo '<thead><tr><th style="width: 25%;">User</th><th>Book Points</th><th>Comment Points</th><th>Activity Points</th><th>Report Points</th><th>Total Points</th></tr></thead>';
		echo '<tbody>';

		foreach ($recal_log as $log_entry)
		{
			echo '<tr>';
			echo '<td>' . esc_html($log_entry['name']) . ' (ID: ' . esc_html($log_entry['id']) . ')</td>';
			echo '<td>' . number_format_i18n($log_entry['breakdown']['books']) . '</td>';
			echo '<td>' . number_format_i18n($log_entry['breakdown']['comments']) . '</td>';
			echo '<td>' . number_format_i18n($log_entry['breakdown']['activity']) . '</td>';
			echo '<td>' . number_format_i18n($log_entry['breakdown']['reports']) . '</td>';
			echo '<td>' . number_format_i18n($log_entry['breakdown']['reviews'] ?? 0) . '</td>';
			echo '<td><strong>' . number_format_i18n($log_entry['total']) . '</strong></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>'; // Close wrap
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
			<?php wp_nonce_field('pointy_recalculate_all', 'pointy_recalculate_nonce'); ?>
			<p>
				<button type="submit" name="pointy_recalculate_all_points" class="button button-primary" onclick="return confirm('Are you sure that you want to recalculate points for all users?');">Recalculate Points</button>
			</p>
		</form>
	</div>
	<?php
}


function pointy_recalculation_handler()
{
	// Correctly check for the button name 'pointy_recalculate_all_points'
	if (isset($_POST['pointy_recalculate_all_points']) && isset($_POST['pointy_recalculate_nonce']) && wp_verify_nonce($_POST['pointy_recalculate_nonce'], 'pointy_recalculate_all'))
	{
		global $wpdb;
		
		// Define meta keys
		$meta_key_total = 'user_points';
		$meta_key_breakdown = 'user_points_breakdown';

		// Define point values
		$points_for_book = 10;
		$points_for_comment = 2;
		$points_for_activity = 2;
		$points_for_report = 25; // Points for approved inaccuracy reports

		// Review points
		$points_for_review_rating = 5;
		$points_for_review_text = 20;
		$points_for_review_both = 25;

		// Define table names
		$usermeta_table = $wpdb->usermeta;
		$reports_table = $wpdb->prefix . 'hs_book_reports';
		$activity_table = $wpdb->prefix . 'bp_activity';

		// Clear all existing point meta keys before recalculating
		$wpdb -> delete($usermeta_table, ['meta_key' => $meta_key_total]);
		$wpdb -> delete($usermeta_table, ['meta_key' => $meta_key_breakdown]);

		$users = get_users(['fields' => 'ID']);
		$full_breakdown_log = []; // Initialize log array

		foreach($users as $user_id)
		{
			// Explicitly initialize all counts and points to 0 for each user
			$total_points = 0;
			$book_count = 0;
			$book_points = 0;
			$comment_count = 0;
			$comment_points = 0;
			$activity_count = 0;
			$activity_points = 0;
			$report_count = 0;
			$report_points = 0;
			$review_points = 0;

			// This array will store the log/breakdown
			$points_breakdown = [
				'books' => 0,
				'comments' => 0,
				'activity' => 0,
				'reports' => 0,
				'reviews' => 0,
			];

			// --- Calculate Book Points ---
			$book_count = count_user_posts($user_id, 'book'); // 'book' post type, 'publish' status by default
			$book_points = $book_count * $points_for_book;
			$points_breakdown['books'] = $book_points;
			
			// --- Calculate Comment Points (WP API) ---
			// Reverted to the original get_comments() function from your file
			$comment_count = get_comments(['user_id' => $user_id, 'status' => 'approve', 'count' => true]);
			$comment_points = $comment_count * $points_for_comment;
			$points_breakdown['comments'] = $comment_points;

			// --- Calculate BuddyPress Activity Points (Direct SQL Query) ---
			if (function_exists('bp_activity_get'))
			{
				$activity_count = $wpdb->get_var( $wpdb->prepare( 
					"SELECT COUNT(id) FROM {$activity_table} WHERE user_id = %d AND type = 'activity_update'", 
					$user_id 
				) );
				$activity_points = $activity_count * $points_for_activity;
				$points_breakdown['activity'] = $activity_points;
			}
			
			// --- Calculate Inaccuracy Report Points (Direct SQL Query) ---
			$report_count = $wpdb->get_var( $wpdb->prepare( 
				"SELECT COUNT(id) FROM {$reports_table} WHERE user_id = %d AND status = 'approved'", 
				$user_id 
			) );
			$report_points = $report_count * $points_for_report;
			$points_breakdown['reports'] = $report_points;


			// Calculate points from reviews
			$user_reviews = $wpdb -> get_results($wpdb -> prepare(
				"SELECT rating, review_text FROM $reviews_table WHERE user_id = %d", $user_id
			));

			foreach ($user_reviews as $review)
			{
				$has_rating = !is_null($review -> rating) && $review -> rating >= 0.0;
				$has_text = !empty(trim($review -> review_text));

				if ($has_rating && $has_text)
				{
					$review_points += $points_for_review_both;
				}

				elseif($has_rating)
				{
					$review_points += points_for_review_rating;
				}

				elseif ($has_text)
				{
					$review_points += $points_for_review_text;
				}
			}

			$points_breakdown['reviews'] = $review_points;

			// --- Sum total points from the breakdown ---
			$total_points = array_sum($points_breakdown);
			
			// Save the total and the breakdown log
			if ($total_points > 0)
			{
				update_user_meta($user_id, $meta_key_total, $total_points);
				update_user_meta($user_id, $meta_key_breakdown, $points_breakdown); // This array will be serialized by WordPress
			}

			// Add this user's data to the full log
			$user_info = get_userdata($user_id);
			if ($user_info) 
			{
				$full_breakdown_log[] = [
					'name'      => $user_info->display_name,
					'id'        => $user_id,
					'breakdown' => $points_breakdown,
					'total'     => $total_points
				];
			}
		}

		// Save the complete log to a transient (temporary cache)
		set_transient('pointy_recal_log', $full_breakdown_log, 60 * 5); // Store for 5 minutes

		wp_safe_redirect(admin_url('tools.php?page=pointy-tools&recal_status=success&count=' . count($users)));
		exit;
	}
}
add_action('admin_init', 'pointy_recalculation_handler');




// TODO:
// Add log of points awarded to each user, when, and for
