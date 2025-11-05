<?php

// Handles reporting, tracking, and resolving inaccuracies in the database.

// This creates the table to store reports about inaccuracies.
function hs_inaccuracies_create_table()
{
        global $wpdb;
        $table_name = $wpdb -> prefix . 'hs_book_reports';
        $charset_collate = $wpdb -> get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                book_id bigint(20) UNSIGNED NOT NULL,
                user_id bigint(20) UNSIGNED NOT NULL,
                report_text text NOT NULL,
                status varchar(20) DEFAULT 'pending' NOT NULL,
                date_submitted datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql);
}



// Adds the Inaccuracies Manager
function hs_inaccuracies_add_admin_page()
{
        add_menu_page(
                'Inaccuracy Reports',
                'Inaccuracy Reports',
                'manage_options',
                'hs-inaccuracy-reports',
                'hs_inaccuracies_admin_page_html',
                'dashicons-flag',
                30
        );
}
add_action('admin_menu', 'hs_inaccuracies_add_admin_page');



function hs_inaccuracies_admin_page_html()
{
        global $wpdb;
        $table_name = $wpdb -> prefix . 'hs_book_reports';


        // Handle inaccuracies
        if (isset($_GET['action'], $_GET['report_id'], $_GET['_wpnonce']))
        {
                $report_id = intval($_GET['report_id']);
                if (wp_verify_nonce($_GET['_wpnonce'], 'hs_report_action_' . $report_id))
                {
                        $report = $wpdb -> get_row($wpdb -> prepare("SELECT * FROM $table_name WHERE id = %d", $report_id));
                        if ($report)
                        {
                                if ($_GET['action'] === 'approve')
                                {
                                        $wpdb -> update($table_name, ['status' => 'approved'], ['id' => $report_id]);
                                        if (function_exists('award_points'))
                                        {
                                                award_points($report -> user_id, 25);
                                        }

					hs_update_approved_reports_count($report -> user_id);
                                }
                                elseif ($_GET['action'] === 'reject')
                                {
                                        $wpdb -> update($table_name, ['status' => 'rejected'], ['id' => $report_id]);
                                }
                        }
                }
        }


        $reports = $wpdb -> get_results("SELECT * FROM $table_name ORDER BY date_submitted DESC");
        ?>

        <div class="wrap">
                <h1>Inaccuracy Reports</h1>
                <table class="wp-list-table widefat fixed striped">
                        <thead>
                                <tr>
                                        <th>Book Title</th>
                                        <th>Submitted By</th>
                                        <th>Details</th>
                                        <th>Date Submitted</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                </tr>
                        </thead>

                        <tbody>
                                <?php if ($reports) : foreach ($reports as $report) : ?>
                                        <tr>
                                                <td><a href="<?php echo esc_url(get_permalink($report -> book_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($report -> book_id)); ?></a></td>
                                                <td><?php echo esc_html(get_userdata($report -> user_id) -> display_name); ?></td>
                                                <td><?php echo esc_html($report -> report_text); ?></td>
                                                <td><?php echo esc_html($report -> date_submitted); ?></td>
                                                <td><span class="hs-report-status-<?php echo esc_attr($report -> status); ?>"><?php echo esc_html(ucfirst($report -> status)); ?></span></td>
                                                <td>
                                                        <?php if ($report -> status === 'pending') : ?>
                                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hs-inaccuracy-reports&action=approve&report_id=' . $report -> id), 'hs_report_action_' . $report -> id)); ?>">Approve</a> |
                                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hs-inaccuracy-reports&action=reject&report_id=' . $report -> id), 'hs_report_action_' . $report -> id)); ?>">Reject</a> |
                                                        <?php endif; ?>

                                                        <a href="<?php echo esc_url(get_edit_post_link($report -> book_id)); ?>" target="_blank">Edit Book</a>
                                                </td>
                                        </tr>
                                <?php endforeach; else : ?>
                                        <tr><td colspan="6">No reports found.</td></tr>
                                <?php endif; ?>
                        </tbody>
                </table>
        </div>
        <style>
                .hs-report-status-approved { color: green; font-weight: bold; }
                .hs-report-status-rejected { color: red; font-weight: bold; }
                .hs-report-status-pending { color: orange; font-weight: bold; }
        </style>
        <?php
}


function hs_ajax_submit_report()
{
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in() || !isset($_POST['book_id'], $_POST['report_text']))
    {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    global $wpdb;
    $table_name = $wpdb -> prefix . 'hs_book_reports';
    
    // Data to be inserted
    $data_to_insert = [
        'book_id' => intval($_POST['book_id']),
        'user_id' => get_current_user_id(),
        'report_text' => sanitize_textarea_field($_POST['report_text']),
        'date_submitted' => current_time('mysql'),
    ];

    $result = $wpdb -> insert($table_name, $data_to_insert, ['%d', '%d', '%s', '%s']);

    if ($result === false) {
        wp_send_json_error([
            'message' => 'Database Insert Failed.',
            'sql_error' => $wpdb->last_error,
            'attempted_query' => $wpdb->last_query,
            'input_data' => $data_to_insert
        ]);
	}


    if ($result)
    {
        wp_send_json_success(['message' => 'Report has been submitted. Thank you for making GRead even better!']);
    }
    else
    {
        // This is the original generic error, which we are now overriding for debug.
        wp_send_json_error(['message' => 'Could not submit report.']); 
    }
}
add_action('wp_ajax_hs_submit_report', 'hs_ajax_submit_report');



function hs_add_report_modal_to_book_pages()
{
	if (!is_singular('book') || !is_user_logged_in())
	{
		return;
	}

	global $post;
	?>

	<div id="hs-report-modal" style="display:none;">
		<div class="hs-modal-content">
			<span id="hs-close-report-modal">&times;</span>
			<h2>Report an Inaccuracy</h2>
			<p>Is there something incorrect about this listing? Report it and get some points if you are correct!</p>
			<textarea id="hs-report-textarea" rows="4" placeholder="What is inaccurate about this listing?"></textarea>
			<button id="hs-submit-report-button" class="hs-button" data-book-id="<?php echo esc_attr($post -> ID); ?>">Submit Report</button>
			<div id="hs-report-feedback" style="margin-top:10px;"></div>
		</div>
	</div>
	<?php
}
add_action('wp_footer', 'hs_add_report_modal_to_book_pages');


function hs_update_approved_reports_count($user_id)
{
	if (!$user_id)
	{
		return;
	}

	global $wpdb;
	$reports_table = $wpdb -> prefix . 'hs_book_reports';

	$approved_count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(id) FROM $reports_table WHERE user_id = %d AND status = 'approved'",
		$user_id
	));

	update_user_meta($user_id, 'hs_approved_reports_count', (int)$approved_count);

	do_action('hs_stats_updated', $user_id);
}
