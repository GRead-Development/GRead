<?php

// This adds a management system for unlockables.

if (!defined('ABSPATH'))
{
        exit;
}


// Enqueues the CSS file for displaying unlockables if the user is viewing a page that uses the shortcode
function hs_rewards_enqueue_styles()
{
	global $post;

	// Should this be switched to a page?
	if (is_a($post, 'WP_Post') && has_shortcode($post -> post_content, 'hs_rewards_display'))
	{
		wp_enqueue_style(
			'hs-rewards-style',
			plugin_dir_url(__FILE__) . '../css/hs-rewards-style.css',
			[],
			'1.0.0'
		);
	}
}
add_action('wp_enqueue_scripts', 'hs_rewards_enqueue_styles');


// Registers and renders [hs_rewards_display]
function hs_rewards_display_shortcode()
{
	if (!is_user_logged_in())
	{
		return '<p>You need to be logged in in order to unlock things!</p>';
	}


	$user_id = get_current_user_id();
	global $wpdb;

	// Get user's unlockables and unlocked status in a single query
	$table_unlockables = $wpdb -> prefix . 'hs_unlockables';
	$table_user_unlocks = $wpdb -> prefix . 'hs_user_unlocks';

	$all_unlockables = $wpdb -> get_results($wpdb -> prepare(
		"SELECT u.*, uu.id IS NOT NULL as is_unlocked
		FROM {$table_unlockables} u
		LEFT JOIN {$table_user_unlocks} uu ON u.id = uu.unlockable_id AND uu.user_id = %d
		ORDER BY u.requirement ASC",
		$user_id
	));

	if (empty($all_unlockables))
	{
		return '<p>There is nothing to unlock yet!</p>';
	}

	// Get a user's current statistics in order to calculate progress
	$user_stats = [
		'points' => (int) get_user_meta($user_id, 'user_points', true),
		'books_read' => (int) get_user_meta($user_id, 'hs_completed_books_count', true),
		'pages_read' => (int) get_user_meta($user_id, 'hs_total_pages_read', true),
	];


	ob_start();
	?>

	<div class="hs-rewards-grid">
		<?php foreach($all_unlockables as $item) : ?>
			<?php
				$progress_percentage = 0;
				$current_value = 0;
				$metric_label = ucwords(str_replace('_', ' ', $item -> metric));

				if (!$item -> is_unlocked)
				{
					$current_value = $user_stats[$item -> metric] ?? 0;

					if ($item -> requirement > 0)
					{
						$progress_percentage = min(100, ($current_value / $item -> requirement) * 100);
					}
				}

				else
				{
					$progress_percentage = 100;
				}
			?>

			<div class="hs-reward-item <?php echo $item -> is_unlocked ? 'unlocked' : ''; ?>">
				<div class="hs-reward-icon"><span class="dashicons dashicons-awards"></span></div>
				<h3 class="hs-reward-title"><?php echo esc_html($item -> title); ?></h3>
				<p class="hs-reward-description"><?php echo esc_html($item -> description); ?></p>

				<div class="hs-reward-progress">
					<div class="hs-progress-bar" style="width: <?php echo esc_attr($progress_percentage); ?>%;"></div>
				</div>

				<div class="hs-progress-text">
					<?php if ($item -> is_unlocked) : ?>
						<strong>Unlocked!</strong>
					<?php else : ?>
						<?php echo number_format($current_value) . ' / ' . number_format($item -> requirement) . ' ' . esc_html($metric_label); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
	<?php

	return ob_get_clean();
}
add_shortcode('hs_rewards_display', 'hs_rewards_display_shortcode');
// Adds the page
function hs_rewards_add_admin_page()
{
        add_menu_page(
                // Page title
                'Unlockables',

                // Menu title
                'Unlockables',

                // Required capability
                'manage_options',

                // The menu slug
                'hs-unlockables',

                // The function used to display the page
                'hs_rewards_admin_page_html',

                // Icon
                'dashicons-awards',

                // Position
                25
        );
}
add_action('admin_menu', 'hs_rewards_add_admin_page');


