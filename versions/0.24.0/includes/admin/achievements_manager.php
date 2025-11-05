<?php

// Administrative utilities for managing unlockable achievements


if (!defined('ABSPATH'))
{
	exit;
}


// Create achivements table when HotSoup is activated
function hs_achievements_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_achievements';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		slug varchar(50) NOT NULL,
		name varchar(100) NOT NULL,
		description text,
		icon_type varchar(20) NOT NULL DEFAULT 'star',
		icon_color varchar(7) NOT NULL DEFAULT '#FFD700',
		unlock_metric varchar(50) NOT NULL,
		unlock_value int(11) NOT NULL,
		points_reward int(11) DEFAULT 0,
		is_hidden tinyint(1) DEFAULT 0,
		display_order int(11) DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);


	// Create a table for storing data about users' unlocked achievements
	$user_achievements_table = $wpdb -> prefix . 'hs_user_achievements';


	$sql_user = "CREATE TABLE $user_achievements_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) UNSIGNED NOT NULL,
		achievement_id mediumint(9) NOT NULL,
		date_unlocked datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY user_achievement_unique (user_id, achievement_id)
	) $charset_collate;";

	dbDelta($sql_user);
}
//register_activation_hook(__FILE__, 'hs_achievements_create_table');



// Add the achievement manager to the administrator panel
function hs_achievements_add_admin_page()
{
	add_menu_page(
	'Achievement Manager',
	'Achievements',
	'manage_options',
	'hs-achievement-manager',
	'hs_achievements_admin_page_html',
	'dashicons-awards',
	27
	);
}
add_action('admin_menu', 'hs_achievements_add_admin_page');


