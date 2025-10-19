<?php

// Pointy is responsible for awarding points, and displaying the points that a user has earned.



function points_for_post($post_ID, $post)
{
	$author_id = $post -> post_author;
	$meta_key = 'user_points';
	$current_points = get_user_meta($author_id, $meta_key, true);

	// Default is 0
	if (empty($current_points))
	{
		$current_points = 0;
	}

	// Points to award for a post
	$points_awarded = 10;

	// Update the total
	$new_points = (int)$current_points + $points_awarded;

	// Update the points for a given user, in the database
	update_user_meta($author_id, $meta_key, $new_points);
}

add_action('publish_book', 'points_for_post', 10, 2);


// Displays the number of points that have been earned by a user
function display_points()
{
	$displayed_user = bp_displayed_user_id();

	// Make sure the user exists
	if (!$displayed_user)
	{
		return;
	}

	$meta_key = 'user_points';
	$total_points = get_user_meta($displayed_user, $meta_key, true);

	// Default is 0
	if (empty($total_points))
	{
		$total_points = 0;
	}

	// A simple display for points
	echo '<div class="user-points">Points: <strong>' . esc_html($total_points) . '</strong></div>';
}

add_action('bp_before_member_header_meta', 'display_points');



function points_for_comment($comment_ID, $comment_approved, $commentdata)
{
	// Specifically to prevent Vlad from getting points :)
	if ($comment_approved === 1)
	{
		$user_id = $commentdata['user_id'];

		if ($user_id)
		{
			award_points($user_id, 2);
		}
	}
}

add_action('comment_post', 'points_for_comment', 10, 3);

// Awards points
function award_points($user_id, $points_awarded = 1)
{
	$meta_key = 'user_points';
	$current_points = (int)get_user_meta($user_id, $meta_key, true);
	$new_points = $current_points + $points_awarded;
	update_user_meta($user_id, $meta_key, $new_points);
}


function points_for_bp_activity($content, $user_id, $activity_id)
{
	// Two points for each activity posted
	award_points($user_id, 2);
}
add_action('bp_activity_posted_update', 'points_for_bp_activity', 10, 3);


?>
