<?php
/**
 * GRead REST API Endpoints
 *
 * This file consolidates all REST API functionality for the GRead plugin,
 * providing endpoints for library management, user statistics, moderation,
 * and activity feeds.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all custom REST API routes for GRead.
 */
function gread_register_rest_routes() {

    $namespace = 'gread/v1';

    // Get User Library
    register_rest_route($namespace, '/library', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_library',
        'permission_callback' => 'gread_check_user_permission'
    ));

    // Add Book to Library
    register_rest_route($namespace, '/library/add', array(
        'methods' => 'POST',
        'callback' => 'gread_add_book_to_library',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Update Reading Progress
    register_rest_route($namespace, '/library/progress', array(
        'methods' => 'POST',
        'callback' => 'gread_update_reading_progress',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            ),
            'current_page' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Remove Book from Library
    register_rest_route($namespace, '/library/remove', array(
        'methods' => 'DELETE',
        'callback' => 'gread_remove_book_from_library',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Get User Stats/Profile
    register_rest_route($namespace, '/user/(?P<id>\d+)/stats', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_stats',
        'permission_callback' => 'gread_check_user_permission' // User must be logged in to view stats
    ));

    // Search Books
    register_rest_route($namespace, '/books/search', array(
        'methods' => 'GET',
        'callback' => 'gread_search_books',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => array(
            'query' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));

    // Block User
    register_rest_route($namespace, '/user/block', array(
        'methods' => 'POST',
        'callback' => 'gread_block_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'user_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Unblock User
    register_rest_route($namespace, '/user/unblock', array(
        'methods' => 'POST',
        'callback' => 'gread_unblock_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'user_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Mute User
    register_rest_route($namespace, '/user/mute', array(
        'methods' => 'POST',
        'callback' => 'gread_mute_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'user_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Unmute User
    register_rest_route($namespace, '/user/unmute', array(
        'methods' => 'POST',
        'callback' => 'gread_unmute_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'user_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            )
        )
    ));

    // Report User
    register_rest_route($namespace, '/user/report', array(
        'methods' => 'POST',
        'callback' => 'gread_report_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'user_id' => array(
                'required' => true,
                'validate_callback' => 'is_numeric'
            ),
            'reason' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_textarea_field'
            )
        )
    ));

    // Get Blocked List
    register_rest_route($namespace, '/user/blocked_list', array(
        'methods' => 'GET',
        'callback' => 'gread_get_blocked_list',
        'permission_callback' => 'gread_check_user_permission'
    ));

    // Get Muted List
    register_rest_route($namespace, '/user/muted_list', array(
        'methods' => 'GET',
        'callback' => 'gread_get_muted_list',
        'permission_callback' => 'gread_check_user_permission'
    ));

    // Get Activity Feed
    register_rest_route($namespace, '/activity', array(
        'methods' => 'GET',
        'callback' => 'gread_get_activity_feed',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => array(
            'per_page' => array(
                'default' => 20,
                'sanitize_callback' => 'absint'
            ),
            'page' => array(
                'default' => 1,
                'sanitize_callback' => 'absint'
            )
        )
    ));
}
add_action('rest_api_init', 'gread_register_rest_routes');

/**
 * Permission check callback.
 * Ensures the user is logged in.
 */
function gread_check_user_permission() {
    return is_user_logged_in();
}

/**
 * API Callback: Get User's Library
 * GET /gread/v1/library
 */
function gread_get_user_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }
    
    $table_name = $wpdb->prefix . 'user_books';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error('table_not_found', 'User books table not found', array('status' => 500));
    }
    
    $user_books = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC",
        $user_id
    ));
    
    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Database error', array('status' => 500));
    }
    
    $response = array();
    
    foreach ($user_books as $user_book) {
        $book_post = get_post($user_book->book_id);
        
        if (!$book_post || $book_post->post_status !== 'publish') {
            continue;
        }
        
        $author = get_post_meta($book_post->ID, 'book_author', true);
        $isbn = get_post_meta($book_post->ID, 'book_isbn', true);
        $page_count = intval(get_post_meta($book_post->ID, 'nop', true));
        
        $book_data = array(
            'id' => intval($user_book->id),
            'book_id' => intval($user_book->book_id),
            'current_page' => intval($user_book->current_page),
            'status' => $user_book->status,
            'book' => array(
                'id' => intval($book_post->ID),
                'title' => get_the_title($book_post),
                'author' => !empty($author) ? $author : 'Unknown Author',
                'isbn' => $isbn,
                'page_count' => $page_count,
                'permalink' => get_permalink($book_post->ID),
            )
        );
        
        $response[] = $book_data;
    }
    
    return rest_ensure_response($response);
}

