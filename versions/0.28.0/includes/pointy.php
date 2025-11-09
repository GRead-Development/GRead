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


// Points deducter
function hs_deduct_points($user_id, $points_deducted = 1)
{
	if (!user_id || $points_deducted <= 0)
	{
		return;
	}

	$meta_key = 'user_points';
	$current_points = (int)get_user_meta($user_id, $meta_key, true);

	$new_points = $current_points - $points_deducted;

	if ($new_points < 0)
	{
		$new_points = 0;
	}

	update_user_meta($user_id, $meta_key, $new_points);

	do_action('hs_points_updated', $user_id);
}


// Deduct points when a book is removed from the database
function hs_deduct_points_book($post_id)
{
	$post = get_post($post_id);

	if ($post && $post -> post_type === 'book' && $post -> post_status === 'publish')
	{
		$author_id = $post -> post_author;
		$points_deducted = 10;
		hs_deduct_points($author_id, $points_deducted);
	}
}
add_action('before_delete_post', 'hs_deduct_points_book', 10, 1);


// Deduct points for a deleted comment
function hs_deduct_points_comment($comment_id)
{
	$comment = get_comment($comment_id);

	if ($comment && $comment -> user_id)
	{
		if ($comment -> comment_approved == '1')
		{
			$user_id = $comment -> user_id;
			$points_deducted = 2;
			hs_deduct_points($user_id, $points_deducted);
		}
	}
}
add_action('delete_comment', 'hs_deduct_points_comment', 10, 1);


// Deduct points for a deleted post
function hs_deduct_points_post($args)
{
	if (empty($args['id']) || !function_exists('bp_activity_get_specific'))
	{
		return;
	}

	$activity_id = $args['id'];

	$activity = bp_activity_get_specific(['activity_ids' => $activity_id, 'show_hidden' => true]);

	if (!empty($activity['activities'][0]))
	{
		$activity_obj = $activity['activities'][0];
		$user_id = $activity_obj -> user_id;

		if ($activity_obj -> type === 'activity_update')
		{
			$points_deducted = 2;
			hs_deduct_points($user_id, $points_deducted);
		}
	}
}

?>
