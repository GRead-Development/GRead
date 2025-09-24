<?php

// This gives us a way to display books in our database, on any page we want.

if (!defined('ABSPATH'))
{
	exit;
}



function gr_books_display_list_shortcode()
{
	$args = [
		'post_type' => 'book',
		'posts_per_page' => -1,
		'post_status' => 'publish',
	];

	$books_query = new WP_Query($args);
	ob_start();

	if ($books_query -> have_posts())
	{
		?>
		<table class="gr-book-list">
			<thead>
				<tr>
					<th>Name</th>
					<th>Author</th>
					<th>Publication Year</th>
					<th>Pages</th>
					<th>ISBN</th>
					<th>Rating</th>
				</tr>
			</thead>

			<tbody>
				<?php while ($books_query -> have_posts()) : $books_query -> the_post(); ?>
					<tr>
						<td><?php the_title(); ?></td>
						<td><?php echo esc_html(get_field('book_author')); ?></td>
						<td><?php echo esc_html(get_field('publication_year')); ?></td>
						<td><?php echo esc_html(get_field('nop')); ?></td>
						<td><?php echo esc_html(get_field('book_isbn')); ?></td>
						<td>Coming soon!</td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>

		<?php wp_reset_postdata();
		}

		else
		{
			echo '<p>There are no books in the database!</p>';
		}

	return ob_get_clean();
}

add_shortcode('book_list', 'gr_books_display_list_shortcode');
