<?php

/**
 * Adds the "Themes" tab to the user's Settings page in BuddyPress.
 */
function gr_add_themes_tab() {
    bp_core_new_subnav_item( [
        'name'            => 'Themes',
        'slug'            => 'themes',
        'parent_url'      => bp_loggedin_user_domain() . bp_get_settings_slug() . '/',
        'parent_slug'     => bp_get_settings_slug(),
        'screen_function' => 'gr_themes_screen',
        'position'        => 20,
        'user_has_access' => bp_is_my_profile(),
    ] );
}
add_action( 'bp_setup_nav', 'gr_add_themes_tab', 100 );

/**
 * Sets up the screen content for the Themes settings page.
 */
function gr_themes_screen() {
    add_action( 'bp_template_content', 'gr_themes_content' );
    bp_core_load_template( 'members/single/plugins' );
}

/**
 * Displays the HTML form for the theme settings.
 */
function gr_themes_content() {
    $userid         = bp_displayed_user_id();
    $bg_color       = get_user_meta( $userid, 'gr_profile_bg_color', true );
    $text_color     = get_user_meta( $userid, 'gr_profile_text_color', true );
    $font           = get_user_meta( $userid, 'gr_profile_font', true );
    $bg_image_url   = get_user_meta( $userid, 'gr_profile_bg_img', true );
    ?>

    <h2>Profile Theme</h2>
    <form action="" method="post" id="gr-themes-form" enctype="multipart/form-data">

        <label for="gr-bg-color">Background Color:</label>
        <input type="text" name="gr_bg_color" id="gr-bg-color" class="gr-color-selector" value="<?php echo esc_attr( $bg_color ); ?>">

        <label for="gr-text-color">Text Color:</label>
        <input type="text" name="gr_text_color" id="gr-text-color" class="gr-color-selector" value="<?php echo esc_attr( $text_color ); ?>">

        <label for="gr-font">Font:</label>
        <select name="gr_font" id="gr-font">
            <option value="" <?php selected( $font, '' ); ?>>Theme Default</option>
            <option value="'Georgia', serif" <?php selected( $font, "'Georgia', serif" ); ?>>Georgia</option>
            <option value="'Verdana', sans-serif" <?php selected( $font, "'Verdana', sans-serif" ); ?>>Verdana</option>
        </select>

        <label for="gr-upload-bg-img">Background Image:</label>
        <input type="button" class="button" id="gr-upload-bg-img" value="Choose or upload an image">
        <input type="hidden" name="gr_bg_img" id="gr-bg-img-url" value="<?php echo esc_attr( $bg_image_url ); ?>">

        <div id="gr-bg-img-preview">
            <?php if ( $bg_image_url ) : ?>
                <img src="<?php echo esc_url( $bg_image_url ); ?>" style="max-width:200px; margin-top:10px; border: 1px solid #ddd; padding: 5px;">
            <?php endif; ?>
        </div>

        <hr>
        <?php wp_nonce_field( 'gr_themes_save' ); ?>
        <input type="submit" name="gr_themes_submit" value="Save">
    </form>
    <?php
}

/**
 * Handles saving the theme settings form data.
 * This version includes debugging messages.
 */