// Renders the HTML required for the admin page
function hs_rewards_admin_page_html()
{
        global $wpdb;
        $table_name = $wpdb -> prefix . 'hs_unlockables';


        // This handles the form required for adding/modifying an unlockable
        if (isset($_POST['hs_save_unlockable_nonce']) && wp_verify_nonce($_POST['hs_save_unlockable_nonce'], 'hs_save_unlockable'))
        {
                $title = sanitize_text_field($_POST['title']);
                $description = sanitize_textarea_field($_POST['description']);
                $metric = sanitize_key($_POST['metric']);
                $requirement = intval($_POST['requirement']);
                $id = isset($_POST['unlockable_id']) ? intval($_POST['unlockable_id']) : 0;

                $data = [
                        'title' => $title,
                        'description' => $description,
                        'metric' => $metric,
                        'requirement' => $requirement,
                ];
                $format = ['%s', '%s', '%s', '%d'];


                if ($id > 0)
                {
                        $wpdb -> update($table_name, $data, ['id' => $id], $format, ['%d']);
                }

                else
                {
                        $wpdb -> insert($table_name, $data, $format);
                }
        }


        // Handles deleting unlockables
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']))
        {
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'hs_delete_unlockable_' . $_GET['id']))
                {
                        $wpdb -> delete($table_name, ['id' => intval($_GET['id'])], ['%d']);
                }
        }


        // Determines whether we are adding an unlockable, or editing an existing one.
        $unlockable_to_edit = null;

        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']))
        {
                $unlockable_to_edit = $wpdb -> get_row($wpdb -> prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
        }


        $unlockables = $wpdb -> get_results("SELECT * FROM $table_name ORDER BY requirement ASC");
        ?>

        <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <div id="col-container" class="wp-clearfix">
                        <div id="col-left">
                                <div class="col-wrap">
                                        <h2><?php echo $unlockable_to_edit ? 'Edit Unlockable' : 'Add Unlockable'; ?></h2>

                                        <form method="post">
                                                <input type="hidden" name="unlockable_id" value="<?php echo isset($unlockable_to_edit->id) ? esc_attr($unlockable_to_edit->id) : '0'; ?>">
                                                <?php wp_nonce_field('hs_save_unlockable', 'hs_save_unlockable_nonce'); ?>

                                                <div class="form-field">
                                                        <label for="title">Title</label>
                                                        <input type="text" name="title" id="title" value="<?php echo isset($unlockable_to_edit->title) ? esc_attr($unlockable_to_edit->title) : ''; ?>" required>
                                                </div>

                                                <div class="form-field">
                                                        <label for="description">Description</label>
                                                        <textarea name="description" id="description" rows="3"><?php echo isset($unlockable_to_edit->description) ? esc_textarea($unlockable_to_edit->description) : ''; ?></textarea>
                                                </div>

                                                <div class="form-field">
                                                        <label for="metric">Metric</label>
                                                        <select name="metric" id="metric">
                                                                <option value="points" <?php if (isset($unlockable_to_edit->metric)) selected($unlockable_to_edit->metric, 'points'); ?>>Points Earned</option>
                                                                <option value="books_read" <?php if (isset($unlockable_to_edit->metric)) selected($unlockable_to_edit->metric, 'books_read'); ?>>Books Read</option>
                                                                <option value="pages_read" <?php if (isset($unlockable_to_edit->metric)) selected($unlockable_to_edit->metric, 'pages_read'); ?>>Pages Read</option>
                                                        </select>
                                                </div>

                                                <div class="form-field">
                                                        <label for="requirement">Requirement</label>
                                                        <input type="number" name="requirement" id="requirement" value="<?php echo isset($unlockable_to_edit->requirement) ? esc_attr($unlockable_to_edit->requirement) : ''; ?>" required>
                                                </div>

                                                <p class="submit">
                                                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $unlockable_to_edit ? 'Update Unlockable' : 'Add Unlockable'; ?>">
                                                </p>
                                        </form>
                                </div>
                        </div>

                        <div id="col-right">
                                <div class="col-wrap">
                                        <table class="wp-list-table widefat fixed striped">
                                                <thead>
                                                        <tr>
                                                                <th>Title</th>
                                                                <th>Metric</th>
                                                                <th>Requirement</th>
                                                        </tr>
                                                </thead>

                                                <tbody>
                                                        <?php if ($unlockables) : ?>
                                                                <?php foreach ($unlockables as $unlockable) : ?>
                                                                        <tr>
                                                                                <td>
                                                                                        <strong><?php echo esc_html($unlockable -> title); ?></strong>
                                                                                        <div class="row-actions">
                                                                                                <span class="edit"><a href="?page=hs-unlockables&action=edit&id=<?php echo $unlockable->id; ?>">Edit</a> | </span>
                                                                                                <span class="delete"><a href="?page=hs-unlockables&action=delete&id=<?php echo $unlockable->id; ?>&_wpnonce=<?php echo wp_create_nonce('hs_delete_unlockable_' . $unlockable->id); ?>" onclick="return confirm('You sure?')">Delete</a></span>
                                                                                        </div>
                                                                                </td>
                                                                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $unlockable -> metric))); ?></td>
                                                                                <td><?php echo esc_html($unlockable -> requirement); ?></td>
                                                                        </tr>
                                                                <?php endforeach; ?>
                                                        <?php else : ?>
                                                                <tr>
                                                                        <td colspan="3">No unlockables found.</td>
                                                                </tr>
                                                        <?php endif; ?>
                                                </tbody>
                                        </table>
                                </div>
                        </div>
                </div>
        </div>
        <?php
}


