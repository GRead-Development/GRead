<?php

// This stores information about what themes are available to be unlocked, and how they are unlocked, respectively.

function hs_enqueue_theme_styles()
{
	if (!wp_doing_ajax())
	{
	wp_enqueue_style(
		'hs-theme',
		plugin_dir_url(__FILE__) . '../../css/hs-themes.css',
		[],
		'1.0.0'
	);
}
}
add_action('wp_enqueue_scripts', 'hs_enqueue_theme_styles');


function hs_theme_settings_nav()
{
	if (function_exists('bp_core_new_nav_item'))
	{
		bp_core_new_nav_item([
			'name' => 'Themes',
			'slug' => 'themes',
			'parent_slug' => bp_get_settings_slug(),
			'screen_function' => 'hs_theme_settings_screen_content',
			'position' => 30
		]);
	}
}
add_action('bp_setup_nav', 'hs_theme_settings_nav');


// Render the content for the themes tab
function hs_theme_settings_screen_content()
{
	wp_enqueue_script(
		'hs-theme-selector-js',
		plugin_dir_url(__FILE__) . '../../js/unlockables/theme-selector.js',
		['jquery'],
		'1.0.1',
		true
	);

	wp_localize_script(
		'hs-theme-selector-js',
		'hs_theme_ajax',
		['ajaxurl' => admin_url('admin-ajax.php')]
	);

	add_action('bp_template_content', 'hs_render_theme_selector');
	bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}




// AJAX handler for changing user's theme
function hs_save_user_theme_callback()
{
	// Verify nonce
	if (!isset($_POST['hs_theme_nonce']) || !wp_verify_nonce($_POST['hs_theme_nonce'], 'hs_save_theme_nonce'))
	{
		wp_send_json_error(['message' => 'Security check failed.']);
		return;
	}

	if (!is_user_logged_in() || !isset($_POST['selected_theme']))
	{
		wp_send_json_error(['message' => 'Invalid request.']);
		return;
	}

	$user_id = get_current_user_id();
	$selected_slug = sanitize_key($_POST['selected_theme']);
	$themes = hs_get_available_themes();

	if (!isset($themes[$selected_slug]))
	{
		wp_send_json_error(['message' => 'Invalid theme selection.']);
		return;
	}

	$theme = $themes[$selected_slug];
	$is_unlocked = false;

	if (!empty($theme['unlocked']))
	{
		$is_unlocked = true;
	}
	else
	{
		switch ($theme['unlock_metric'])
		{
			case 'points':
				$meta_key_to_check = 'user_points';
				break;

			case 'books_read':
				$meta_key_to_check = 'hs_completed_books_count';
				break;

			case 'pages_read':
				$meta_key_to_check = 'hs_total_pages_read';
				break;

			default:
				$meta_key_to_check = 'hs_' . sanitize_key($theme['unlock_metric']);
		}


		$raw_val = get_user_meta($user_id, $meta_key_to_check, true);


		if (empty($raw_val) && strpos($meta_key_to_check, 'hs_') !== 0)
		{
			$raw_val = get_user_meta($user_id, 'hs_' . $meta_key_to_check, true);
		}

		if ($raw_val >= intval($theme['unlock_value']))
		{
			$is_unlocked = true;
		}
	}

	if ($is_unlocked)
	{
		update_user_meta($user_id, 'hs_selected_theme', $selected_slug);
		wp_send_json_success(['message' => 'Successfully set your theme. The page will reload so that your theme can be applied.']);
	}
	else
	{
		wp_send_json_error(['message' => 'Oops! You have not unlocked this theme yet!']);
	}

}
add_action('wp_ajax_hs_save_user_theme', 'hs_save_user_theme_callback');

