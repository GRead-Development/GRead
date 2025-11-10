<?php
/**
 * GRead REST API Endpoints
 * Add this to your hotsoup.php file or create a new file in includes/
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register custom REST API routes
function gread_register_rest_routes() {
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
}
add_action('rest_api_init', 'gread_register_rest_routes');

// Permission check
function gread_check_user_permission() {
    return is_user_logged_in();
}

// Get user's library
function gread_get_user_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'user_books';
    
    $user_books = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    $response = array();
    
    foreach ($user_books as $user_book) {
        $book_post = get_post($user_book->book_id);
        
        if (!$book_post) {
            continue;
        }
        
        $book_data = array(
            'id' => $user_book->id,
            'book_id' => $user_book->book_id,
            'current_page' => intval($user_book->current_page),
            'status' => $user_book->status,
            'book' => array(
                'id' => $book_post->ID,
                'title' => get_the_title($book_post),
                'author' => get_post_meta($book_post->ID, 'book_author', true),
                'isbn' => get_post_meta($book_post->ID, 'book_isbn', true),
                'page_count' => intval(get_post_meta($book_post->ID, 'nop', true)),
                'permalink' => get_permalink($book_post->ID)
            )
        );
        
        $response[] = $book_data;
    }
    
    return rest_ensure_response($response);
}

// Add book to library
function gread_add_book_to_library($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    $table_name = $wpdb->prefix . 'user_books';
    
    // Check if book exists
    $book_post = get_post($book_id);
    if (!$book_post || $book_post->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found', array('status' => 404));
    }
    
    // Check if already in library
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
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
        return new WP_Error('db_error', 'Failed to add book to library', array('status' => 500));
    }
    
    // Update user stats
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

// Update reading progress
function gread_update_reading_progress($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $book_id = intval($request['book_id']);
    $current_page = intval($request['current_page']);
    $table_name = $wpdb->prefix . 'user_books';
    
    // Validate book exists in user's library
    $user_book = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));
    
    if (!$user_book) {
        return new WP_Error('not_in_library', 'Book not in library', array('status' => 404));
    }
    
    // Get total pages
    $total_pages = intval(get_post_meta($book_id, 'nop', true));
    
    // Validate current page
    if ($current_page < 0 || ($total_pages > 0 && $current_page > $total_pages)) {
        return new WP_Error('invalid_page', 'Invalid page number', array('status' => 400));
    }
    
    // Update progress
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
    
    // Update user stats
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

// Remove book from library
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
    
    // Update user stats
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

// Get user stats
function gread_get_user_stats($request) {
    $user_id = intval($request['id']);
    
    // Check if requesting own stats or is admin
    $current_user_id = get_current_user_id();
    if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Cannot access other user stats', array('status' => 403));
    }
    
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

// Search books
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
