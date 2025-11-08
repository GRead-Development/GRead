<?php

// This stores information about what themes are available to be unlocked, and how they are unlocked, respectively.

function hs_get_available_themes()
{
	return [

		'default' => [
			// Theme's name
			'name' => 'Default',
			// Theme's CSS class
			'css_class' => 'theme-default',
			// Theme's preview color
			'preview_color' => '#0073aa',
			// Theme is always unlocked
			'unlocked' => true,
		],

		'dark_mode' => [
			'name' => 'Dark Mode',
			'css_class' => 'theme-dark-mode',
			'preview_color' => '#343a40',
			'unlock_metric' => 'total_pages_read',
			'unlock_value' => 1000,
			'unlock_message' => 'Read 1,000 pages to unlock this theme.',
		],
	];
}


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


function hs_apply_theme_body_class($classes)
{
	if (is_user_logged_in())
	{
		$user_id = get_current_user_id();
		$selected_theme = get_user_meta($user_id, 'hs_selected_theme', true);
		$themes = hs_get_available_themes();

		if (!empty($selected_theme) && isset($themes[$selected_theme]))
		{
			$classes[] = esc_attr($themes[$selected_theme]['css_class']);
		}

		else
		{
			$classes[] = 'theme-default';
		}
	}

	else
	{
		$classes[] = 'theme-default';
	}

	return $classes;
}
add_filter('body_class', 'hs_apply_theme_body_class');


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


// Render the form for the user to select a theme
function hs_render_theme_selector()
{
	$user_id = bp_displayed_user_id();
	$themes = hs_get_available_themes();
	$current_theme = get_user_meta($user_id, 'hs_selected_theme', true) ?: 'default';
	?>

	<h4>Select Theme</h4>
	<p>Unlock themes for reading, contributing to GRead, and accomplishing different tasks!</p>

	<div id="hs-theme-selector-feedback"></div>

	<form id="hs-theme-selector-form">
		<div class="hs-themes-grid">
			<?php foreach ($themes as $slug => $theme) : ?>
				<?php
				$is_unlocked = false;

				if (isset($theme['unlocked']) && $theme['unlocked'])
				{
					$is_unlocked = true;
				}

				else
				{
					$user_stat = get_user_meta($user_id, 'hs_' . $theme['unlock_metric'], true) ?: 0;

					if ($user_stat >= $theme['unlock_value'])
					{
						$is_unlocked = true;
					}
				}
			?>

			<div class="hs-theme-option <?php echo $is_unlocked ? '' : 'locked'; ?>">
				<label>
					<input type="radio" name="hs_selected_theme" value="<?php echo esc_attr($slug); ?>" <?php checked($current_theme, $slug); ?> <?php disabled(!$is_unlocked); ?>>
					<div class="theme-preview" style="background-color: <?php echo esc_attr($theme['preview_color']); ?>"></div>
					<span class="theme-name"><?php echo esc_html($theme['name']); ?></span>

					<?php if (!$is_unlocked) : ?>
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


// AJAX handler for changing user's theme
function hs_save_user_theme_callback()
{
	check_ajax_referer('hs_save_theme_nonce', 'hs_theme_nonce');

	if (!is_user_logged_in() || !isset($_POST['selected_theme']))
	{
		wp_send_json_error(['message' => 'Invalid request.']);
	}

	$user_id = get_current_user_id();
	$selected_slug = sanitize_key($_POST['selected_theme']);
	$themes = hs_get_available_themes();

	if (!isset($themes[$selected_slug]))
	{
		wp_send_json_error(['message' => 'Invalid theme selection.']);
	}

	$theme = $themes[$selected_slug];
	$is_unlocked = false;

	if (isset($theme['unlocked']) && $theme['unlocked'])
	{
		$is_unlocked = true;
	}

	else
	{
		$user_stat = get_user_meta($user_id, 'hs_' . $theme['unlock_metric'], true) ?: 0;

		if ($user_stat >= $theme['unlock_value'])
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
