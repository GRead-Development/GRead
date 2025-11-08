<?php

// The total count of books in the database
function hs_total_books_shortcode()
{
        $count = wp_count_posts('book');
        $total_books = $count -> publish ?? 0;
        $total_books_formatted = number_format($total_books);


        // HTML output
        $output = '<div class= "hs-site-stats-container">';
        $output .= '<ul>';
        $output .= '<li><strong>Number of books:</strong> ' . $total_books_formatted . '</li>';
        $output .= '</ul>';
        $output .= '</div>';

        return $output;
//      return number_format($total_books);
}
add_shortcode('total_books', 'hs_total_books_shortcode');




function hs_site_stats_styles()
{
        echo '<style>
                .hs-site-stats-container
                {
                        background-color: #fff !important;
                        padding: 15px 25px !important;
                        border: 1px solid #e0e0e0 !important;
                        border-radius: 5px !important;
                }

                .hs-site-stats-container ul { list-style-type: none; padding-left: 0; }
                .hs-site-stats-container li { border-bottom: 1px solid #eee; padding: 8px 0; }
                .hs-site-stats-container li:last-child { border-bottom: none; }
        </style>';
}
add_action('wp_head', 'hs_site_stats_styles');

?>
