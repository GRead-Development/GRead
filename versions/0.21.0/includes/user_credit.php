<?php
/**
 * Adds the 'Author' column to the admin list table for the 'book' post type.
 *
 * @param array $columns The existing array of columns.
 * @return array The modified array of columns.
 */
function hs_add_author_column_to_books_list($columns) {
    // To place the author column after the title, we rebuild the array
    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            // Add the 'author' column right after 'title'
            $new_columns['author'] = 'Author';
        }
    }
    return $new_columns;
}
add_filter('manage_book_posts_columns', 'hs_add_author_column_to_books_list');

/**
 * Populates the custom 'Author' column with the post author's name.
 *
 * @param string $column_name The name of the current column.
 * @param int    $post_id     The ID of the current post.
 */
function hs_display_book_author_column_content($column_name, $post_id) {
    if ($column_name === 'author') {
        // Get the post object to find the author ID
        $post = get_post($post_id);
        if ($post) {
            // Display the author's display name
            echo esc_html(get_the_author_meta('display_name', $post->post_author));
        }
    }
}
add_action('manage_book_posts_custom_column', 'hs_display_book_author_column_content', 10, 2);