// Admin page HTML
function hs_achievements_admin_page_html()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_achievements';


	// Form submission
	if (isset($_POST['hs_save_achievement_nonce']) && wp_verify_nonce($_POST['hs_save_achievement_nonce'], 'hs_save_achievement'))
	{
		$achievement_id = isset($_POST['achievement_id']) ? intval($_POST['achievement_id']) : 0;

		$data = [
		'slug' => sanitize_key($_POST['slug']),
		'name' => sanitize_text_field($_POST['name']),
		'description' => sanitize_textarea_field($_POST['description']),
		'icon_type' => sanitize_key($_POST['icon_type']),
		'icon_color' => sanitize_hex_color($_POST['icon_color']),
		'unlock_metric' => sanitize_key($_POST['unlock_metric']),
		'unlock_value' => intval($_POST['unlock_value']),
		'points_reward' => intval($_POST['points_reward']),
		'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0,
		'display_order' => intval($_POST['display_order']),
	];

		if ($achievement_id > 0)
		{
			$wpdb -> update($table_name, $data, ['id' => $achievement_id]);
			echo '<div class="notice notice-success"><p>Achievement updated successfully.</p></div>';
		}

		else
		{
			$wpdb -> insert($table_name, $data);
			echo '<div class="notice notice-success"><p>Achievement created successfully.</p></div>';
		}
	}

	// Handles deletion
	if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']))
	{
		if (wp_verify_nonce($_GET['_wpnonce'], 'hs_delete_achievement_' . $_GET['id']))
		{
			$wpdb -> delete($table_name, ['id' => intval($_GET['id'])]);
			echo '<div class="notice notice-success"><p>Achievement deleted successfully.</p></div>';
		}
	}

	// Retrieve details about an achievement to edit
	$achievement_to_edit = null;
	if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']))
	{
		$achievement_to_edit = $wpdb -> get_row($wpdb -> prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
	}


	$all_achievements = $wpdb -> get_results("SELECT * FROM $table_name ORDER BY display_order ASC, name ASC");
	?>

	<div class="wrap">
		<h1>Achievement Manager</h1>

		<div id="col-container" class="wp-clearfix">
		<div id="col-left">
		<div class="col-wrap">
			<h2><?php echo $achievement_to_edit ? 'Edit Achievement' : 'Add New Achievement'; ?></h2>

		<form method="post">
			<?php wp_nonce_field('hs_save_achievement', 'hs_save_achievement_nonce'); ?>
			<input type="hidden" name="achievement_id" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit -> id) : '0'; ?>">

 <div class="form-field">
                            <label for="name">Achievement Name *</label>
                            <input type="text" name="name" id="name" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit->name) : ''; ?>" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="slug">Achievement Slug *</label>
                            <input type="text" name="slug" id="slug" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit->slug) : ''; ?>" required>
                            <p class="description">Lowercase letters, numbers, and dashes only.</p>
                        </div>
                        
                        <div class="form-field">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" rows="3"><?php echo $achievement_to_edit ? esc_textarea($achievement_to_edit->description) : ''; ?></textarea>
                        </div>
                        
                        <h3>Icon Settings</h3>
                        
                        <div class="form-field">
                            <label for="icon_type">Icon Type</label>
                            <select name="icon_type" id="icon_type">
                                <option value="star" <?php echo $achievement_to_edit && $achievement_to_edit->icon_type === 'star' ? 'selected' : ''; ?>>⭐ Star</option>
                                <option value="trophy" <?php echo $achievement_to_edit && $achievement_to_edit->icon_type === 'trophy' ? 'selected' : ''; ?>>🏆 Trophy</option>
                                <option value="medal" <?php echo $achievement_to_edit && $achievement_to_edit->icon_type === 'medal' ? 'selected' : ''; ?>>🏅 Medal</option>
                                <option value="book" <?php echo $achievement_to_edit && $achievement_to_edit->icon_type === 'book' ? 'selected' : ''; ?>>📚 Book</option>
                                <option value="fire" <?php echo $achievement_to_edit && $achievement_to_edit->icon_type === 'fire' ? 'selected' : ''; ?>>🔥 Fire</option>
                                <option value="crown" <?php echo $achievement_to_edit && $achievement_to_edit->icon_type === 'crown' ? 'selected' : ''; ?>>👑 Crown</option>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="icon_color">Icon Color</label>
                            <input type="color" name="icon_color" id="icon_color" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit->icon_color) : '#FFD700'; ?>">
                        </div>
                        
                        <h3>Unlock Requirements</h3>
                        
                        <div class="form-field">
                            <label for="unlock_metric">Unlock Metric *</label>
                            <select name="unlock_metric" id="unlock_metric" required>
                                <option value="points" <?php echo $achievement_to_edit && $achievement_to_edit->unlock_metric === 'points' ? 'selected' : ''; ?>>Points Earned</option>
                                <option value="books_read" <?php echo $achievement_to_edit && $achievement_to_edit->unlock_metric === 'books_read' ? 'selected' : ''; ?>>Books Completed</option>
                                <option value="pages_read" <?php echo $achievement_to_edit && $achievement_to_edit->unlock_metric === 'pages_read' ? 'selected' : ''; ?>>Pages Read</option>
                                <option value="books_added" <?php echo $achievement_to_edit && $achievement_to_edit->unlock_metric === 'books_added' ? 'selected' : ''; ?>>Books Added to Database</option>
                                <option value="approved_reports" <?php echo $achievement_to_edit && $achievement_to_edit->unlock_metric === 'approved_reports' ? 'selected' : ''; ?>>Approved Reports</option>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="unlock_value">Unlock Value *</label>
                            <input type="number" name="unlock_value" id="unlock_value" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit->unlock_value) : ''; ?>" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="points_reward">Points Reward</label>
                            <input type="number" name="points_reward" id="points_reward" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit->points_reward) : '0'; ?>">
                            <p class="description">Points awarded when unlocked (0 for no reward)</p>
                        </div>
                        
                        <div class="form-field">
                            <label for="display_order">Display Order</label>
                            <input type="number" name="display_order" id="display_order" value="<?php echo $achievement_to_edit ? esc_attr($achievement_to_edit->display_order) : '0'; ?>">
                            <p class="description">Lower numbers appear first</p>
                        </div>
                        
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="is_hidden" value="1" <?php echo $achievement_to_edit && $achievement_to_edit->is_hidden ? 'checked' : ''; ?>>
                                Hidden Achievement (users can't see requirements until unlocked)
                            </label>
                        </div>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php echo $achievement_to_edit ? 'Update Achievement' : 'Create Achievement'; ?>">
                            <?php if ($achievement_to_edit): ?>
                                <a href="?page=hs-achievement-manager" class="button">Cancel</a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
            
            <div id="col-right">
                <div class="col-wrap">
                    <h2>Existing Achievements</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Name</th>
                                <th>Icon</th>
                                <th>Requirement</th>
                                <th>Reward</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_achievements): ?>
                                <?php foreach ($all_achievements as $achievement): ?>
                                    <tr>
                                        <td><?php echo esc_html($achievement->display_order); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($achievement->name); ?></strong>
                                            <?php if ($achievement->is_hidden): ?>
                                                <span class="dashicons dashicons-hidden" title="Hidden Achievement"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="hs-achievement-icon-preview" style="background-color: <?php echo esc_attr($achievement->icon_color); ?>">
                                                <?php echo hs_get_icon_symbol($achievement->icon_type); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo esc_html(ucwords(str_replace('_', ' ', $achievement->unlock_metric))); ?>: 
                                            <?php echo esc_html($achievement->unlock_value); ?>
                                        </td>
                                        <td><?php echo $achievement->points_reward > 0 ? esc_html($achievement->points_reward) . ' pts' : '—'; ?></td>
                                        <td>
                                            <a href="?page=hs-achievement-manager&action=edit&id=<?php echo $achievement->id; ?>">Edit</a> | 
                                            <a href="?page=hs-achievement-manager&action=delete&id=<?php echo $achievement->id; ?>&_wpnonce=<?php echo wp_create_nonce('hs_delete_achievement_' . $achievement->id); ?>" onclick="return confirm('Are you sure?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No achievements found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        #col-container { display: flex; gap: 20px; }
        #col-left { flex: 0 0 400px; }
        #col-right { flex: 1; }
        .form-field { margin-bottom: 15px; }
        .form-field label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-field input[type="text"],
        .form-field input[type="number"],
        .form-field select,
        .form-field textarea { width: 100%; }
        .form-field input[type="color"] { width: 100px; height: 40px; }
        .form-field .description { color: #666; font-size: 12px; margin-top: 5px; }
        .hs-achievement-icon-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 2px solid #ddd;
        }
    </style>
    <?php
}

