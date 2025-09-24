<?php

function gr_add_themes_tab()
{
	bp_core_new_subnav_item([
		'name' => 'Themes',
		'slug' => 'themes',
		'parent_url' => bp_loggedin_user_domain() . bp_get_settings_slug() . '/',
		'parent_slug' => bp_get_settings_slug(),
		'screen_function' => 'gr_themes_screen',
		'position' => 20,
		'user_has_access' => bp_is_my_profile()
	]);
}

add_action('bp_setup_nav', 'gr_add_themes_tab', 100);

function gr_themes_screen()
{
	add_action('bp_template_content', 'gr_themes_content');
	bp_core_load_template ('members/single/plugins');
}

function gr_themes_content()
{
    $userid = bp_displayed_user_id();
    $bg_color = get_user_meta($userid, 'gr_profile_bg_color', true);
    $text_color = get_user_meta($userid, 'gr_profile_text_color', true );
    $font = get_user_meta($userid, 'gr_profile_font', true);
    $bg_image_url = get_user_meta($userid, 'gr_profile_bg_img', true);
    ?>


    <h2>Profile Theme</h2>
    <form action="" method="post" id="gr-themes-form" enctype="multipart/form-data">
        <label for="gr-bg-color">Background Color:</label>
        <input type="text" name="gr_bg_color" class="gr-color-selector" value="<?php echo esc_attr($bg_color);?>">

        <label for="gr-text-color">Text Color:</label>
        <input type="text" name="gr_text_color" class="gr-color-selector" value="<?php echo esc_attr($text_color);?>">

        <label for="gr-font">Font:</label>
        <select name="gr_font" id="gr-font">
            <option value="" <?php selected($font, ''); ?>>Theme Default</option>
            <option value="'Georgia', serif" <?php selected( $font, "'Georgia', serif"); ?>>Georgia</option>
            <option value="'Verdana', sans-serif" <?php selected($font, "'Verdana', sans-serif"); ?>>Verdana</option>
        </select>

        <label for="gr-bg-img">Background Image:</label>
        <input type="button" class="button" id="gr-upload-bg-img" value="Choose or upload an image">
        <input type="hidden" name="gr_bg_img" id="gr-bg-img-url" value="<?php echo esc_attr($bg_image_url); ?>">

        <div id="gr-bg-img-preview">
            <?php if ($bg_image_url): ?>
                <img src="<?php echo esc_url($bg_image_url); ?>" style="max-width:200px; margin-top:10px; border: 1px solid #ddd; padding: 5px;">
            <?php endif; ?>
        </div>

        <hr>
        <?php wp_nonce_field('gr_themes_save');?>
        <input type="submit" name="gr_themes_submit" value="Save">
    </form>
    <?php
}

function gr_save_themes()
{
    // First, check if we are on the correct screen to process this form.
    if ( ! bp_is_my_profile() || ! bp_is_settings_component() || ! bp_is_current_action( 'themes' ) ) {
        return;
    }

    // Next, check if the form was submitted and the security nonce is valid.
    if ( isset( $_POST['gr_themes_submit'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'gr_themes_save' ) ) {
        
        $userid = bp_loggedin_user_id();

        // Sanitize and save Background Color
        if ( isset( $_POST['gr_bg_color'] ) ) {
            update_user_meta( $userid, 'gr_profile_bg_color', sanitize_hex_color( $_POST['gr_bg_color'] ) );
        }

        // Sanitize and save Text Color
        if ( isset( $_POST['gr_text_color'] ) ) {
            update_user_meta( $userid, 'gr_profile_text_color', sanitize_hex_color( $_POST['gr_text_color'] ) );
        }
        
        // Sanitize and save Background Image URL
        if ( isset( $_POST['gr_bg_img'] ) ) {
            update_user_meta( $userid, 'gr_profile_bg_img', esc_url_raw( $_POST['gr_bg_img'] ) );
        }

        // Sanitize and save Font
        if ( isset( $_POST['gr_font'] ) ) {
            $allowed_fonts = array( "'Georgia', serif", "'Verdana', sans-serif", "" );
            $font          = in_array( $_POST['gr_font'], $allowed_fonts ) ? $_POST['gr_font'] : '';
            update_user_meta( $userid, 'gr_profile_font', $font );
        }

        // Add a success message and redirect the user.
        bp_core_add_message( 'Your theme has been saved.' );
        bp_core_redirect( bp_displayed_user_domain() . bp_get_settings_slug() . '/themes' );
    }
}
// Use the 'bp_screens' hook instead of 'bp_actions'
add_action( 'bp_screens', 'gr_save_themes' );


function gr_apply_themes()
{
	if (!bp_is_user())
	{
		return;
	}

	$userid = bp_displayed_user_id();
	$bg_color = get_user_meta($userid,'gr_profile_bg_color', true);
	$text_color = get_user_meta($userid, 'gr_profile_text_color', true);
	$font = get_user_meta($userid, 'gr_profile_font', true);
	$bg_img = get_user_meta($userid, 'gr_profile_bg_img', true);

	if (empty($bg_color) && empty($text_color) && empty($font) && empty($bg_img))
	{
		return;
	}

	$css_selector = '#buddypress #item-body';
	$css = '';

	if (!empty($bg_color)) $css .= esc_html($css_selector) . '{ background-color: ' . esc_attr($bg_color) . '!important; }';
	if (!empty($text_color)) $css .= esc_html($css_selector) . 'p, ' . esc_html($css_selector) . 'h1,' . esc_html($css_selector) . 'h2, ' . esc_html($css_selector) . 'h3 { color: ' . esc_attr($text_color) . '!important; }';
	if (!empty($font)) $css .= esc_html($css_selector) . ' { font-family: ' . esc_attr($font) . ' !important; }';
	if (!empty($bg_img))
	{
		$css .= 'body.buddypress.user-profile
			{
				background-image: url(' . esc_url($bg_img) . ');
				background-size: cover;
				background-position: center;
				background-attachment: fixed;
			}';
		}

	if (!empty($css))
	{
		echo '<style type="text/css" id="gr-custom-themes">' . $css . '</style>';
	}
}

add_action('wp_head', 'gr_apply_themes');

function gr_custom_enqueue_assets()
{
	if (bp_is_my_profile() && bp_is_settings_component() && bp_is_current_action('themes'))
	{
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_media();
		wp_enqueue_script('gr-customizer-js',
		GR_CUSTOM_PLUGIN_URL . 'assets/js/main.js',
		['jquery', 'wp-color-picker'],
		'1.0.0',
		true);
	}
}

add_action('wp_enqueue_scripts', 'gr_custom_enqueue_assets');
