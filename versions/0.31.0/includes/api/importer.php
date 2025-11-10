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
      $clean_isbn = preg_replace('/[^0-9-]/', '', $isbn);
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

      // Return the created book
      $book = gread_get_book_by_post_id($book_post_id);
      return gread_format_book_response($book);
  }

  /**
   * Search for existing book by ISBN
   */
  function gread_find_book_by_isbn($isbn) {
      $args = array(
          'post_type' => 'book',
          'posts_per_page' => 1,
          'meta_query' => array(
              array(
                  'key' => '_book_isbn',
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
      $url = sprintf('https://openlibrary.org/api/books?bibkeys=ISBN:%s&format=json&jscmd=data',
   $isbn);

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
   */
  function gread_create_book_post($book_data, $isbn) {
      // Extract data from OpenLibrary response
      $title = isset($book_data['title']) ? $book_data['title'] : 'Unknown Book';
      $author = 'Unknown';

      if (isset($book_data['authors']) && is_array($book_data['authors']) &&
  !empty($book_data['authors'])) {
          $author = $book_data['authors'][0]['name'] ?? 'Unknown';
      }

      $description = isset($book_data['excerpts']) && is_array($book_data['excerpts']) &&
  !empty($book_data['excerpts'])
          ? $book_data['excerpts'][0]['text'] ?? ''
          : '';

      $page_count = isset($book_data['number_of_pages']) ? intval($book_data['number_of_pages'])
   : 0;
      $published_date = isset($book_data['publish_date']) ? $book_data['publish_date'] : '';
      $cover_url = isset($book_data['cover']['large']) ? $book_data['cover']['large'] : '';

      // Create the book post
      $post_args = array(
          'post_type' => 'book',
          'post_title' => sanitize_text_field($title),
          'post_content' => wp_kses_post($description),
          'post_status' => 'publish',
          'meta_input' => array(
              '_book_isbn' => $isbn,
              '_book_author' => sanitize_text_field($author),
              '_book_page_count' => $page_count,
              '_book_published_date' => sanitize_text_field($published_date),
              '_book_cover_url' => esc_url($cover_url)
          )
      );

      $post_id = wp_insert_post($post_args);

      if (is_wp_error($post_id)) {
          return new WP_Error(
              'book_creation_failed',
              'Failed to create book post',
              array('status' => 500)
          );
      }

      // Set featured image if cover URL exists
      if (!empty($cover_url)) {
          gread_set_featured_image_from_url($post_id, $cover_url, $title);
      }

      return $post_id;
  }

  /**
   * Download and attach featured image from URL
   */
  function gread_set_featured_image_from_url($post_id, $image_url, $title) {
      $response = wp_remote_get($image_url, array(
          'timeout' => 10,
          'user-agent' => 'GRead-App/1.0'
      ));

      if (is_wp_error($response)) {
          return;
      }

      $image_data = wp_remote_retrieve_body($response);
      if (empty($image_data)) {
          return;
      }

      $upload_dir = wp_upload_dir();
      $filename = sprintf('book-cover-%s-%d.jpg', sanitize_title($title), $post_id);
      $file_path = $upload_dir['path'] . '/' . $filename;

      if (!file_put_contents($file_path, $image_data)) {
          return;
      }

      $filetype = wp_check_filetype($file_path);
      $attachment_args = array(
          'guid' => $upload_dir['url'] . '/' . $filename,
          'post_mime_type' => $filetype['type'],
          'post_title' => sanitize_text_field($title),
          'post_content' => '',
          'post_status' => 'inherit'
      );

      $attachment_id = wp_insert_attachment($attachment_args, $file_path, $post_id);

      if (!is_wp_error($attachment_id)) {
          require_once(ABSPATH . 'wp-admin/includes/image.php');
          $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
          wp_update_attachment_metadata($attachment_id, $attach_data);
          set_post_thumbnail($post_id, $attachment_id);
      }
  }

  /**
   * Retrieve book by post ID
   */
  function gread_get_book_by_post_id($post_id) {
      return get_post($post_id);
  }

  /**
   * Format book post for API response
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

      if (!$cover_url) {
          $cover_url = get_post_meta($post->ID, '_book_cover_url', true);
      }

      return array(
          'id' => $post->ID,
          'title' => $post->post_title,
          'author' => get_post_meta($post->ID, '_book_author', true),
          'description' => $post->post_content,
          'cover_url' => $cover_url,
          'page_count' => intval(get_post_meta($post->ID, '_book_page_count', true)),
          'isbn' => get_post_meta($post->ID, '_book_isbn', true),
          'published_date' => get_post_meta($post->ID, '_book_published_date', true)
      );
  }