// Helper function to get icon symbol
function hs_get_icon_symbol($icon_type) {
    $icons = [
        'star' => '⭐',
        'trophy' => '🏆',
        'medal' => '🏅',
        'book' => '📚',
        'fire' => '🔥',
        'crown' => '👑',
    ];
    return isset($icons[$icon_type]) ? $icons[$icon_type] : '⭐';
}


// Check and unlock achievements for a given user
function hs_check_user_achievements($user_id)
{
	if (!$user_id)
	{
		return;
	}

	global $wpdb;
	$achievements_table = $wpdb -> prefix . 'hs_achievements';
	$user_achievements_table = $wpdb -> prefix . 'hs_user_achievements';


	// Retrieve unlocked achievements
	$unlocked_achievement_ids = $wpdb -> get_col($wpdb -> prepare(
		"SELECT achievement_id FROM $user_achievements_table WHERE user_id = %d",
		$user_id
	));


	// Retrieve locked achievements
	$placeholder = !empty($unlocked_achievement_ids) ? implode(',', array_fill(0, count($unlocked_achievement_ids), '%d')) : '0';
	$query = "SELECT * FROM $achievements_table WHERE id NOT IN ($placeholder)";
	$unearned_achievements = $wpdb -> get_results($wpdb -> prepare($query, $unlocked_achievement_ids));

	if (empty($unearned_achievements))
	{
		return;
	}


	// Retrieve user statistics
	$user_stats = [
		'points' => (int) get_user_meta($user_id, 'user_points', true),
		'books_read' => (int) get_user_meta($user_id, 'hs_completed_books_count', true),
		'pages_read' => (int) get_user_meta($user_id, 'hs_total_pages_read', true),
		'books_added' => (int) get_user_meta($user_id, 'hs_books_added_count', true),
		'approved_reports' => (int) get_user_meta($user_id, 'hs_approved_reports_count', true),
	];


	$metric_map = [
		'points' => 'user_points',
		'books_read' => 'hs_completed_books_count',
		'pages_read' => 'hs_total_pages_read',
		'books_added' => 'hs_books_added_count',
		'approved_reports' => 'hs_approved_reports_count',
	];

	$user_stats = [];
	foreach ($metric_map as $metric => $meta_key)
	{
		$user_stats[$metric] = (int) get_user_meta($user_id, $meta_key, true);


	// Check each achievement
	foreach ($unearned_achievements as $achievement)
	{
		$metric = $achievement -> unlock_metric;

		if (isset($user_stats[$metric]) && $user_stats[$metric] >= $achievement -> unlock_value)
		{
			$wpdb -> insert(
			$user_achievements_table,
			[
				'user_id' => $user_id,
				'achievement_id' => $achievement -> id,
				'date_unlocked' => current_time('mysql'),
			],
			['%d', '%d', '%s']
		);


		// If points are part of the achievement, award points
		if ($achievement -> points_reward > 0 && function_exists('award_points'))
		{
			award_points($user_id, $achievement -> points_reward);
		}
	}
}}
}
add_action('hs_stats_updated', 'hs_check_user_achievements', 10, 1);
add_action('hs_points_updated', 'hs_check_user_achievements', 10, 1);


