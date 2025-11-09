<?php

// Provides user-moderation features

if (!defined('ABSPATH'))
{
	exit;
}

// Databases
function hs_moderation_create_tables()
{
	global $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$charset_collate = $wpdb -> get_charset_collate();

	// Block/mute relationships
	$relationships_table = $wpdb -> prefix . 'hs_user_relationships';
	$sql_relationships = "CREATE TABLE $relationships_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		actor_user_id bigint(20) UNSIGNED NOT NULL,
		target_user_id bigint(20) UNSIGNED NOT NULL,
		relationship_type VARCHAR(20) NOT NULL,
		date_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY actor_target_type (actor_user_id, target_user_id, relationship_type),
		KEY actor_user_id (actor_user_id),
		KEY target_user_id (target_user_id)
	) $charset_collate;";
	dbDelta($sql_relationships);


	// Table for reports
	$reports_table = $wpdb -> prefix . 'hs_user_reports';
	$sql_reports = "CREATE TABLE $reports_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		reporting_user_id bigint(20) UNSIGNED NOT NULL,
		reported_user_id bigint(20) UNSIGNED NOT NULL,
		report_reason TEXT NOT NULL,
		status VARCHAR(20) DEFAULT 'pending' NOT NULL,
		date_submitted datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		admin_notes TEXT,
		PRIMARY KEY (id),
		KEY reporting_user_id (reporting_user_id),
		KEY reported_user_id (reported_user_id),
		KEY status (status)
	) $charset_collate;";
	dbDelta($sql_reports);
}



// Block a user
function hs_block_user($actor_id, $target_id)
{
	if ($actor_id == $target_id) return false;
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';


	$result = $wpdb -> replace(
		$table_name,
		[
			'actor_user_id' => $actor_id,
			'target_user_id' => $target_id,
			'relationship_type' => 'block',
			'date_created' => current_time('mysql'),
		],
		['%d', '%d', '%s', '%s']
	);

	return $result !== false;
}


function hs_mute_user($actor_id, $target_id)
{
	if ($actor_id == $target_id) return false;
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';

	$result = $wpdb -> replace(
		$table_name,
		[
			'actor_user_id' => $actor_id,
			'target_user_id' => $target_id,
			'relationship_type' => 'mute',
			'date_created' => current_time('mysql'),
		],
		['%d', '%d', '%s', '%s']
	);
	return $result !== false;
}



function hs_unblock_user($actor_id, $target_id)
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';
	$result = $wpdb -> delete(
		$table_name,
		[
			'actor_user_id' => $actor_id,
			'target_user_id' => $target_id,
			'relationship_type' => 'block',
		],
		['%d', '%d', '%s']
	);

	return $result !== false;
}


function hs_unmute_user($actor_id, $target_id)
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';
	$result = $wpdb -> delete(
		$table_name,
		[
			'actor_user_id' => $actor_id,
			'target_user_id' => $target_id,
			'relationship_type' => 'mute',
		],
		['%d', '%d', '%s']
	);

	return $result !== false;
}


function hs_report_user($reporter_id, $reported_id, $reason)
{
	if ($reporter_id == $reported_id || empty(trim($reason))) return false;

	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_reports';

	$result = $wpdb -> insert(
		$table_name,
		[
			'reporting_user_id' => $reporter_id,
			'reported_user_id' => $reported_id,
			'report_reason' => sanitize_textarea_field($reason),
			'status' => 'pending',
			'date_submitted' => current_time('mysql'),
		],
		['%d', '%d', '%s', '%s', '%s']
	);

	return $result !== false;
}


function hs_get_blocked_users($actor_id)
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';
	$results =  $wpdb -> get_col($wpdb -> prepare(
		"SELECT target_user_id FROM $table_name WHERE actor_user_id = %d AND relationship_type = 'block'",
		$actor_id
	));
	
	return array_map('intval', $results);
}


// Retrieve list of muted users for a given actor
function hs_get_muted_users($actor_id)
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';

	$results = $wpdb -> get_col($wpdb -> prepare(
		"SELECT target_user_id FROM $table_name WHERE actor_user_id = %d AND relationship_type = 'mute'",
		$actor_id
	));
	
	return array_map('intval', $results);
}


// Check if a given user has blocked another user
function hs_is_user_blocked($actor_id, $target_id)
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';
	$count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(*) FROM $table_name WHERE actor_user_id = %d AND target_user_id = %d AND relationship_type = 'block'",
		$actor_id, $target_id
	));

	return (int)$count > 0;
}


// Check for a block between two users, going in either direction
function hs_check_block_status($user1_id, $user2_id)
{
	if (!$user1_id || !$user2_id)
	{
		return false;
	}

	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_user_relationships';
	$count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(*) FROM $table_name
		WHERE relationship_type = 'block'
		AND ((actor_user_id = %d AND target_user_id = %d) OR (actor_user_id = %d AND target_user_id = %d))",
		$user1_id, $user2_id, $user2_id, $user1_id
	));

	return (int)$count > 0;
}


// Add the admin page
function hs_user_reports_add_admin_page()
{
	add_menu_page(
		'User Reports',
		'User Reports',
		'manage_options',
		'hs-user-reports',
		'hs_user_reports_admin_page_html',
		'dashicons-admin-users',
		31
	);
}
add_action('admin_menu', 'hs_user_reports_add_admin_page');