function gr_save_themes() {
    // Stage 1: Check if this is the correct page for saving.
    if ( ! bp_is_my_profile() || ! bp_is_settings_component() || ! bp_is_current_action( 'themes' ) ) {
        // If the page context is wrong, we don't want to do anything.
        return;
    }

    // Stage 2: Check if the form was actually submitted.
    if ( ! isset( $_POST['gr_themes_submit'] ) ) {
        // If not, do nothing.
        return;
    }

    // Stage 3: The form was submitted on the right page. Now, check the security nonce.
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'gr_themes_save' ) ) {
        // If the nonce fails, show an error and stop. This is a common point of failure.
        bp_core_add_message( 'DEBUG: Security check failed. Settings were not saved.', 'error' );
        return;
    }

    // Stage 4: If we get here, all checks have passed. Proceed with saving.
    $userid = bp_loggedin_user_id();

    // Save background color
    if ( isset( $_POST['gr_bg_color'] ) ) {
        update_user_meta( $userid, 'gr_profile_bg_color', sanitize_hex_color( $_POST['gr_bg_color'] ) );
    }

    // Save text color
    if ( isset( $_POST['gr_text_color'] ) ) {
        update_user_meta( $userid, 'gr_profile_text_color', sanitize_hex_color( $_POST['gr_text_color'] ) );
    }

    // Save background image URL
    if ( isset( $_POST['gr_bg_img'] ) ) {
        update_user_meta( $userid, 'gr_profile_bg_img', esc_url_raw( $_POST['gr_bg_img'] ) );
    }

    // Save font
    if ( isset( $_POST['gr_font'] ) ) {
        $allowed_fonts = array( "'Georgia', serif", "'Verdana', sans-serif", "" );
        $font          = in_array( $_POST['gr_font'], $allowed_fonts ) ? $_POST['gr_font'] : '';
        update_user_meta( $userid, 'gr_profile_font', $font );
    }

    // If we reach this point, the save should have worked.
    bp_core_add_message( 'DEBUG: Save process completed successfully!' );
    bp_core_redirect( bp_displayed_user_domain() . bp_get_settings_slug() . '/themes' );
}
add_action( 'bp_screens', 'gr_save_themes' );

/**
 * Applies the saved theme styles to the user's profile page header.
 */
function gr_apply_themes() {
    // Only run on a BuddyPress member's profile page
    if ( ! bp_is_user() ) {
        return;
    }

    $userid     = bp_displayed_user_id();
    $bg_color   = get_user_meta( $userid, 'gr_profile_bg_color', true );
    $text_color = get_user_meta( $userid, 'gr_profile_text_color', true );
    $font       = get_user_meta( $userid, 'gr_profile_font', true );
    $bg_img     = get_user_meta( $userid, 'gr_profile_bg_img', true );

    // If no custom styles are saved, do nothing
    if ( empty( $bg_color ) && empty( $text_color ) && empty( $font ) && empty( $bg_img ) ) {
        return;
    }

    // --- CSS SELECTORS (You may need to change these to match your theme) ---
    $body_selector    = 'body.buddypress.user-profile';
    $content_selector = '#buddypress #item-body';
    // --- END SELECTORS ---

    $css = '';

    if ( ! empty( $bg_img ) ) {
        $css .= sprintf(
            '%s { background-image: url(%s); background-size: cover; background-position: center; background-attachment: fixed; }',
            $body_selector,
            esc_url( $bg_img )
        );
    }
    
    if ( ! empty( $bg_color ) ) {
        $css .= sprintf(
            '%s { background-color: %s !important; }',
            $content_selector,
            esc_attr( $bg_color )
        );
    }
    
    if ( ! empty( $text_color ) ) {
        $css .= sprintf(
            '%1$s p, %1$s h1, %1$s h2, %1$s h3, %1$s .activity-content .activity-inner, %1$s .bp-activity-post-form p { color: %2$s !important; }',
            $content_selector,
            esc_attr( $text_color )
        );
    }
    
    if ( ! empty( $font ) ) {
        $css .= sprintf(
            '%s { font-family: %s !important; }',
            $content_selector,
            esc_attr( $font )
        );
    }

    if ( ! empty( $css ) ) {
        echo '<style type="text/css" id="gr-custom-themes">' . $css . '</style>';
    }
}
add_action( 'wp_head', 'gr_apply_themes' );

/**
 * Enqueues the necessary scripts and styles for the theme settings page.
 */
function gr_custom_enqueue_assets() {
    if ( bp_is_my_profile() && bp_is_settings_component() && bp_is_current_action( 'themes' ) ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();
        wp_enqueue_script(
            'gr-customizer-js',
            GR_CUSTOM_PLUGIN_URL . 'assets/js/main.js', // Make sure GR_CUSTOM_PLUGIN_URL is defined in your main plugin file
            [ 'jquery', 'wp-color-picker' ],
            '1.0.0',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'gr_custom_enqueue_assets' );
