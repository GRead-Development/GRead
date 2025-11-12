<?php
// Register the ISBN lookup endpoint
add_action('rest_api_init', function() {
    register_rest_route('gread/v1', '/books/isbn', array(
        'methods' => 'GET',
        'callback' => 'gread_handle_isbn_lookup',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'isbn' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description' => 'ISBN number to search for'
            )
        )
    ));
});

/**
 * Handle ISBN lookup and book import
 */
function gread_handle_isbn_lookup($request) {
    $isbn = $request->get_param('isbn');

    // Clean ISBN
    $clean_isbn = preg_replace('/[^0-9X-]/', '', $isbn);
    if (empty($clean_isbn)) {
        return new WP_Error(
            'invalid_isbn',
            'Invalid ISBN format',
            array('status' => 400)
        );
    }

    // Check if book already exists in database
    $existing_book = gread_find_book_by_isbn($clean_isbn);
    if ($existing_book) {
        return gread_format_book_response($existing_book);
    }

    // Query OpenLibrary API
    $openlibrary_data = gread_query_openlibrary($clean_isbn);
    if (is_wp_error($openlibrary_data)) {
        return $openlibrary_data;
    }

    // Create book post
    $book_post_id = gread_create_book_post($openlibrary_data, $clean_isbn);
    if (is_wp_error($book_post_id)) {
        return $book_post_id;
    }

    // Update user's book count and statistics
    $user_id = get_current_user_id();
    if ($user_id && function_exists('hs_increment_books_added')) {
        hs_increment_books_added($user_id);
        if (function_exists('hs_update_user_stats')) {
            hs_update_user_stats($user_id);
        }
    }
    
    // Return the created book
    $book = gread_get_book_by_post_id($book_post_id);
    return gread_format_book_response($book);
}

/**
 * Search for existing book by ISBN
 * Fixed to match admin importer's field name
 */
function gread_find_book_by_isbn($isbn) {
    $args = array(
        'post_type' => 'book',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => 'book_isbn',  // Fixed: matches admin importer
                'value' => $isbn,
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return $query->posts[0];
    }

    return null;
}

/**
 * Query OpenLibrary API for book data
 */
function gread_query_openlibrary($isbn) {
    $url = sprintf('https://openlibrary.org/api/books?bibkeys=ISBN:%s&format=json&jscmd=data', $isbn);

    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'user-agent' => 'GRead-App/1.0'
    ));

    if (is_wp_error($response)) {
        return new WP_Error(
            'openlibrary_error',
            'Failed to connect to OpenLibrary API',
            array('status' => 500)
        );
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data)) {
        return new WP_Error(
            'book_not_found',
            'Book not found in OpenLibrary database',
            array('status' => 404)
        );
    }

    // Extract the first result
    $book_data = reset($data);
    if (!is_array($book_data)) {
        return new WP_Error(
            'invalid_openlibrary_response',
            'Invalid response from OpenLibrary',
            array('status' => 500)
        );
    }

    return $book_data;
}

/**
 * Create a book post in WordPress from OpenLibrary data
 * Fixed to match admin importer's approach using ACF update_field()
 */
function gread_create_book_post($book_data, $isbn) {
    // Extract data from OpenLibrary response
    $title = isset($book_data['title']) ? sanitize_text_field($book_data['title']) : 'Unknown Book';
    
    // Get description - try notes field first, then excerpts
    $description = '';
    if (isset($book_data['notes'])) {
        $description = sanitize_textarea_field($book_data['notes']);
    } elseif (isset($book_data['excerpts']) && is_array($book_data['excerpts']) && !empty($book_data['excerpts'])) {
        $description = wp_kses_post($book_data['excerpts'][0]['text'] ?? '');
    }
    if (empty($description)) {
        $description = 'No description available.';
    }

    // Create the book post
    $post_args = array(
        'post_type' => 'book',
        'post_title' => $title,
        'post_content' => $description,
        'post_status' => 'publish',
        'post_name' => $isbn,
        'post_author' => get_current_user_id()
    );

    $post_id = wp_insert_post($post_args);
    
    if (is_wp_error($post_id)) {
        return new WP_Error(
            'book_creation_failed',
            'Failed to create book post',
            array('status' => 500)
        );
    }

    // Use ACF update_field() to match admin importer
    update_field('book_isbn', $isbn, $post_id);

    // Handle authors
    if (isset($book_data['authors']) && is_array($book_data['authors']) && !empty($book_data['authors'])) {
        $author_names = array_map(function($author) {
            return sanitize_text_field($author['name']);
        }, $book_data['authors']);
        update_field('book_author', implode(', ', $author_names), $post_id);
    }

    // Handle publication year
    if (isset($book_data['publish_date'])) {
        preg_match('/(\d{4})/', $book_data['publish_date'], $matches);
        $year = $matches[0] ?? null;
        if ($year) {
            update_field('publication_year', intval($year), $post_id);
        }
    }

    // Handle page count
    if (isset($book_data['number_of_pages'])) {
        update_field('nop', intval($book_data['number_of_pages']), $post_id);
    }

    // Handle cover image
    if (isset($book_data['cover']['large'])) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $image_url = $book_data['cover']['large'];
        $attachment_id = media_sideload_image($image_url, $post_id, $title, 'id');

        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    // Update user statistics
    $user_id = get_current_user_id();
    if ($user_id && function_exists('hs_increment_books_added')) {
        hs_increment_books_added($user_id);
        if (function_exists('hs_update_user_stats')) {
            hs_update_user_stats($user_id);
        }
    }

    return $post_id;
}

/**
 * Retrieve book by post ID
 */
function gread_get_book_by_post_id($post_id) {
    return get_post($post_id);
}

/**
 * Format book post for API response
 * Fixed to use correct ACF field names
 */
function gread_format_book_response($post) {
    if (!$post) {
        return null;
    }

    $cover_url = null;
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    if ($thumbnail_id) {
        $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
        if ($thumbnail_url) {
            $cover_url = $thumbnail_url;
        }
    }

    // Get ACF fields using get_field() for reliability
    $author = get_field('book_author', $post->ID);
    $page_count = get_field('nop', $post->ID);
    $isbn = get_field('book_isbn', $post->ID);
    $pub_year = get_field('publication_year', $post->ID);

    return array(
        'id' => $post->ID,
        'title' => $post->post_title,
        'author' => $author ?: 'Unknown',
        'description' => $post->post_content,
        'cover_url' => $cover_url,
        'page_count' => $page_count ? intval($page_count) : 0,
        'isbn' => $isbn ?: '',
        'published_date' => $pub_year ?: ''
    );
}