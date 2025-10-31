<?php


/**
 * Shortcode: [my_books]
 * Displays the user's reading library with options for sorting and filtering.
 *
 * Sorting is done via shortcode attributes or GET parameters:
 * - sort_by: 'title' (default), 'author', or 'progress'
 * - sort_order: 'asc' (default) or 'desc'
 * - include_completed: 'yes' or 'no' (default)
 */
function hs_my_books_shortcode($atts)
{
    if (!is_user_logged_in()) {
        // Return message if the user is not logged in
        return '<p>Please log in to track your reading!</p>';
    }

    // 1. Parse Shortcode Attributes for Sorting and Filtering
    $atts = shortcode_atts(array(
        'sort_by' => 'title',
        'sort_order' => 'asc',
        'include_completed' => 'no',
    ), $atts, 'my_books');

    // Clean and validate attributes, preferring GET parameters if present for interactive sorting
    $sort_by = isset($_GET['sort_by']) ? sanitize_key($_GET['sort_by']) : $atts['sort_by'];
    $sort_order = isset($_GET['sort_order']) ? sanitize_key($_GET['sort_order']) : $atts['sort_order'];
    $include_completed = isset($_GET['include_completed']) ? ('yes' === sanitize_key($_GET['include_completed'])) : ('yes' === $atts['include_completed']);

    // Ensure valid values after checking GET/defaults
    $sort_by = in_array($sort_by, array('title', 'author', 'progress')) ? $sort_by : 'title';
    $sort_order = in_array(strtolower($sort_order), array('asc', 'desc')) ? strtolower($sort_order) : 'asc';


    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'user_books';

	// Retrieve user's reviews
	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';
	$user_reviews_raw = $wpdb -> get_results($wpdb -> prepare("SELECT book_id, rating, review_text FROM {$reviews_table} WHERE user_id = %d", $user_id));
	$user_reviews = [];

	foreach ($user_reviews_raw as $review)
	{
		$user_reviews[$review -> book_id] = $review;
	}

    // Fetch all books for the current user
    $my_book_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if (empty($my_book_entries)) {
        return '<p>You have not added any books to your library. Browse the book database and add what books you are reading to your library. If you cannot find the book you are reading, submit it to the database and get some rewards!</p>';
    }

    // 2. Collect All Book Data into a single array
    $all_books_data = [];
    foreach ($my_book_entries as $book_entry) {
        $book = get_post($book_entry->book_id);
        if ($book) {
            $total_pages = (int)get_post_meta($book_entry->book_id, 'nop', true);
            $current_page = (int)$book_entry->current_page;
            $progress = ($total_pages > 0) ? round(($current_page / $total_pages) * 100) : 0;
            $is_completed = ($total_pages > 0 && $current_page >= $total_pages);

            // Assuming the author is the post author for simplicity; adjust meta key if needed
            $author = get_post_meta($book_entry->book_id, 'book_author', true);

		// Check if the user has reviewed the book
		$has_review = isset($user_reviews[$book_entry -> book_id]);
		$user_rating = $has_review ? $user_reviews[$book_entry -> book_id] -> rating : null;
		$user_review_text = $has_review ? $user_reviews[$book_entry -> book_id] -> review_text : '';

            $all_books_data[] = [
                'entry' => $book_entry,
                'post' => $book,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'progress' => $progress,
                'is_completed' => $is_completed,
                'author' => $author,
		'has_review' => $has_review,
		'user_rating' => $user_rating,
		'user_review_text' => $user_review_text
            ];
        }
    }

    // 3. Implement Sorting Logic (custom comparison function)
    usort($all_books_data, function($a, $b) use ($sort_by, $sort_order) {
        $a_val = '';
        $b_val = '';

        switch ($sort_by) {
            case 'author':
                $a_val = $a['author'];
                $b_val = $b['author'];
                break;
            case 'progress':
                $a_val = $a['progress'];
                $b_val = $b['progress'];
                break;
            case 'title':
            default:
                $a_val = $a['post']->post_title;
                $b_val = $b['post']->post_title;
        }

        // String comparison for title/author
        if ($sort_by === 'author' || $sort_by === 'title') {
            $result = strcasecmp($a_val, $b_val);
            return ($sort_order === 'asc') ? $result : -$result;
        }
        // Numeric comparison for progress
        else {
            if ($a_val == $b_val) return 0;
            if ($sort_order === 'asc') {
                return ($a_val < $b_val) ? -1 : 1;
            } else {
                return ($a_val > $b_val) ? -1 : 1;
            }
        }
    });

    // Initialize HTML containers
    $reading_books_html = '';
    $completed_books_html = '';
    $completed_count = 0;
    $main_list_html = ''; // For combined list when filtering is off

    // 4. Generate HTML based on the sorted list and current filter settings
    foreach ($all_books_data as $data) {
        $book_entry = $data['entry'];
        $book = $data['post'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];
        $progress = $data['progress'];
        $is_completed = $data['is_completed'];
        $author = $data['author'];
	$has_review = $data['has_review'];
	$user_rating = $data['user_rating'];
	$user_review_text = $data['user_review_text'];

	error_log("Book ID {$book_entry -> book_id}: current_page={$current_page}, total_pages={$total_pages}, is_completed=" . ($is_completed ? 'YES' : 'NO'));

        $li_class = $is_completed ? 'hs-my-book completed' : 'hs-my-book';
        $bar_class = $is_completed ? 'hs-progress-bar golden' : 'hs-progress-bar';

	// Data-reviewed attribute
	$book_html = '<li class="' . esc_attr($li_class) . '" data-list-book-id="' . esc_attr($book_entry -> book_id) . '" date-reviewed="' . ($has_review ? 'true' : 'false') . '">';

        // HTML for a single book item
        $book_html = '<li class="' . esc_attr($li_class) . '" data-list-book-id="' . esc_attr($book_entry->book_id) . '">';
        $book_html .= '<h3><a style="color: #0056b3;" href="' . esc_url(get_permalink($book->ID)) . '">' . esc_html($book->post_title) . '</a></h3>';
        $book_html .= '<p class="hs-book-author">By: ' . esc_html($author) . '</p>'; // Display Author
        $book_html .= '<div class="hs-progress-bar-container"><div class="' . esc_attr($bar_class) . '" style="width: ' . esc_attr($progress) . '%;"></div></div>';
        $book_html .= '<p>Progress: ' . esc_html($progress) . '% (' . esc_html($current_page) . ' / ' . esc_html($total_pages) . ' pages)</p>';

        $book_html .= '<form class="hs-progress-form">';
        $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
        $book_html .= '<label>Update current page number:</label>';
        $book_html .= '<input type="number" name="current_page" min="0" max="' . esc_attr($total_pages) . '" value="' . esc_attr($current_page) . '">';
        $book_html .= '<button type="submit" class="hs-button">Save Progress</button>';
        $book_html .= '<span class="hs-feedback"></span>';
        $book_html .= '</form>';

        $book_html .= '<button class="hs-button hs-remove-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Remove</button>';


	if ($is_completed) {
		error_log("Book ID {book_entry -> book_id}: ADDING REVIEW SECTION");
            $book_html .= '<div class="hs-review-section">';
            
            $review_button_text = $has_review ? 'Edit Review' : 'Review Book';
            $rating_display = $has_review && !is_null($user_rating) ? 'You rated this: <strong>' . esc_html($user_rating) . '/10</strong>' : '';

            $book_html .= '<span class="hs-user-rating-display">' . $rating_display . '</span>';
            $book_html .= '<button class="hs-button hs-toggle-review-form" data-book-id="' . esc_attr($book_entry->book_id) . '">' . $review_button_text . '</button>';

            // Hidden Review Form
            $book_html .= '<form class="hs-review-form" style="display:none;">';
            $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
            $book_html .= '<h4>Your Review</h4>';
            
            $book_html .= '<div class="hs-review-field">';
            $book_html .= '<label for="hs_rating_' . esc_attr($book_entry->book_id) . '">Rating (1.0 - 10.0):</label>';
            $book_html .= '<input type="number" id="hs_rating_' . esc_attr($book_entry->book_id) . '" name="hs_rating" min="1.0" max="10.0" step="0.1" value="' . esc_attr($user_rating) . '">';
            $book_html .= '</div>';
            
            $book_html .= '<div class="hs-review-field">';
            $book_html .= '<label for="hs_review_text_' . esc_attr($book_entry->book_id) . '">Review (optional, +20 points):</label>';
            $book_html .= '<textarea id="hs_review_text_' . esc_attr($book_entry->book_id) . '" name="hs_review_text" rows="4">' . esc_textarea($user_review_text) . '</textarea>';
            $book_html .= '</div>';

            $book_html .= '<button type="submit" class="hs-button">Submit Review</button>';
            $book_html .= '<span class="hs-review-feedback"></span>';
            $book_html .= '</form>'; // end .hs-review-form
            
            $book_html .= '</div>'; // end .hs-review-section
        }

        $book_html .= '</li>';

        // Append to the correct section string based on the filter setting
        if ($include_completed) {
            $main_list_html .= $book_html;
        } else {
            if ($is_completed) {
                $completed_books_html .= $book_html;
            } else {
                $reading_books_html .= $book_html;
            }
        }

        if ($is_completed) {
            $completed_count++; // Still count for summary
        }
    }


    // 5. Assemble the final output, starting with the sort/filter form

    // Determine the current URL to ensure form submission works correctly.
    $form_action = esc_url(remove_query_arg(array('sort_by', 'sort_order', 'include_completed')));

    $output = '<div class="hs-container">';

    // Form to control sorting and filtering
    $output .= '<form class="hs-sort-filter-form" action="' . $form_action . '" method="get">';

    // Hidden fields for existing GET parameters to maintain context if shortcode is on a complex page
    $current_url_params = $_GET;
    unset($current_url_params['sort_by'], $current_url_params['sort_order'], $current_url_params['include_completed']);
    foreach ($current_url_params as $key => $value) {
        $output .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }

    // Sort By Selector
    $output .= '<div class="hs-sort-group">';
    $output .= '<label for="hs_sort_by">Sort By:</label>';
    $output .= '<select name="sort_by" id="hs_sort_by" onchange="this.form.submit()">';
    $output .= '<option value="title"' . selected($sort_by, 'title', false) . '>Title</option>';
    $output .= '<option value="author"' . selected($sort_by, 'author', false) . '>Author</option>';
    $output .= '<option value="progress"' . selected($sort_by, 'progress', false) . '>Progress</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Sort Order Selector
    $output .= '<div class="hs-sort-group">';
    $output .= '<label for="hs_sort_order">Order:</label>';
    $output .= '<select name="sort_order" id="hs_sort_order" onchange="this.form.submit()">';
    $output .= '<option value="asc"' . selected($sort_order, 'asc', false) . '>Ascending (A-Z, 0-100%)</option>';
    $output .= '<option value="desc"' . selected($sort_order, 'desc', false) . '>Descending (Z-A, 100%-0%)</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Include Completed Checkbox
    $output .= '<div class="hs-filter-group">';
    $output .= '<label for="hs_include_completed">';
    // Use an input type="checkbox" but ensure the value is only submitted if checked
    $output .= '<input type="checkbox" name="include_completed" id="hs_include_completed" value="yes" ' . checked($include_completed, true, false) . ' onchange="this.form.submit()">';
    $output .= 'Include Completed in Main List';
    $output .= '</label>';
    $output .= '</div>';

    $output .= '</form>';


    // Display the book lists
    if ($include_completed) {
        // Combined List
        $output .= '<h2>My Full Library (Sorted by ' . esc_html(ucfirst($sort_by)) . ')</h2>';
        if (!empty($main_list_html)) {
            $output .= '<ul class="hs-my-book-list hs-combined-list" id="hs-combined-books-list">' . $main_list_html . '</ul>';
        } else {
            $output .= '<p>Your library is empty.</p>';
        }
    } else {
        // Separated Lists (Currently Reading / Completed)

        // "Currently Reading" Section
        $output .= '<h2>Currently Reading</h2>';
        if (!empty($reading_books_html)) {
            $output .= '<ul class="hs-my-book-list" id="hs-reading-books-list">' . $reading_books_html . '</ul>';
        } else {
            $output .= '<p>You are not currently reading any books.</p>';
        }

        // "Completed Books" Section
        if ($completed_count > 0) {
            $output .= '<details class="hs-completed-section">';
            $output .= '<summary><h3>Completed Books (' . $completed_count . ')</h3></summary>';
            $output .= '<ul class="hs-my-book-list" id="hs-completed-books-list">' . $completed_books_html . '</ul>';
            $output .= '</details>';
        }
    }

    $output .= '</div>';
    return $output;
}
// Add the shortcode
add_shortcode('my_books', 'hs_my_books_shortcode');

