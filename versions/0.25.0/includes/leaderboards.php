<?php

function hs_submission_leaderboard_shortcode($atts)
{
	// By default the limit is 10.
	// TODO: Test 100
	$atts = shortcode_atts([
		'limit' => 10,
	], $atts, 'book_submission_leaderboard');


	// Make sure that the limit is an integer
	$limit = intval($atts['limit']);


	global $wpdb;


	// Run an SQL query (this should be an efficient way of doing this)
	// TODO: Make this something that the server does, not the user (to prevent users from making too many requests on the server)

	/*
		Explanation of the query:

		From $wpdb's posts, select the post_author column, count the occurrence of each ID found. Store this part of the query as "submission_count".
		Only select posts that are of the 'book' type, and are published.
		Group selection by post_author.
		Order the selection by submission_count, in descending order.
		Set the limit of the selection to that of the $limit variable.
	*/

	$query = $wpdb -> prepare
	(
		"SELECT post_author, COUNT(ID) as submission_count
		FROM {$wpdb -> posts}
		WHERE post_type = 'book' AND post_status = 'publish'
		GROUP BY post_author
		ORDER BY submission_count DESC
		LIMIT %d",
		$limit
	);

	// This variable stores the result of the previous query
	$top_contributors =  $wpdb -> get_results($query);

	// This message is shown if nothing is found by the aforementioned query. This should never be shown, so the message will assume there is an error with the database.
	if (empty($top_contributors))
	{
		return '<p>Oops! There is an issue with the server. Check back later!</p>';
	}


	// Start constructing the HTML part of this shortcode
	$output = '<div class="hs-submission-leaderboard-container">';
	$output .= '<h3>Top Contributors</h3>';
	$output .= '<ol class="hs-leaderboard-list">';


	// Loop through the results in order to put together the list items
	foreach ($top_contributors as $contributor)
	{
		$user_id = $contributor -> post_author;
		$user_info = get_userdata($user_id);
		$count = $contributor -> submission_count;

		$li_class = '';
		if ($user_id == $current_user_id)
		{
			$current_user_on_board = true;
			$li_class = ' hs-current-user';
		}


		// Make sure there is data before trying to display anything.
		if ($user_info && function_exists('bp_core_get_user_domain'))
		{
			$profile_url = bp_core_get_user_domain($user_id);
			$display_name = '<a href="' . esc_url($profile_url) . '">' . esc_html($user_info -> display_name) . '</a>';
			$books_label = _n('book', 'books', $count, 'hotsoup');

			$output .= '<li>';
			$output .= '<span class="user-name">' . $display_name . '</span>';
			$output .= '<span class="submission-count">' . $count . ' ' . $books_label . '</span>';
			$output .= '</li>';
		}
	}


	$output .= '</ol>';

	// Add the current user's rank on the leaderboard if they are logged in and do not place
	if ($current_user_id > 0 && !current_user_on_board)
	{
		$user_count = $wpdb -> get_var($wpdb -> prepare(
			"SELECT COUNT(ID) FROM ($wpdb -> posts) WHERE post_type = 'book' AND post_status = 'publish' AND post_author = %d",
			$current_user_id
		));

		if ($user_count > 0)
		{
			$sub_query = "SELECT COUNT(ID) as submission_count FROM {$wpdb -> posts} WHERE post_type = 'book' AND 'post_status' = 'publish' GROUP BY post_author HAVING submission_count > %d";
			$higher_ranks = $wpdb -> get_var($wpdb -> prepare($sub_query, $user_count));

			$user_rank = intval($higher_ranks) + 1;
			$books_label = _n('book', 'books', $user_count, 'hotsoup');

			$output .= '<div class="hs-leaderboards-user-rank">';
			$output .= '<span>Your Rank: #' . $user_rank . ' with ' . $user_count . ' ' . $books_label . '</span>';
			$output .= '</div>';
		}

		else
		{
			$output .= '<div class="hs-leaderboard-user-rank">';
			$output .= '<span>You are not on the leaderboard yet! Add some books and get on!</span>';
			$output .= '</div>';
		}
	}

	$output .= '</div>';

	return $output;
}
// Add the shortcode
add_shortcode('book_submission_leaderboard', 'hs_submission_leaderboard_shortcode');


