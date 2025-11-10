<?php

// Provides a way for administrator to manually recheck a given metric for a given user.

if (!defined('ABSPATH'))
{
	exit;
}


function hs_add_metric_recalc_page()
{
	add_submenu_page(
		'tools.php',
		'Recalculate User Metrics',
		'User Metrics',
		'manage_options',
		'hs-recalc-user-metrics',
		'hs_recalc_user_metrics_page_html',
	);
}
add_action('admin_menu', 'hs_add_metric_recalc_page');


function hs_recalc_user_metrics_page_html()
{
	    if (isset($_GET['recalc_success'])) {
        $user_id = intval($_GET['user_id']);
        $metric = sanitize_text_field($_GET['metric']);
        echo '<div class="notice notice-success"><p>Successfully recalculated <strong>' . esc_html($metric) . '</strong> for user ID ' . $user_id . '</p></div>';
    }
    
    $users = get_users(['orderby' => 'display_name']);
    ?>
    <div class="wrap">
        <h1>Recalculate User Metrics</h1>
        <p>Manually recalculate specific statistics for a user.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('hs_recalc_metric_action', 'hs_recalc_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="user_select">Select User</label></th>
                    <td>
                        <select name="user_id" id="user_select" required>
                            <option value="">-- Choose User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user->ID; ?>">
                                    <?php echo esc_html($user->display_name); ?> (ID: <?php echo $user->ID; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="metric_select">Select Metric</label></th>
                    <td>
                        <select name="metric" id="metric_select" required>
                            <option value="pages_read">Pages Read</option>
                            <option value="books_completed">Books Completed</option>
                            <option value="books_added">Books Added</option>
                            <option value="approved_reports">Approved Reports</option>
                            <option value="points">Total Points</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="hs_recalc_submit" class="button button-primary">
                    Recalculate Metric
                </button>
            </p>
        </form>
    </div>
    <?php
}


function hs_handle_metric_recalculation()
{
	if (!isset($_POST['hs_recalc_submit']) || !isset($_POST['hs_recalc_nonce']) || !wp_verify_nonce($_POST['hs_recalc_nonce'], 'hs_recalc_metric_action') || !current_user_can('manage_options'))
	{
		return;
	}

	$user_id = intval($_PSOT['user_id']);
	$metric = sanitize_key($_POST['metric']);

	global $wpdb;

	switch ($metric)
	{
		case 'pages_read':
			$table = $wpdb -> prefix . 'user_books';
			$total = $wpdb -> get_var($wpdb -> prepare(
				"SELECT SUM(current_page) FROM $table WHERE user_id = %d",
				$user_id
			));

			update_user_meta($user_id, 'hs_total_pages_read', intval($total));
			break;

		case 'books_completed':
			$table = $wpdb -> prefix . 'user_books';
			$count = $wpdb -> get_var($wpdb -> prepare(
				"SELECT COUNT(ub.book_id) FROM $table AS ub
				JOIN {$wpdb -> postmeta} AS pm ON ub.book_id = pm.post_id
				WHERE ub.user_id = %d AND pm.meta_key = 'nop'
				AND ub.current.page >= pm.meta_value AND CAST(pm.meta_value AS UNSIGNED) > 0",
				$user_id
		));

		update_user_meta($user_id, 'hs_completed_books_count', intval($count));
		break;


		case 'books_added':
			$count = count_user_posts($user_id, 'book');
			update_user_meta($user_id, 'hs_books_added_count', $count);
			break;

		case 'approved_reports':
			$table = $wpdb -> prefix . 'hs_book_reports';
			$count = $wpdb -> get_var($wpdb -> prepare(
				"SELECT COUNT(id) FROM $table WHERE user_id = %d AND status = 'approved'",
				$user_id
			));
			update_user_meta($user_id, 'hs_approved_reports_count', intval($count));
			break;


		case 'points':
			require_once plugin_dir_path(__FILE__) . '../pointy_utils.php';
			break;
	}

	do_action('hs_stats_updated, $user_id);

	wp_safe_redirect(admin_url('tools.php?page=hs-recalc-user-metrics&recalc_success=1&user_id=' . $user_id . '&metric=' . $metric));
	exit;
}
add_action('admin_init', 'hs_handle_metric_recalculation');
