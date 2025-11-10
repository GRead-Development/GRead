<?php
// [book_directory]: Display a list of available books
function hs_book_directory_shortcode()
{
    if (is_user_logged_in())
    {
        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb -> prefix . 'user_books';
        $user_book_ids = $wpdb -> get_col("SELECT book_id FROM $table_name WHERE user_id = $user_id");
	}
    $args =
    [
        'post_type' => 'book',
        'posts_per_page' => -1,
    ];

    $books = new WP_Query($args);
    $output = '<div class="hs-container"><ul class="hs-book-list">';

    if ($books -> have_posts())
    {
        while ($books -> have_posts())
        {
            $books -> the_post();
            $book_id = get_the_ID();
            $author = get_post_meta($book_id, 'book_author', true);
            $pagecount = get_post_meta($book_id, 'nop', true);
            $isbn = get_post_meta($book_id, 'book_isbn', true);

            $output .= '<li>';
            $output .= '<h3>' . get_the_title() . '</h3>';
            
            if (!empty($author))
            {
                $output .= '<p><strong>Author:</strong> ' . esc_html($author) . '</p>';
            }

            if (!empty($pagecount))
            {
                $output .= '<p><strong>Pages:</strong> ' . esc_html($pagecount) . '</p>';
            }

            if (!empty($isbn))
            {
                $output .= '<p><strong>ISBN:</strong> ' . esc_html($isbn) . '</p>';
            }

			if (is_user_logged_in())
			{
				if (in_array($book_id, $user_book_ids))
				{
					$output .= '<button class="hs-button" disabled>Added</button>';
				}
				else
				{
					$output .= '<button class="hs-button hs-add-book" data-book-id="' . $book_id . '">Add to Library</button>';

				}
			}

            $output .= '</li>';
            }
        }

        else
        {
            $output .= '<li>No books are available. Check back later!</li>';
        }

        $output .= '</ul></div>';
        wp_reset_postdata();
        return $output;
}
// Add the shortcode
add_shortcode('book_directory', 'hs_book_directory_shortcode');