/**
 * API Callback: Add Book to Library
 * POST /gread/v1/library/add
 */
function gread_add_book_to_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    $table_name = $wpdb->prefix . 'user_books';
    
    $book_post = get_post($book_id);
    if (!$book_post || $book_post->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found', array('status' => 404));
    }
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));
    
    if ($exists) {
        return new WP_Error('already_exists', 'Book already in library', array('status' => 400));
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'book_id' => $book_id,
            'current_page' => 0,
            'status' => 'reading'
        ),
        array('%d', '%d', '%d', '%s')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to add book to library', array('status' => 500));
    }
    
    if (function_exists('hs_increment_books_added')) {
        hs_increment_books_added($user_id);
    }
    
    if (function_exists('hs_update_user_stats')) {
        hs_update_user_stats($user_id);
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Book added to library'
    ));
}

/**
 * API Callback: Update Reading Progress
 * POST /gread/v1/library/progress
 */
function gread_update_reading_progress($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    $current_page = intval($request['current_page']);
    $table_name = $wpdb->prefix . 'user_books';
    
    $user_book = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));
    
    if (!$user_book) {
        return new WP_Error('not_in_library', 'Book not in library', array('status' => 404));
    }
    
    $total_pages = intval(get_post_meta($book_id, 'nop', true));
    
    if ($current_page < 0) {
        $current_page = 0;
    }

    if ($total_pages > 0 && $current_page > $total_pages) {
        $current_page = $total_pages;
    }
    
    $result = $wpdb->update(
        $table_name,
        array('current_page' => $current_page),
        array('user_id' => $user_id, 'book_id' => $book_id),
        array('%d'),
        array('%d', '%d')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to update progress', array('status' => 500));
    }
    
    if (function_exists('hs_update_user_stats')) {
        hs_update_user_stats($user_id);
    }
    
    $is_completed = ($total_pages > 0 && $current_page >= $total_pages);
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Progress updated',
        'current_page' => $current_page,
        'is_completed' => $is_completed
    ));
}

/**
 * API Callback: Remove Book from Library
 * DELETE /gread/v1/library/remove
 */
function gread_remove_book_from_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    $table_name = $wpdb->prefix . 'user_books';
    
    $result = $wpdb->delete(
        $table_name,
        array('user_id' => $user_id, 'book_id' => $book_id),
        array('%d', '%d')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to remove book', array('status' => 500));
    }
    
    if (function_exists('hs_decrement_books_added')) {
        hs_decrement_books_added($user_id);
    }
    
    if (function_exists('hs_update_user_stats')) {
        hs_update_user_stats($user_id);
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Book removed from library'
    ));
}

/**
 * API Callback: Get User Stats
 * GET /gread/v1/user/(?P<id>\d+)/stats
 */
function gread_get_user_stats($request) {
    $user_id = intval($request['id']);
    $user = get_userdata($user_id);
    
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }
    
    $avatar_url = '';
    if (function_exists('bp_core_fetch_avatar')) {
        $avatar_url = bp_core_fetch_avatar(array(
            'item_id' => $user_id,
            'type' => 'full',
            'html' => false
        ));
    }
    
    $stats = array(
        'user_id' => $user_id,
        'display_name' => $user->display_name,
        'avatar_url' => $avatar_url,
        'points' => intval(get_user_meta($user_id, 'user_points', true)),
        'books_completed' => intval(get_user_meta($user_id, 'hs_completed_books_count', true)),
        'pages_read' => intval(get_user_meta($user_id, 'hs_total_pages_read', true)),
        'books_added' => intval(get_user_meta($user_id, 'hs_books_added_count', true)),
        'approved_reports' => intval(get_user_meta($user_id, 'hs_approved_reports_count', true))
    );
    
    return rest_ensure_response($stats);
}

