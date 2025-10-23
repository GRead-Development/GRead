<?php

function hs_debug_stats_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to see this.';
    }
    
    $user_id = get_current_user_id();
    $completed_count = get_user_meta($user_id, 'hs_completed_books_count', true);
    $pages_read = get_user_meta($user_id, 'hs_total_pages_read', true);

    $output = '<h3>Debug Stats for User ID: ' . esc_html($user_id) . '</h3>';
    $output .= '<ul>';
    $output .= '<li><strong>Completed Books Meta (hs_completed_books_count):</strong> ' . esc_html(var_export($completed_count, true)) . '</li>';
    $output .= '<li><strong>Pages Read Meta (hs_total_pages_read):</strong> ' . esc_html(var_export($pages_read, true)) . '</li>';
    $output .= '</ul>';
    $output .= '<p><em>If you see numbers here after updating a book, the data is saving correctly. If you see empty or `NULL` values, the data is not saving.</em></p>';
    
    return $output;
}
add_shortcode('hs_debug_stats', 'hs_debug_stats_shortcode');