function hs_user_reports_admin_page_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_user_reports';

    // Handle report status changes
    if (isset($_GET['action'], $_GET['report_id'], $_GET['_wpnonce'])) {
        $report_id = intval($_GET['report_id']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'hs_report_action_' . $report_id)) {
            $new_status = '';
            if ($_GET['action'] === 'resolve') {
                $new_status = 'resolved';
            } elseif ($_GET['action'] === 'dismiss') {
                $new_status = 'dismissed';
            }
            
            if (!empty($new_status)) {
                $wpdb->update($table_name, ['status' => $new_status], ['id' => $report_id]);
                echo '<div class="notice notice-success is-dismissible"><p>Report status updated.</p></div>';
            }
        }
    }

    $reports = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending' ORDER BY date_submitted DESC");
    ?>
    <div class="wrap">
        <h1>User Reports (Pending)</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Reported User</th>
                    <th>Reported By</th>
                    <th>Reason</th>
                    <th>Date Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports) : foreach ($reports as $report) : ?>
                    <?php
                        $reporter = get_userdata($report->reporting_user_id);
                        $reported = get_userdata($report->reported_user_id);
                    ?>
                    <tr>
                        <td><?php echo $reported ? esc_html($reported->display_name) : 'Unknown User'; ?> (ID: <?php echo $report->reported_user_id; ?>)</td>
                        <td><?php echo $reporter ? esc_html($reporter->display_name) : 'Unknown User'; ?> (ID: <?php echo $report->reporting_user_id; ?>)</td>
                        <td><?php echo nl2br(esc_html($report->report_reason)); ?></td>
                        <td><?php echo esc_html($report->date_submitted); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hs-user-reports&action=resolve&report_id=' . $report->id), 'hs_report_action_' . $report->id)); ?>" class="button button-primary">Resolve</a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hs-user-reports&action=dismiss&report_id=' . $report->id), 'hs_report_action_' . $report->id)); ?>" class="button button-secondary">Dismiss</a>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5">No pending user reports found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <style>
        .hs-report-status-resolved { color: green; font-weight: bold; }
        .hs-report-status-dismissed { color: red; font-weight: bold; }
        .hs-report-status-pending { color: orange; font-weight: bold; }
    </style>
    <?php
}

// --- AJAX Handlers for Web Frontend ---

function hs_ajax_block_user() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');
    if (!is_user_logged_in() || !isset($_POST['target_user_id'])) {
        wp_send_json_error(['message' => 'Invalid request.']);
    }
    $actor_id = get_current_user_id();
    $target_id = intval($_POST['target_user_id']);
    
    if (hs_block_user($actor_id, $target_id)) {
        wp_send_json_success(['message' => 'User blocked.']);
    } else {
        wp_send_json_error(['message' => 'Could not block user.']);
    }
}
add_action('wp_ajax_hs_block_user', 'hs_ajax_block_user');

function hs_ajax_unblock_user() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');
    if (!is_user_logged_in() || !isset($_POST['target_user_id'])) {
        wp_send_json_error(['message' => 'Invalid request.']);
    }
    $actor_id = get_current_user_id();
    $target_id = intval($_POST['target_user_id']);
    
    if (hs_unblock_user($actor_id, $target_id)) {
        wp_send_json_success(['message' => 'User unblocked.']);
    } else {
        wp_send_json_error(['message' => 'Could not unblock user.']);
    }
}
add_action('wp_ajax_hs_unblock_user', 'hs_ajax_unblock_user');

function hs_ajax_report_user() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');
    if (!is_user_logged_in() || !isset($_POST['target_user_id'], $_POST['reason'])) {
        wp_send_json_error(['message' => 'Invalid request. Missing fields.']);
    }
    $reporter_id = get_current_user_id();
    $reported_id = intval($_POST['target_user_id']);
    $reason = sanitize_textarea_field($_POST['reason']);

    if (empty(trim($reason))) {
         wp_send_json_error(['message' => 'A reason is required.']);
    }

    if (hs_report_user($reporter_id, $reported_id, $reason)) {
        wp_send_json_success(['message' => 'User reported. Thank you.']);
    } else {
        wp_send_json_error(['message' => 'Could not report user.']);
    }
}
add_action('wp_ajax_hs_report_user', 'hs_ajax_report_user');


// --- Frontend Modal for Reporting ---

/**
 * Adds the report user modal to the footer on user profiles.
 */
function hs_add_report_user_modal() {
    // Only show on user profiles
    if (!function_exists('bp_is_user') || !bp_is_user()) {
        return;
    }
    
    // Don't show on your own profile
    if (bp_displayed_user_id() == get_current_user_id()) {
        return;
    }
    
    $reported_user_id = bp_displayed_user_id();
    $reported_user_name = bp_get_displayed_user_fullname();
	?>
	<div id="hs-report-user-modal" style="display:none;">
		<div class="hs-modal-content">
			<span id="hs-close-report-user-modal" class="hs-modal-close">&times;</span>
			<h2>Report <?php echo esc_html($reported_user_name); ?></h2>
			<p>Please provide a reason for reporting this user. This will be sent to the site administrators for review.</p>
            <form id="hs-report-user-form">
                <input type="hidden" id="hs-report-user-id" value="<?php echo esc_attr($reported_user_id); ?>">
    			<textarea id="hs-report-user-textarea" rows="5" placeholder="Provide details about the user's behavior..." required></textarea>
    			<button id="hs-submit-user-report-button" class="hs-button">Submit Report</button>
            </form>
			<div id="hs-report-user-feedback" style="margin-top:10px;"></div>
		</div>
	</div>
    <style>
        /* Basic Modal Styles (can be moved to CSS file) */
        #hs-report-user-modal {
            position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6);
        }
        #hs-report-user-modal .hs-modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 25px;
            border: 1px solid #888; width: 80%; max-width: 500px;
            position: relative; border-radius: 5px;
        }
        #hs-report-user-modal .hs-modal-close {
            color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;
        }
        #hs-report-user-modal #hs-report-user-textarea {
            width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box;
        }
    </style>
	<?php
}
add_action('wp_footer', 'hs_add_report_user_modal');
?>