// Add an Achievements tab to users' profile
function hs_achievements_profile_nav()
{
	if (function_exists('bp_core_new_nav_item'))
	{
		bp_core_new_nav_item([
			'name' => 'Achievements',
			'slug' => 'achievements',
			'screen_function' => 'hs_achievements_screen_content',
			'position' => 40,
			'default_subnav_slug' => 'achievements'
		]);
	}
}
add_action('bp_setup_nav', 'hs_achievements_profile_nav');


// Render the achievements screen
function hs_achievements_screen_content()
{
	add_action('bp_template_content', 'hs_render_achievements_display');
	bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}


// Display achievements
function hs_render_achievements_display()
{
	$user_id = bp_displayed_user_id();

	global $wpdb;
	$achievements_table = $wpdb -> prefix . 'hs_achievements';
	$user_achievements_table = $wpdb -> prefix . 'hs_user_achievements';

	// Retrieve achievements list, unlock status
	$all_achievements = $wpdb -> get_results($wpdb -> prepare(
		"SELECT a.*, ua.date_unlocked, ua.id IS NOT NULL as is_unlocked
		FROM {$achievements_table} a
		LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id AND ua.user_id = %d
		ORDER BY a.display_order ASC, a.name ASC",
		$user_id
	));

	// Retrieve user statistics
	$user_stats = [
		'points' => (int) get_user_meta($user_id, 'user_points', true),
		'books_read' => (int) get_user_meta($user_id, 'hs_completed_books_count', true),
		'pages_read' => (int) get_user_meta($user_id, 'hs_total_pages_read', true),
		'books_added' => (int) get_user_meta($user_id, 'hs_books_added_count', true),
		'approved_reports' => (int) get_user_meta($user_id, 'hs_approved_reports_count', true),
	];

	        $metric_map = [
                'points' => 'user_points',
                'books_read' => 'hs_completed_books_count',
                'pages_read' => 'hs_total_pages_read',
                'books_added' => 'hs_books_added_count',
                'approved_reports' => 'hs_approved_reports_count',
        ];

        // Retrieve user statistics
        $user_stats = [];
        foreach ($metric_map as $metric => $meta_key) {
                $user_stats[$metric] = (int) get_user_meta($user_id, $meta_key, true);
        }


	$unlocked_count = 0;

	foreach ($all_achievements as $ach)
	{
		if ($ach -> is_unlocked)
		{
			$unlocked_count++;
		}
	}

?>
    
    <div class="hs-achievements-container">
        <div class="hs-achievements-header">
            <h3>Achievements</h3>
            <p class="hs-achievement-progress">
                Unlocked: <strong><?php echo $unlocked_count; ?></strong> / <?php echo count($all_achievements); ?>
            </p>
        </div>

        <div class="hs-achievements-grid">
            <?php foreach ($all_achievements as $achievement): ?>
                <?php
                $is_unlocked = $achievement->is_unlocked;
                $progress_percentage = 0;
                $current_value = 0;

                if (!$is_unlocked) {
                    $current_value = isset($user_stats[$achievement->unlock_metric]) ? $user_stats[$achievement->unlock_metric] : 0;
                    if ($achievement->unlock_value > 0) {
                        $progress_percentage = min(100, ($current_value / $achievement->unlock_value) * 100);
                    }
                } else {
                    $progress_percentage = 100;
                }

                $is_hidden = $achievement->is_hidden && !$is_unlocked;
                ?>

                <div class="hs-achievement-item <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?> <?php echo $is_hidden ? 'hidden-achievement' : ''; ?>">
                    <div class="hs-achievement-icon" style="background-color: <?php echo $is_unlocked ? esc_attr($achievement->icon_color) : '#ccc'; ?>">
                        <?php if ($is_hidden): ?>
                            <span class="hidden-icon">?</span>
                        <?php else: ?>
                            <?php echo hs_get_icon_symbol($achievement->icon_type); ?>
                        <?php endif; ?>
                    </div>

                    <div class="hs-achievement-info">
                        <h4 class="hs-achievement-name">
                            <?php echo $is_hidden ? '???' : esc_html($achievement->name); ?>
                        </h4>
                        <p class="hs-achievement-description">
                            <?php echo $is_hidden ? 'Hidden achievement - unlock to reveal!' : esc_html($achievement->description); ?>
                        </p>

                        <?php if (!$is_hidden): ?>
                            <div class="hs-achievement-progress-bar">
                                <div class="hs-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%;"></div>
                            </div>
                            <p class="hs-achievement-requirement">
                                <?php if ($is_unlocked): ?>
                                    <span class="unlocked-text">✓ Unlocked <?php echo human_time_diff(strtotime($achievement->date_unlocked), current_time('timestamp')); ?> ago</span>
                                <?php else: ?>
                                    <?php echo number_format($current_value); ?> / <?php echo number_format($achievement->unlock_value); ?> 
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $achievement->unlock_metric))); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}