// A leaderboard for points
function hs_points_leaderboard_shortcode($atts)
{
	$atts = shortcode_atts(['limit' => 15], $atts, 'points_leaderboard');
	$limit = intval($atts['limit']);

	global $wpdb;
	$current_user_id = get_current_user_id();
	$current_user_on_board = false;

	$meta_key = 'user_points';

	$query = $wpdb -> prepare(
		"SELECT user_id, meta_value as total_points
		FROM {$wpdb -> usermeta}
		WHERE meta_key = %s
		ORDER BY CAST(meta_value AS UNSIGNED) DESC
		LIMIT %d",
		$meta_key,
		$limit
	);

	$top_users = $wpdb -> get_results($query);

	if (empty($top_users))
	{
		return '<p>Something is wrong! Could not retrieve points data!</p>';
	}

	$output = '<div class="hs-submission-leaderboard-container">';
	$output .= '<h3>Points Leaderboard</h3>';
	$output .= '<ol class="hs-leaderboard-list">';

	foreach ($top_users as $user)
	{
		

		$user_info = get_userdata($user -> user_id);

		if ($user_info)
		{
			$profile_url = bp_core_get_user_domain($user -> user_id);
			$display_name = '<a href="' . esc_url($profile_url) . '">' . esc_html($user_info -> display_name) . '</a>';
			$count = (int)$user -> total_points;
			$points_label = _n('point', 'points', $count, 'hotsoup');

			$output .= '<li>';
			$output .= '<span class="user-name">' . $display_name . '</span>';
			$output .= '<span class="submission-count">' . number_format($count) . ' ' . $points_label . '</span>';
			$output .= '</li>';
		}
	}

	$output .= '</ol></div>';
	return $output;
}
add_shortcode('points_leaderboard', 'hs_points_leaderboard_shortcode');

// TODO: Fix this garbage so it calculates read pages, not pages in books submitted
/*
// Pages read leaderboard
function hs_pages_read_leaderboard_shortcode($atts)
{
	$atts = shortcode_atts(['limit' => 15], $atts, 'page_read_leaderboard');
	$limit = intval($atts['limit']);
	global $wpdb;

	$query = $wpdb -> prepare(
		"SELECT p.post_author, SUM(pm.meta_value) as total_pages
		FROM {$wpdb -> posts} p
		JOIN {$wpdb -> postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'book'
		AND p.post_status = 'publish'
		AND pm.meta_key = 'nop'
		GROUP BY p.post_author
		ORDER BY total_pages DESC
		LIMIT %d",
		$limit
	);

	$top_readers = $wpdb -> get_results($query);

	if (empty($top_readers))
	{
		return '<p>Oops! Could not retrieve data about pages read!</p>';
	}


	$output = '<div class="hs-submission-leaderboard-container">';
	$output .= '<h3>Pages Read Leaderboard</h3>';
	$output .= '<ol class="hs-leaderboard-list">';


	foreach ($top_readers as $reader)
	{
		$user_info = get_userdata($reader -> post_author);

		if ($user_info)
		{
			$count = (int)$reader -> total_pages;
			$display_name = esc_html($user_info -> display_name);
			$pages_label = _n('page', 'pages', $count, 'hotsoup');

			$output .= '<li>';
			$output .= '<span class="user-name">' . $display_name . '</span>';
			$output .= '<span class="submission-count">' . number_format($count) . ' ' . $pages_label . '</span>';
			$output .= '</li>';
		}
	}

	$output .= '</ol></div>';
	return $output;
}
add_shortcode('pages_read_leaderboard', 'hs_pages_read_leaderboard_shortcode');
*/


// Some styling stuff for the leaderboard shortcode
function hs_leaderboard_styles()
{
	echo '<style>
		.hs-submission-leaderboard-container
		{
			border: 1px solid #e0e0e0;
			padding: 15px 25px;
			border-radius: 5px;
			background-color: #f9f9f9;
		}

		.hs-submission-leaderboard-container h3
		{
			margin-top: 0;
			border-bottom: 2px solid #e0e0e0;
			padding-bottom: 10px;
		}

		.hs-leaderboard-list
		{
			list-style-type: decimal;
			margin-left: 20px;
			padding-left: 20px;
		}

		.hs-leaderboard-list li
		{
			padding: 8px 0;
			display: flex;
			justify-content: space-between;
			border-bottom: 1px solid #eee;
		}

		.hs-leaderboard-list li:last-child
		{
			border-bottom: none;
		}

		.hs-leaderboard-list .user-name
		{
			font-weight: bold;
		}
		
		.hs-site-stats-container
		{
			border: 1px solid #e0e0e0;
			padding: 15px 25px;
			border-radius: 5px;
			background-color: #f9f9f9;
		}
	</style>';
}
// Add the action
add_action('wp_head', 'hs_leaderboard_styles');