/**
 * API Callback: Search Books
 * GET /gread/v1/books/search
 */
function gread_search_books($request) {
    $query = sanitize_text_field($request['query']);
    
    if (strlen($query) < 3) {
        return rest_ensure_response(array());
    }
    
    $args = array(
        'post_type' => 'book',
        'post_status' => 'publish',
        'posts_per_page' => 20,
        's' => $query
    );
    
    $books_query = new WP_Query($args);
    $results = array();
    
    if ($books_query->have_posts()) {
        while ($books_query->have_posts()) {
            $books_query->the_post();
            $book_id = get_the_ID();
            
            $results[] = array(
                'id' => $book_id,
                'title' => get_the_title(),
                'author' => get_post_meta($book_id, 'book_author', true),
                'isbn' => get_post_meta($book_id, 'book_isbn', true),
                'page_count' => intval(get_post_meta($book_id, 'nop', true)),
                'permalink' => get_permalink($book_id)
            );
        }
        wp_reset_postdata();
    }
    
    return rest_ensure_response($results);
}

/**
 * API Callback: Block User
 * POST /gread/v1/user/block
 */
function gread_block_user($request) {
    if (!function_exists('hs_block_user')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $actor_id = get_current_user_id();
    $target_id = intval($request['user_id']);
    
    if ($actor_id == $target_id) {
        return new WP_Error('invalid_target', 'Cannot block yourself.', array('status' => 400));
    }

    if (hs_block_user($actor_id, $target_id)) {
        return rest_ensure_response(array('success' => true, 'message' => 'User blocked.'));
    }
    return new WP_Error('action_failed', 'Could not block user.', array('status' => 500));
}

/**
 * API Callback: Unblock User
 * POST /gread/v1/user/unblock
 */
function gread_unblock_user($request) {
    if (!function_exists('hs_unblock_user')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $actor_id = get_current_user_id();
    $target_id = intval($request['user_id']);

    if (hs_unblock_user($actor_id, $target_id)) {
        return rest_ensure_response(array('success' => true, 'message' => 'User unblocked.'));
    }
    return new WP_Error('action_failed', 'Could not unblock user.', array('status' => 500));
}

/**
 * API Callback: Mute User
 * POST /gread/v1/user/mute
 */
function gread_mute_user($request) {
    if (!function_exists('hs_mute_user')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $actor_id = get_current_user_id();
    $target_id = intval($request['user_id']);

    if ($actor_id == $target_id) {
        return new WP_Error('invalid_target', 'Cannot mute yourself.', array('status' => 400));
    }

    if (hs_mute_user($actor_id, $target_id)) {
        return rest_ensure_response(array('success' => true, 'message' => 'User muted.'));
    }
    return new WP_Error('action_failed', 'Could not mute user.', array('status' => 500));
}

/**
 * API Callback: Unmute User
 * POST /gread/v1/user/unmute
 */
function gread_unmute_user($request) {
    if (!function_exists('hs_unmute_user')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $actor_id = get_current_user_id();
    $target_id = intval($request['user_id']);

    if (hs_unmute_user($actor_id, $target_id)) {
        return rest_ensure_response(array('success' => true, 'message' => 'User unmuted.'));
    }
    return new WP_Error('action_failed', 'Could not unmute user.', array('status' => 500));
}

/**
 * API Callback: Report User
 * POST /gread/v1/user/report
 */
function gread_report_user($request) {
    if (!function_exists('hs_report_user')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $reporter_id = get_current_user_id();
    $reported_id = intval($request['user_id']);
    $reason = $request['reason']; // Already sanitized

    if ($reporter_id == $reported_id) {
        return new WP_Error('invalid_target', 'Cannot report yourself.', array('status' => 400));
    }
    
    if (empty(trim($reason))) {
        return new WP_Error('reason_required', 'A reason is required to submit a report.', array('status' => 400));
    }

    if (hs_report_user($reporter_id, $reported_id, $reason)) {
        return rest_ensure_response(array('success' => true, 'message' => 'User reported. Thank you.'));
    }
    return new WP_Error('action_failed', 'Could not submit report.', array('status' => 500));
}

/**
 * API Callback: Get Blocked List
 * GET /gread/v1/user/blocked_list
 */
function gread_get_blocked_list($request) {
    if (!function_exists('hs_get_blocked_users')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $user_id = get_current_user_id();
    $blocked_ids = hs_get_blocked_users($user_id);
    return rest_ensure_response(array('success' => true, 'blocked_users' => $blocked_ids));
}

/**
 * API Callback: Get Muted List
 * GET /gread/v1/user/muted_list
 */
function gread_get_muted_list($request) {
    if (!function_exists('hs_get_muted_users')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $user_id = get_current_user_id();
    $muted_ids = hs_get_muted_users($user_id);
    return rest_ensure_response(array('success' => true, 'muted_users' => $muted_ids));
}

/**
 * API Callback: Get Activity Feed
 * GET /gread/v1/activity
 */
function gread_get_activity_feed($request) {
    if (!function_exists('bp_activity_get')) {
        return new WP_Error('bp_not_active', 'BuddyPress not active', array('status' => 500));
    }
    
    $per_page = $request->get_param('per_page') ?: 20;
    $page = $request->get_param('page') ?: 1;
    
    $activities = bp_activity_get(array(
        'object' => 'hotsoup',
        'per_page' => $per_page,
        'page' => $page,
        'display_comments' => 'stream',
        'show_hidden' => false
    ));
    
    $response = array();
    
    if (!empty($activities['activities'])) {
        foreach ($activities['activities'] as $activity) {
            $user = get_userdata($activity->user_id);
            $user_name = $user ? $user->display_name : 'Unknown User';
            
            $avatar_url = '';
            if (function_exists('bp_core_fetch_avatar')) {
                $avatar_args = array(
                    'item_id' => $activity->user_id,
                    'type' => 'thumb',
                    'html' => false
                );
                $avatar_url = bp_core_fetch_avatar($avatar_args);
            }
            
            $item = array(
                'id' => intval($activity->id),
                'user_id' => intval($activity->user_id),
                'user_name' => $user_name,
                'avatar_url' => $avatar_url,
                'content' => $activity->content,
                'action' => $activity->action,
                'type' => $activity->type,
                'date' => $activity->date_recorded,
                'date_formatted' => bp_core_time_since($activity->date_recorded)
            );
            
            $response[] = $item;
        }
    }
    
    return rest_ensure_response(array(
        'activities' => $response,
        'total' => $activities['total'],
        'has_more' => $activities['total'] > ($page * $per_page)
    ));
}

/**
 * Add book meta to REST API 'book' post type responses.
 */
function gread_add_book_meta_to_api() {
    register_rest_field('book', 'book_meta', array(
        'get_callback' => function($post) {
            return array(
                'author' => get_post_meta($post['id'], 'book_author', true),
                'isbn' => get_post_meta($post['id'], 'book_isbn', true),
                'page_count' => intval(get_post_meta($post['id'], 'nop', true)),
                'publication_year' => get_post_meta($post['id'], 'publication_year', true),
                'average_rating' => floatval(get_post_meta($post['id'], 'hs_average_rating', true)),
                'review_count' => intval(get_post_meta($post['id'], 'hs_review_count', true))
            );
        },
        'schema' => null
    ));
}
add_action('rest_api_init', 'gread_add_book_meta_to_api');

/**
 * Ensure JWT authentication works with Authorization headers.
 */
add_filter('rest_authentication_errors', function($result) {
    if (!empty($result)) {
        return $result;
    }
    
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = function_exists('getallheaders') ? getallheaders() : array();
        if (isset($headers['Authorization'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = $headers['Authorization'];
        }
    }
    
    return $result;
});

/**
 * Enhance JWT token response with user data.
 */
function gread_enhance_jwt_response($data, $user) {
    if (!isset($data['user_id'])) {
        $data['user_id'] = $user->ID;
    }
    if (!isset($data['user_display_name'])) {
        $data['user_display_name'] = $user->display_name;
    }
    if (!isset($data['user_nicename'])) {
        $data['user_nicename'] = $user->user_nicename;
    }
    
    return $data;
}
add_filter('jwt_auth_token_before_dispatch', 'gread_enhance_jwt_response', 10, 2);

/**
 * Add broad CORS headers for mobile app development.
 */
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        return $value;
    });
}, 15);

?>