function hs_enqueue_achievement_styles()
{
	wp_enqueue_style(
		'hs-achievements',
		plugin_dir_url(__FILE__) . '../../css/hs-achievements.css',
		[],
		'1.0.0'
	);
}
add_action('wp_enqueue_scripts', 'hs_enqueue_achievement_styles');


function hs_achievement_debug_add_submenu() {
    add_submenu_page(
        'hs-achievement-manager',
        'Achievement Debug',
        'Debug Tool',
        'manage_options',
        'hs-achievement-debug',
        'hs_achievement_debug_page_html'
    );
}
add_action('admin_menu', 'hs_achievement_debug_add_submenu');

// Debug page HTML
function hs_achievement_debug_page_html() {
    global $wpdb;
    
    // Handle manual check trigger
    if (isset($_POST['check_user_achievements']) && isset($_POST['user_id']) && wp_verify_nonce($_POST['_wpnonce'], 'hs_debug_check_achievements')) {
        $user_id = intval($_POST['user_id']);
        hs_check_user_achievements($user_id);
        echo '<div class="notice notice-success"><p>Achievement check completed for user ID ' . $user_id . '!</p></div>';
    }

    // Handle recalculate all users
    if (isset($_POST['check_all_users']) && wp_verify_nonce($_POST['_wpnonce'], 'hs_debug_check_all')) {
        $users = get_users(['fields' => 'ID']);
        $count = 0;
        foreach ($users as $user_id) {
            hs_check_user_achievements($user_id);
            $count++;
        }
        echo '<div class="notice notice-success"><p>Achievement check completed for ' . $count . ' users!</p></div>';
    }
    
    // Get selected user
    $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
    
    // Get all users for dropdown
    $all_users = get_users(['orderby' => 'display_name']);
    
    ?>
    <div class="wrap">
        <h1>Achievement Debug Tool</h1>
        
        <!-- User Selection -->
        <div class="card" style="max-width: 800px;">
            <h2>Select User</h2>
            <form method="get">
                <input type="hidden" name="page" value="hs-achievement-debug">
                <select name="user_id" onchange="this.form.submit()" style="min-width: 300px;">
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($selected_user_id, $user->ID); ?>>
                            <?php echo esc_html($user->display_name); ?> (ID: <?php echo $user->ID; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="View Stats">
            </form>
        </div>

        <?php if ($selected_user_id): ?>
            <?php
            // Get user info
            $user_info = get_userdata($selected_user_id);
            
            // Define metric mapping
            $metric_map = [
                'points' => 'user_points',
                'books_read' => 'hs_completed_books_count',
                'pages_read' => 'hs_total_pages_read',
                'books_added' => 'hs_books_added_count',
                'approved_reports' => 'hs_approved_reports_count',
            ];
            
            // Get user stats
            $user_stats = [];
            foreach ($metric_map as $metric => $meta_key) {
                $user_stats[$metric] = (int) get_user_meta($selected_user_id, $meta_key, true);
            }
            
            // Get achievements
            $achievements_table = $wpdb->prefix . 'hs_achievements';
            $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';
            
            $all_achievements = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, ua.date_unlocked, ua.id IS NOT NULL as is_unlocked
                FROM {$achievements_table} a
                LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id AND ua.user_id = %d
                ORDER BY a.display_order ASC, a.name ASC",
                $selected_user_id
            ));
            ?>
            
            <!-- User Stats Card -->
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>User Statistics: <?php echo esc_html($user_info->display_name); ?></h2>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Database Key</th>
                            <th>Current Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metric_map as $metric => $meta_key): ?>
                            <tr>
                                <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $metric))); ?></strong></td>
                                <td><code><?php echo esc_html($meta_key); ?></code></td>
                                <td><strong style="color: #0073aa; font-size: 16px;"><?php echo number_format($user_stats[$metric]); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Achievement Status Card -->
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Achievement Status</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="30%">Achievement</th>
                            <th width="20%">Metric</th>
                            <th width="15%">Required</th>
                            <th width="15%">Current</th>
                            <th width="10%">Progress</th>
                            <th width="10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_achievements as $achievement): ?>
                            <?php
                            $current_value = isset($user_stats[$achievement->unlock_metric]) ? $user_stats[$achievement->unlock_metric] : 0;
                            $progress = $achievement->unlock_value > 0 ? round(($current_value / $achievement->unlock_value) * 100) : 0;
                            $should_unlock = $current_value >= $achievement->unlock_value;
                            $is_unlocked = (bool)$achievement->is_unlocked;
                            
                            // Determine row color
                            $row_style = '';
                            if ($is_unlocked) {
                                $row_style = 'background-color: #d4edda;';
                            } elseif ($should_unlock) {
                                $row_style = 'background-color: #fff3cd;';
                            }
                            ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td>
                                    <strong><?php echo esc_html($achievement->name); ?></strong>
                                    <?php if ($achievement->is_hidden): ?>
                                        <span class="dashicons dashicons-hidden" title="Hidden"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $achievement->unlock_metric))); ?></td>
                                <td><strong><?php echo number_format($achievement->unlock_value); ?></strong></td>
                                <td><strong><?php echo number_format($current_value); ?></strong></td>
                                <td>
                                    <div style="background: #e0e0e0; border-radius: 3px; height: 20px; position: relative;">
                                        <div style="background: <?php echo $is_unlocked ? '#28a745' : '#0073aa'; ?>; width: <?php echo min(100, $progress); ?>%; height: 100%; border-radius: 3px;"></div>
                                        <span style="position: absolute; top: 0; left: 0; right: 0; text-align: center; line-height: 20px; font-size: 11px; font-weight: bold; color: #333;">
                                            <?php echo $progress; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_unlocked): ?>
                                        <span style="color: #28a745; font-weight: bold;">✓ Unlocked</span>
                                    <?php elseif ($should_unlock): ?>
                                        <span style="color: #ff6600; font-weight: bold;">⚠ Should Unlock</span>
                                    <?php else: ?>
                                        <span style="color: #666;">Locked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Manual Check Button -->
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Manual Achievement Check</h2>
                <p>Click the button below to manually run the achievement check for this user. This will unlock any achievements they qualify for.</p>
                <form method="post">
                    <?php wp_nonce_field('hs_debug_check_achievements'); ?>
                    <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                    <button type="submit" name="check_user_achievements" class="button button-primary button-large">
                        🔄 Check Achievements for <?php echo esc_html($user_info->display_name); ?>
                    </button>
                </form>
            </div>

        <?php endif; ?>

        <!-- Check All Users -->
        <div class="card" style="max-width: 800px; margin-top: 20px; border-left: 4px solid #dc3545;">
            <h2 style="color: #dc3545;">⚠️ Bulk Operations</h2>
            <p><strong>Warning:</strong> This will check achievements for ALL users on the site. This may take a while on sites with many users.</p>
            <form method="post" onsubmit="return confirm('Are you sure you want to check achievements for ALL users? This cannot be undone.');">
                <?php wp_nonce_field('hs_debug_check_all'); ?>
                <button type="submit" name="check_all_users" class="button button-secondary button-large">
                    🔄 Check Achievements for All Users
                </button>
            </form>
        </div>

        <!-- Legend -->
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h3>Legend</h3>
            <ul>
                <li><span style="display: inline-block; width: 20px; height: 20px; background: #d4edda; border: 1px solid #c3e6cb; vertical-align: middle;"></span> <strong>Green:</strong> Achievement is unlocked</li>
                <li><span style="display: inline-block; width: 20px; height: 20px; background: #fff3cd; border: 1px solid #ffeaa7; vertical-align: middle;"></span> <strong>Yellow:</strong> User qualifies but achievement hasn't been unlocked yet (click "Check Achievements" to fix)</li>
                <li><span style="display: inline-block; width: 20px; height: 20px; background: #fff; border: 1px solid #ddd; vertical-align: middle;"></span> <strong>White:</strong> Achievement is locked (user hasn't met requirements)</li>
            </ul>
        </div>
    </div>

    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
    </style>
    <?php
}
