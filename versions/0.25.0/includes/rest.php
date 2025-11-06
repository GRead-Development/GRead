<?php
/**
 * GRead REST API Endpoints - FIXED VERSION
 * Complete implementation with activity feed, blocking, reporting, and muting
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register custom REST API routes
function gread_register_rest_routes() {
    
    // --- Book/Library Routes ---
    register_rest_route('gread/v1', '/library', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_library',
        'permission_callback' => 'gread_check_user_permission'
    ));

    register_rest_route('gread/v1', '/library/add', array(
        'methods' => 'POST',
        'callback' => 'gread_add_book_to_library',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));

    register_rest_route('gread/v1', '/library/progress', array(
        'methods' => 'POST',
        'callback' => 'gread_update_reading_progress',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'current_page' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));

    register_rest_route('gread/v1', '/library/remove', array(
        'methods' => 'DELETE',
        'callback' => 'gread_remove_book_from_library',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));

    register_rest_route('gread/v1', '/user/(?P<id>\d+)/stats', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_stats',
        'permission_callback' => 'gread_check_user_permission'
    ));

    register_rest_route('gread/v1', '/books/search', array(
        'methods' => 'GET',
        'callback' => 'gread_search_books',
        'permission_callback' => '__return_true',
        'args' => array(
            'query' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));

    // --- User Moderation Routes ---
    register_rest_route('gread/v1', '/user/block', array(
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

    register_rest_route('gread/v1', '/user/unblock', array(
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

    register_rest_route('gread/v1', '/user/mute', array(
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

    register_rest_route('gread/v1', '/user/unmute', array(
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

    register_rest_route('gread/v1', '/user/report', array(
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

    register_rest_route('gread/v1', '/user/blocked_list', array(
        'methods' => 'GET',
        'callback' => 'gread_get_blocked_list',
        'permission_callback' => 'gread_check_user_permission'
    ));

    register_rest_route('gread/v1', '/user/muted_list', array(
        'methods' => 'GET',
        'callback' => 'gread_get_muted_list',
        'permission_callback' => 'gread_check_user_permission'
    ));

    // --- Activity Feed Route ---
    register_rest_route('gread/v1', '/activity', array(
        'methods' => 'GET',
        'callback' => 'gread_get_activity_feed',
        'permission_callback' => '__return_true',
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

// Permission check
function gread_check_user_permission() {
    return is_user_logged_in();
}

// --- Library Functions ---

function gread_get_user_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }
    
    $table_name = $wpdb->prefix . 'user_books';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error('table_not_found', 'User books table not found', array('status' => 500));
    }
    
    $user_books = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC",
        $user_id
    ));
    
    if ($wpdb->last_error) {
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }
    
    $result = array();
    
    foreach ($user_books as $user_book) {
        $book_id = $user_book->book_id;
        $book = get_post($book_id);
        
        if (!$book) continue;
        
        $result[] = array(
            'id' => intval($user_book->id),
            'book' => array(
                'id' => intval($book_id),
                'title' => get_the_title($book_id),
                'author' => get_post_meta($book_id, 'book_author', true),
                'isbn' => get_post_meta($book_id, 'book_isbn', true),
                'page_count' => intval(get_post_meta($book_id, 'nop', true)),
                'content' => get_the_content(null, false, $book)
            ),
            'current_page' => intval($user_book->current_page),
            'status' => $user_book->status
        );
    }
    
    return rest_ensure_response($result);
}

function gread_add_book_to_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    
    // Check if book exists
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Invalid book ID', array('status' => 400));
    }
    
    $table_name = $wpdb->prefix . 'user_books';
    
    // Check if already in library
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND book_id = %d",
        $user_id, $book_id
    ));
    
    if ($exists) {
        return new WP_Error('already_exists', 'Book already in library', array('status' => 400));
    }
    
    // Add to library
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
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }
    
    return rest_ensure_response(array('success' => true, 'message' => 'Book added to library'));
}

function gread_update_reading_progress($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    $current_page = intval($request['current_page']);
    
    $table_name = $wpdb->prefix . 'user_books';
    
    // Update progress
    $result = $wpdb->update(
        $table_name,
        array('current_page' => $current_page),
        array('user_id' => $user_id, 'book_id' => $book_id),
        array('%d'),
        array('%d', '%d')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }
    
    // Update user stats
    if (function_exists('hs_update_user_stats')) {
        hs_update_user_stats($user_id);
    }
    
    return rest_ensure_response(array('success' => true, 'message' => 'Progress updated'));
}

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
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }
    
    return rest_ensure_response(array('success' => true, 'message' => 'Book removed from library'));
}

function gread_get_user_stats($request) {
    $user_id = intval($request['id']);
    
    // Check if user exists
    $user = get_userdata($user_id);
    
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }
    
    $stats = array(
        'display_name' => $user->display_name,
        'points' => intval(get_user_meta($user_id, 'user_points', true)),
        'books_completed' => intval(get_user_meta($user_id, 'hs_completed_books_count', true)),
        'pages_read' => intval(get_user_meta($user_id, 'hs_total_pages_read', true)),
        'books_added' => intval(get_user_meta($user_id, 'hs_books_added_count', true)),
        'approved_reports' => intval(get_user_meta($user_id, 'hs_approved_reports_count', true))
    );
    
    return rest_ensure_response($stats);
}

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
                'content' => get_the_content(),
                'permalink' => get_permalink($book_id)
            );
        }
        wp_reset_postdata();
    }
    
    return rest_ensure_response($results);
}

// --- Activity Feed Function (FIXED) ---

function gread_get_activity_feed($request) {
    // Check if BuddyPress is active
    if (!function_exists('bp_activity_get')) {
        return new WP_Error('bp_not_active', 'BuddyPress not active', array('status' => 500));
    }
    
    $per_page = $request->get_param('per_page') ?: 20;
    $page = $request->get_param('page') ?: 1;
    $current_user_id = get_current_user_id();
    
    // Get blocked users list to filter them out
    $blocked_users = array();
    $muted_users = array();
    
    if ($current_user_id && function_exists('hs_get_blocked_users')) {
        $blocked_users = hs_get_blocked_users($current_user_id);
    }
    
    if ($current_user_id && function_exists('hs_get_muted_users')) {
        $muted_users = hs_get_muted_users($current_user_id);
    }
    
    // Merge blocked and muted users
    $excluded_users = array_unique(array_merge($blocked_users, $muted_users));
    
    // Build activity query args
    $activity_args = array(
        'object' => 'hotsoup',
        'per_page' => $per_page,
        'page' => $page,
        'display_comments' => 'stream',
        'show_hidden' => false
    );
    
    // Exclude blocked/muted users if any
    if (!empty($excluded_users)) {
        $activity_args['exclude'] = array();
        // We'll filter after retrieval since bp_activity_get doesn't have user_id exclude
    }
    
    $activities = bp_activity_get($activity_args);
    
    $response = array();
    
    if (!empty($activities['activities'])) {
        foreach ($activities['activities'] as $activity) {
            // Skip activities from blocked or muted users
            if (in_array($activity->user_id, $excluded_users)) {
                continue;
            }
            
            // Get user information
            $user = get_userdata($activity->user_id);
            $user_name = $user ? $user->display_name : 'Unknown User';
            
            // Get avatar URL
            $avatar_url = '';
            if (function_exists('bp_core_fetch_avatar')) {
                $avatar_args = array(
                    'item_id' => $activity->user_id,
                    'type' => 'thumb',
                    'html' => false
                );
                $avatar_url = bp_core_fetch_avatar($avatar_args);
            }
            
            // Check if current user has blocked this activity's author (bidirectional check)
            $is_blocked = false;
            if ($current_user_id && function_exists('hs_check_block_status')) {
                $is_blocked = hs_check_block_status($current_user_id, $activity->user_id);
            }
            
            // Skip if blocked
            if ($is_blocked) {
                continue;
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
                'date_formatted' => function_exists('bp_core_time_since') ? 
                    bp_core_time_since($activity->date_recorded) : $activity->date_recorded
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

// --- User Moderation Functions ---

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

function gread_report_user($request) {
    if (!function_exists('hs_report_user')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $reporter_id = get_current_user_id();
    $reported_id = intval($request['user_id']);
    $reason = $request['reason']; // Already sanitized by 'sanitize_callback'

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

function gread_get_blocked_list($request) {
    if (!function_exists('hs_get_blocked_users')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $user_id = get_current_user_id();
    $blocked_ids = hs_get_blocked_users($user_id);
    return rest_ensure_response(array('success' => true, 'blocked_users' => $blocked_ids));
}

function gread_get_muted_list($request) {
    if (!function_exists('hs_get_muted_users')) {
        return new WP_Error('missing_function', 'Moderation function not found.', array('status' => 500));
    }
    $user_id = get_current_user_id();
    $muted_ids = hs_get_muted_users($user_id);
    return rest_ensure_response(array('success' => true, 'muted_users' => $muted_ids));
}

// --- Additional API Enhancements ---

// Add book meta to REST API responses
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

// Ensure JWT authentication works with Authorization headers
add_filter('rest_authentication_errors', function($result) {
    if (!empty($result)) {
        return $result;
    }
    
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = $headers['Authorization'];
        }
    }
    
    return $result;
});

// Enhance JWT token response
add_filter('jwt_auth_token_before_dispatch', 'gread_enhance_jwt_response', 10, 2);

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

// Add CORS headers for mobile app
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