// Checks user milestones and awards unlockables when appropriate
function hs_rewards_check_user_statistics($user_id)
{
	if (!$user_id)
	{
		return;
	}

	global $wpdb;
	$table_unlockables = $wpdb -> prefix . 'hs_unlockables';
	$table_user_unlocks = $wpdb -> prefix . 'hs_user_unlocks';


	// Finds the unlockables that a given user has not unlocked yet
	$unearned_unlockables = $wpdb -> get_results($wpdb -> prepare(
		"SELECT * FROM {$table_unlockables} u WHERE NOT EXISTS (SELECT 1 FROM {$table_user_unlocks} uu WHERE uu.unlockable_id = u.id AND uu.user_id = %d)",
		$user_id
	));

	if (empty($unearned_unlockables))
	{
		return;
	}


	// Retrieve a user's current statistics
	$user_stats = [
		'points' => (int) get_user_meta($user_id, 'user_points', true),
		'books_read' => (int) get_user_meta($user_id, 'hs_completed_books_count', true),
		'pages_read' => (int) get_user_meta($user_id, 'hs_total_pages_read', true),
	];

	// Loop through the unearned unlockables and determine whether or not a user has met the requirements to unlock something
	foreach ($unearned_unlockables as $unlockable)
	{
		$metric = $unlockable -> metric;

		if (isset($user_stats[$metric]) && $user_stats[$metric] >= $unlockable -> requirement)
		{
			// User has met the requirements, so give them what they deserve (good and hard)
			$wpdb -> insert(
				$table_user_unlocks,
				[
					'user_id' => $user_id,
					'unlockable_id' => $unlockable -> id,
					'date_unlocked' => current_time('mysql'),
				],

				['%d', '%d', '%s']
			);
		}
	}
}
// Hooks into the actions that handle updating users' statistics
add_action('hs_stats_updated', 'hs_rewards_check_user_statistics', 10, 1);
add_action('hs_points_updated', 'hs_rewards_check_user_statistics', 10, 1);