/**
 * HotSoup Theme Manager
 * Replaces the hardcoded themes system with a database-driven admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

// Create the themes table on activation
function hs_themes_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_themes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        slug varchar(50) NOT NULL,
        name varchar(100) NOT NULL,
        preview_color varchar(7) NOT NULL,
        bg_color varchar(7) NOT NULL,
        text_color varchar(7) NOT NULL,
        link_color varchar(7) NOT NULL,
        link_hover_color varchar(7) NOT NULL,
        header_bg varchar(7) NOT NULL,
        widget_bg varchar(7) NOT NULL,
        border_color varchar(7) NOT NULL,
        button_bg varchar(7) NOT NULL,
        button_hover_bg varchar(7) NOT NULL,
        unlock_metric varchar(50),
        unlock_value int(11) DEFAULT 0,
        unlock_message text,
        is_default tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Insert default theme if it doesn't exist
    $default_exists = $wpdb->get_var("SELECT id FROM $table_name WHERE slug = 'default'");
    if (!$default_exists) {
        $wpdb->insert($table_name, [
            'slug' => 'default',
            'name' => 'Default',
            'preview_color' => '#0073aa',
            'bg_color' => '#ffffff',
            'text_color' => '#333333',
            'link_color' => '#0073aa',
            'link_hover_color' => '#005a87',
            'header_bg' => '#ffffff',
            'widget_bg' => '#f9f9f9',
            'border_color' => '#e0e0e0',
            'button_bg' => '#0073aa',
            'button_hover_bg' => '#005a87',
            'is_default' => 1
        ]);
    }
}
register_activation_hook(__FILE__, 'hs_themes_create_table');

// Add Theme Manager to admin menu
function hs_themes_add_admin_page() {
    add_menu_page(
        'Theme Manager',
        'Theme Manager',
        'manage_options',
        'hs-theme-manager',
        'hs_themes_admin_page_html',
        'dashicons-admin-appearance',
        26
    );
}
add_action('admin_menu', 'hs_themes_add_admin_page');

// Admin page HTML
function hs_themes_admin_page_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_themes';

    // Handle form submission
    if (isset($_POST['hs_save_theme_nonce']) && wp_verify_nonce($_POST['hs_save_theme_nonce'], 'hs_save_theme')) {
        $theme_id = isset($_POST['theme_id']) ? intval($_POST['theme_id']) : 0;
        
        $data = [
            'slug' => sanitize_key($_POST['slug']),
            'name' => sanitize_text_field($_POST['name']),
            'preview_color' => sanitize_hex_color($_POST['preview_color']),
            'bg_color' => sanitize_hex_color($_POST['bg_color']),
            'text_color' => sanitize_hex_color($_POST['text_color']),
            'link_color' => sanitize_hex_color($_POST['link_color']),
            'link_hover_color' => sanitize_hex_color($_POST['link_hover_color']),
            'header_bg' => sanitize_hex_color($_POST['header_bg']),
            'widget_bg' => sanitize_hex_color($_POST['widget_bg']),
            'border_color' => sanitize_hex_color($_POST['border_color']),
            'button_bg' => sanitize_hex_color($_POST['button_bg']),
            'button_hover_bg' => sanitize_hex_color($_POST['button_hover_bg']),
            'unlock_metric' => sanitize_key($_POST['unlock_metric']),
            'unlock_value' => intval($_POST['unlock_value']),
            'unlock_message' => sanitize_text_field($_POST['unlock_message']),
        ];

        if ($theme_id > 0) {
            $wpdb->update($table_name, $data, ['id' => $theme_id]);
            echo '<div class="notice notice-success"><p>Theme updated successfully!</p></div>';
        } else {
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success"><p>Theme created successfully!</p></div>';
        }
    }

    // Handle deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'hs_delete_theme_' . $_GET['id'])) {
            $theme_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
            if ($theme_to_delete && !$theme_to_delete->is_default) {
                $wpdb->delete($table_name, ['id' => intval($_GET['id'])]);
                echo '<div class="notice notice-success"><p>Theme deleted successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Cannot delete the default theme!</p></div>';
            }
        }
    }

    // Get theme to edit
    $theme_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $theme_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }

    $all_themes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY is_default DESC, name ASC");
    ?>
    
    <div class="wrap">
        <h1>Theme Manager</h1>
        
        <div id="col-container" class="wp-clearfix">
            <div id="col-left">
                <div class="col-wrap">
                    <h2><?php echo $theme_to_edit ? 'Edit Theme' : 'Add New Theme'; ?></h2>
                    
                    <form method="post">
                        <?php wp_nonce_field('hs_save_theme', 'hs_save_theme_nonce'); ?>
                        <input type="hidden" name="theme_id" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->id) : '0'; ?>">
                        
                        <div class="form-field">
                            <label for="name">Theme Name *</label>
                            <input type="text" name="name" id="name" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->name) : ''; ?>" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="slug">Theme Slug *</label>
                            <input type="text" name="slug" id="slug" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->slug) : ''; ?>" required <?php echo $theme_to_edit && $theme_to_edit->is_default ? 'readonly' : ''; ?>>
                            <p class="description">Lowercase letters, numbers, and dashes only.</p>
                        </div>
                        
                        <h3>Color Settings</h3>
                        
                        <div class="form-field">
                            <label for="preview_color">Preview Color</label>
                            <input type="color" name="preview_color" id="preview_color" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->preview_color) : '#0073aa'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="bg_color">Background Color</label>
                            <input type="color" name="bg_color" id="bg_color" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->bg_color) : '#ffffff'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="text_color">Text Color</label>
                            <input type="color" name="text_color" id="text_color" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->text_color) : '#333333'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="link_color">Link Color</label>
                            <input type="color" name="link_color" id="link_color" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->link_color) : '#0073aa'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="link_hover_color">Link Hover Color</label>
                            <input type="color" name="link_hover_color" id="link_hover_color" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->link_hover_color) : '#005a87'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="header_bg">Header Background</label>
                            <input type="color" name="header_bg" id="header_bg" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->header_bg) : '#ffffff'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="widget_bg">Widget Background</label>
                            <input type="color" name="widget_bg" id="widget_bg" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->widget_bg) : '#f9f9f9'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="border_color">Border Color</label>
                            <input type="color" name="border_color" id="border_color" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->border_color) : '#e0e0e0'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="button_bg">Button Background</label>
                            <input type="color" name="button_bg" id="button_bg" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->button_bg) : '#0073aa'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="button_hover_bg">Button Hover Background</label>
                            <input type="color" name="button_hover_bg" id="button_hover_bg" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->button_hover_bg) : '#005a87'; ?>">
                        </div>
                        
                        <h3>Unlock Requirements</h3>
                        <p class="description">Leave blank if theme should be unlocked by default</p>
                        
                        <div class="form-field">
                            <label for="unlock_metric">Unlock Metric</label>
                            <select name="unlock_metric" id="unlock_metric">
                                <option value="">None (Always Unlocked)</option>
                                <option value="points" <?php echo $theme_to_edit && $theme_to_edit->unlock_metric === 'points' ? 'selected' : ''; ?>>Points</option>
                                <option value="books_read" <?php echo $theme_to_edit && $theme_to_edit->unlock_metric === 'books_read' ? 'selected' : ''; ?>>Books Read</option>
                                <option value="pages_read" <?php echo $theme_to_edit && $theme_to_edit->unlock_metric === 'pages_read' ? 'selected' : ''; ?>>Pages Read</option>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="unlock_value">Unlock Value</label>
                            <input type="number" name="unlock_value" id="unlock_value" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->unlock_value) : '0'; ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="unlock_message">Unlock Message</label>
                            <input type="text" name="unlock_message" id="unlock_message" value="<?php echo $theme_to_edit ? esc_attr($theme_to_edit->unlock_message) : ''; ?>" placeholder="e.g., Read 1,000 pages to unlock!">
                        </div>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php echo $theme_to_edit ? 'Update Theme' : 'Create Theme'; ?>">
                            <?php if ($theme_to_edit): ?>
                                <a href="?page=hs-theme-manager" class="button">Cancel</a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
            
            <div id="col-right">
                <div class="col-wrap">
                    <h2>Existing Themes</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Preview</th>
                                <th>Unlock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_themes): ?>
                                <?php foreach ($all_themes as $theme): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($theme->name); ?></strong>
                                            <?php if ($theme->is_default): ?>
                                                <span class="dashicons dashicons-star-filled" style="color: gold;" title="Default Theme"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="width: 30px; height: 30px; background-color: <?php echo esc_attr($theme->preview_color); ?>; border: 1px solid #ddd; border-radius: 3px;"></div>
                                        </td>
                                        <td>
                                            <?php if (empty($theme->unlock_metric)): ?>
                                                <em>Always unlocked</em>
                                            <?php else: ?>
                                                <?php echo esc_html(ucwords(str_replace('_', ' ', $theme->unlock_metric))); ?>: <?php echo esc_html($theme->unlock_value); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?page=hs-theme-manager&action=edit&id=<?php echo $theme->id; ?>">Edit</a>
                                            <?php if (!$theme->is_default): ?>
                                                | <a href="?page=hs-theme-manager&action=delete&id=<?php echo $theme->id; ?>&_wpnonce=<?php echo wp_create_nonce('hs_delete_theme_' . $theme->id); ?>" onclick="return confirm('Are you sure?')">Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No themes found.</td>
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
        .form-field select { width: 100%; }
        .form-field input[type="color"] { width: 100px; height: 40px; }
        .form-field .description { color: #666; font-size: 12px; margin-top: 5px; }
    </style>
    <?php
}

// Get themes from database
function hs_get_available_themes() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_themes';
    $themes_data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY is_default DESC, name ASC");
    
    $themes = [];
    foreach ($themes_data as $theme) {
        $themes[$theme->slug] = [
            'name' => $theme->name,
            'css_class' => 'theme-' . $theme->slug,
            'preview_color' => $theme->preview_color,
            'colors' => [
                'bg' => $theme->bg_color,
                'text' => $theme->text_color,
                'link' => $theme->link_color,
                'link_hover' => $theme->link_hover_color,
                'header_bg' => $theme->header_bg,
                'widget_bg' => $theme->widget_bg,
                'border' => $theme->border_color,
                'button_bg' => $theme->button_bg,
                'button_hover_bg' => $theme->button_hover_bg,
            ],
            'unlocked' => $theme->is_default || empty($theme->unlock_metric),
            'unlock_metric' => $theme->unlock_metric,
            'unlock_value' => $theme->unlock_value,
            'unlock_message' => $theme->unlock_message,
        ];
    }
    
    return $themes;
}

// Generate dynamic CSS
function hs_generate_theme_css() {
    $themes = hs_get_available_themes();
    
    ob_start();
    ?>
    <style id="hs-dynamic-theme-styles">
    <?php foreach ($themes as $slug => $theme): ?>
        body.theme-<?php echo esc_attr($slug); ?> {
            background-color: <?php echo esc_attr($theme['colors']['bg']); ?> !important;
            color: <?php echo esc_attr($theme['colors']['text']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> #page,
        body.theme-<?php echo esc_attr($slug); ?> .site-header,
        body.theme-<?php echo esc_attr($slug); ?> .entry-content,
        body.theme-<?php echo esc_attr($slug); ?> .entry-summary,
        body.theme-<?php echo esc_attr($slug); ?> footer.site-footer,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress .activity-list,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress .activity-item {
            background-color: <?php echo esc_attr($theme['colors']['header_bg']); ?> !important;
            color: <?php echo esc_attr($theme['colors']['text']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> .widget,
        body.theme-<?php echo esc_attr($slug); ?> #secondary .widget,
        body.theme-<?php echo esc_attr($slug); ?> .hs-container,
        body.theme-<?php echo esc_attr($slug); ?> .hs-book-list li,
        body.theme-<?php echo esc_attr($slug); ?> .hs-my-book-list li,
        body.theme-<?php echo esc_attr($slug); ?> .hs-completed-section,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress .activity-content,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress div.activity-comments {
            background-color: <?php echo esc_attr($theme['colors']['widget_bg']); ?> !important;
            border-color: <?php echo esc_attr($theme['colors']['border']); ?> !important;
            color: <?php echo esc_attr($theme['colors']['text']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> h1,
        body.theme-<?php echo esc_attr($slug); ?> h2,
        body.theme-<?php echo esc_attr($slug); ?> h3,
        body.theme-<?php echo esc_attr($slug); ?> h4,
        body.theme-<?php echo esc_attr($slug); ?> h5,
        body.theme-<?php echo esc_attr($slug); ?> h6,
        body.theme-<?php echo esc_attr($slug); ?> .entry-title a,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress .activity-header a {
            color: <?php echo esc_attr($theme['colors']['text']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> a,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress a {
            color: <?php echo esc_attr($theme['colors']['link']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> a:hover,
        body.theme-<?php echo esc_attr($slug); ?> #buddypress a:hover {
            color: <?php echo esc_attr($theme['colors']['link_hover']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> .hs-button,
        body.theme-<?php echo esc_attr($slug); ?> button.hs-button,
        body.theme-<?php echo esc_attr($slug); ?> input[type="submit"].hs-button {
            background-color: <?php echo esc_attr($theme['colors']['button_bg']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> .hs-button:hover,
        body.theme-<?php echo esc_attr($slug); ?> button.hs-button:hover,
        body.theme-<?php echo esc_attr($slug); ?> input[type="submit"].hs-button:hover {
            background-color: <?php echo esc_attr($theme['colors']['button_hover_bg']); ?> !important;
        }
        
        body.theme-<?php echo esc_attr($slug); ?> .hs-progress-bar-container {
            background-color: <?php echo esc_attr($theme['colors']['border']); ?> !important;
        }
    <?php endforeach; ?>
    </style>
    <?php
    echo ob_get_clean();
}
add_action('wp_head', 'hs_generate_theme_css');

// Apply theme body class (keep existing function)
function hs_apply_theme_body_class($classes) {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $selected_theme = get_user_meta($user_id, 'hs_selected_theme', true);
        $themes = hs_get_available_themes();

        if (!empty($selected_theme) && isset($themes[$selected_theme])) {
            $classes[] = esc_attr($themes[$selected_theme]['css_class']);
        } else {
            $classes[] = 'theme-default';
        }
    } else {
        $classes[] = 'theme-default';
    }

    return $classes;
}
add_filter('body_class', 'hs_apply_theme_body_class');

// Update the theme selector to use database themes
function hs_render_theme_selector() {
    $user_id = bp_displayed_user_id();
    $themes = hs_get_available_themes();
    $current_theme = get_user_meta($user_id, 'hs_selected_theme', true) ?: 'default';
    ?>

    <h4>Select Theme</h4>
    <p>Unlock themes for reading, contributing to GRead, and accomplishing different tasks!</p>

    <div id="hs-theme-selector-feedback"></div>

    <form id="hs-theme-selector-form">
        <div class="hs-themes-grid">
            <?php foreach ($themes as $slug => $theme): ?>
                <?php
                $is_unlocked = $theme['unlocked'];

                if (!$is_unlocked && !empty($theme['unlock_metric'])) {
		$meta_key_to_check = '';
		switch ($theme['unlock_metric'])
		{
			case 'points':
				$meta_key_to_check = 'user_points';
				break;

			case 'books_read':
				$meta_key_to_check = 'hs_completed_books_count';
				break;

			case 'pages_read':
				$meta_key_to_check = 'hs_total_pages_read';
				break;

			default:
				$meta_key_to_check = 'hs_' . $theme['unlock_metric'];
		}

		$raw_val = get_user_meta($user_id, $meta_key_to_check, true);

		if (empty($raw_val) && strpos($meta_key_to_check, 'hs_') !== 0)
		{
			$raw_val = get_user_meta($user_id, 'hs_' . $meta_key_to_check, true);
		}


            $user_stat = intval($raw_val);
               if ($user_stat >= intval($theme['unlock_value'])) {
		          $is_unlocked = true;
                    }
                }
                ?>

                <div class="hs-theme-option <?php echo $is_unlocked ? '' : 'locked'; ?>">
                    <label>
                        <input type="radio" name="hs_selected_theme" value="<?php echo esc_attr($slug); ?>" <?php checked($current_theme, $slug); ?> <?php disabled(!$is_unlocked); ?>>
                        <div class="theme-preview" style="background-color: <?php echo esc_attr($theme['preview_color']); ?>"></div>
                        <span class="theme-name"><?php echo esc_html($theme['name']); ?></span>

                        <?php if (!$is_unlocked): ?>
                            <span class="theme-unlock-message"><?php echo esc_html($theme['unlock_message']); ?></span>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <?php wp_nonce_field('hs_save_theme_nonce', 'hs_theme_nonce'); ?>
        <p><input type="submit" value="Save Theme" class="button button-primary"></p>
    </form>
    <?php
}


// Make themes apply to everything (probably)
function hs_output_active_theme_style()
{
	if (!is_user_logged_in())
	{
		return;
	}

	$user_id = get_current_user_id();
	$selected = get_user_meta($user_meta, 'hs_selected_theme', true);
	$themes = hs_get_available_themes();

	if (empty($selected) || !isset($themes[$selected]))
	{
		return;
	}


	$theme = $themes[$selected];

	// Style output
	echo "<style id='hs-active-theme-var'>:root {";

	foreach ($theme as $key => $val)
	{
		if (preg_match('/color|background|border|accent/i', $key))
		{
			echo '--hs-' . esc_attr($key) . ':' . esc_attr($val) . ';';
		}
	}

	echo "}</style>";
}
//add_action('wp_head', 'hs_output_active_theme_styles', 5);


// Apply extended BuddyPress / BuddyX-wide theme CSS
function hs_generate_buddyx_theme_css() {
    if ( wp_doing_ajax() ) {
        return;
    }

    if ( ! function_exists( 'hs_get_available_themes' ) ) {
        return;
    }

    $themes = hs_get_available_themes();
    if ( empty( $themes ) || ! is_array( $themes ) ) {
        return;
    }

    // selectors we want to affect sitewide for BuddyPress / BuddyX
    $selectors = [
        // global / site
        'body.theme-%s',
        'body.theme-%s a',
        'body.theme-%s a:hover',
        'body.theme-%s .site-header',
	'body.theme-%s .site-header-wrapper',
	'body.theme-%s .site-info',
        'body.theme-%s .site-footer',
        'body.theme-%s .main-navigation',
        'body.theme-%s header',
        'body.theme-%s footer',

        // BuddyPress core
	'body.theme-%s .bp-wrap',
	'body.theme-%s .profile-fields.bp-tables-user',
	'body.theme-%s #member-primary-nav',
	'body.theme-%s .item-header-cover-image-wrapper',
	'body.theme-%s .profile.public',
	'body.theme-%s .activity-read-more',
	'body.theme-%s #item-header-content',
	'body.theme-%s #hs-search-container',
	'body.theme-%s .book-importer-form',
	'body.theme-%s .ol-modal-content',
	'body.theme-%s .hs-submission-leaderboard-container',
	'body.theme-%s .hs-site-stats-container',
	'body.theme-%s .theme-name',
	'body.theme-%s #item-header-cover-image',
	'body.theme-%s #item-header-cover-image-wrapper',
	'body.theme-%s #item-header-cover-image-wrapper::before',
	'body.theme-%s .hs-my-book',
	'body.theme-%s .bp-notify',
	'body.theme-%s .hs-sort-filter-form',
	'body.theme-%s .hs-single-book-details',
	'body.theme-%s .bp-priority-subnav-nav-items',
	'body.theme-%s .editfield',
	'body.theme-%s #wp-editor-container',
	'body.theme-%s .mce-panel',
	'body.theme-%s .mce-container',
	'body.theme-%s .message-title',
	'body.theme-%s .preview-message',
	'body.theme-%s .list-wrap',
	'body.theme-%s .bps-form',
	'body.theme-%s .bps-form-title',
	'body.theme-%s .bp-group-search-shortcode-wrapper',
	'body.theme-%s .standard-form',
	'body.theme-%s #group-create-body',
	'body.theme-%s .bp-feedback',
	'body.theme-%s .hs-search-result-item',
	'body.theme-%s .hs-no-results',
	'body.theme-%s .hs-search-loading',
	'body.theme-%s .site-sub-header',
	'body.theme-%s .wp-message_content-editor-container',
	'body.theme-%s .bp-navs',
	'body.theme-%s #user-profile-menu',
	'body.theme-%s #item-body',
	'body.theme-%s #whats-new-content',
	'body.theme-%s #whats-new-form',
        'body.theme-%s #buddypress',
        'body.theme-%s #buddypress a',
        'body.theme-%s #buddypress a:hover',
        'body.theme-%s #buddypress .member-header',
        'body.theme-%s #buddypress .item-list',
        'body.theme-%s #buddypress .activity',
        'body.theme-%s #buddypress .activity-list',
        'body.theme-%s #buddypress .activity-item',
        'body.theme-%s #buddypress .activity-avatar img',
        'body.theme-%s #buddypress .activity-content',
        'body.theme-%s #buddypress .activity-meta',
        'body.theme-%s #buddypress .activity-actions',
        'body.theme-%s #buddypress .activity-comments',
        'body.theme-%s #buddypress .bp-header',
        'body.theme-%s #buddypress .bp-widget',

        // BuddyX / theme-specific class fragments (safe fragment match)
        'body.theme-%s [class*="buddyx"]',
        'body.theme-%s [class*="buddyx"] a',
        'body.theme-%s [class*="bx-"]',
        'body.theme-%s [class*="bx-"] a',

        // widgets and general WP elements that BuddyX will style
        'body.theme-%s .widget',
        'body.theme-%s .widget a',
        'body.theme-%s .widget .widget-title',
        'body.theme-%s .widget_buddypress',
        'body.theme-%s .widget_activity',
        'body.theme-%s button',
        'body.theme-%s .btn',
        'body.theme-%s input[type="submit"]',
        'body.theme-%s button',
        'body.theme-%s .hs-button', // your plugin's buttons
        'body.theme-%s #bp-primary-action',
        'body.theme-%s .bp-secondary-action',

        // forms / inputs
        'body.theme-%s input',
        'body.theme-%s textarea',
        'body.theme-%s select',
        'body.theme-%s .bp-form',
    ];

    // Build the CSS
    $out = '<style id="hs-buddyx-theme-overrides">' . PHP_EOL;

    foreach ( $themes as $slug => $theme ) {
        // defensive: ensure color keys exist; fall back to sensible defaults
        $bg         = isset( $theme['bg_color'] ) ? $theme['bg_color'] : (isset($theme['colors']['bg']) ? $theme['colors']['bg'] : '#ffffff');
        $text       = isset( $theme['text_color'] ) ? $theme['text_color'] : (isset($theme['colors']['text']) ? $theme['colors']['text'] : '#222222');
        $link       = isset( $theme['link_color'] ) ? $theme['link_color'] : (isset($theme['colors']['link']) ? $theme['colors']['link'] : '#0073aa');
        $link_hover = isset( $theme['link_hover_color'] ) ? $theme['link_hover_color'] : (isset($theme['colors']['link_hover']) ? $theme['colors']['link_hover'] : '#005a87');
        $header_bg  = isset( $theme['header_bg'] ) ? $theme['header_bg'] : (isset($theme['colors']['header']) ? $theme['colors']['header'] : $bg);
        $widget_bg  = isset( $theme['widget_bg'] ) ? $theme['widget_bg'] : (isset($theme['colors']['widget']) ? $theme['colors']['widget'] : $bg);
        $border     = isset( $theme['border_color'] ) ? $theme['border_color'] : (isset($theme['colors']['border']) ? $theme['colors']['border'] : '#e0e0e0');
        $button_bg  = isset( $theme['button_bg'] ) ? $theme['button_bg'] : (isset($theme['colors']['button_bg']) ? $theme['colors']['button_bg'] : $link);
        $button_hover_bg = isset( $theme['button_hover_bg'] ) ? $theme['button_hover_bg'] : (isset($theme['colors']['button_hover_bg']) ? $theme['colors']['button_hover_bg'] : $link_hover);

        foreach ( $selectors as $sel ) {
            $selector = sprintf( $sel, esc_attr( $slug ) );

            // map selector -> CSS rule set (we pick what to set based on the selector group)
            // simple heuristics: global/container selectors set background/text; link selectors set color; button selectors set bg
            if ( false !== strpos( $selector, ' a:hover' ) ) {
                $out .= $selector . " { color: {$link_hover} !important; }\n";
            } elseif ( false !== strpos( $selector, '.hs-button' ) || false !== strpos( $selector, '.button' ) || false !== strpos( $selector, 'input[type=\"submit\"]' ) || false !== strpos( $selector, '.bp-primary-action' ) || false !== strpos( $selector, '.btn' ) ) {
                $out .= $selector . " { background-color: {$button_bg} !important; border-color: {$button_bg} !important; color: #fff !important; }\n";
                $out .= $selector . ":hover { background-color: {$button_hover_bg} !important; border-color: {$button_hover_bg} !important; }\n";
            } elseif ( false !== strpos( $selector, ' a' ) ) {
                $out .= $selector . " { color: {$link} !important; }\n";
            } elseif ( false !== strpos( $selector, '.site-header' ) || false !== strpos( $selector, 'header' ) || false !== strpos( $selector, '.bp-header' ) ) {
                $out .= $selector . " { background-color: {$header_bg} !important; color: {$text} !important; }\n";
            } elseif ( false !== strpos( $selector, '.widget' ) || false !== strpos( $selector, '.widget_buddypress' ) || false !== strpos( $selector, '.bp-widget' ) || false !== strpos( $selector, '.activity' ) ) {
                $out .= $selector . " { background-color: {$widget_bg} !important; color: {$text} !important; border-color: {$border} !important; }\n";
            } elseif ( false !== strpos( $selector, 'input' ) || false !== strpos( $selector, 'textarea' ) || false !== strpos( $selector, 'select' ) ) {
                $out .= $selector . " { background-color: {$bg} !important; color: {$text} !important; border-color: {$border} !important; }\n";
            } else {
                // fallback: set background + text
                $out .= $selector . " { background-color: {$bg} !important; color: {$text} !important; }\n";
            }
        }

        // small accessibility helpers for contrast on avatars / progress bars
        $out .= "body.theme-{$slug} .hs-progress-bar { background-color: " . esc_attr( $button_bg ) . " !important; }\n";
        $out .= "body.theme-{$slug} .hs-progress-bar.golden { box-shadow: 0 0 6px " . esc_attr( $button_bg ) . " !important; }\n";
    }

    $out .= '</style>' . PHP_EOL;

    echo $out;
}
add_action( 'wp_head', 'hs_generate_buddyx_theme_css', 20 );
