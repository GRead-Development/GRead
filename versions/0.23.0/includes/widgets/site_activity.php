<?php

// A custom widget that displays activity from HotSoup!
class HotSoup_Activity_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'hotsoup_activity_widget',
            'HotSoup! Activity Feed',
            ['description' => __('Shows the latest activity for books being added, progress being updated, users registering, etc.', 'hotsoup')]
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        $limit = !empty($instance['number']) ? intval($instance['number']) : 5;

        echo '<div class="hotsoup-widget-feed">';
        echo '<ul>';

        if (function_exists('bp_activity_get')) {
            $activities = bp_activity_get([
                'object' => 'hotsoup',
                'per_page' => $limit,
                'display_comments' => 'stream',
            ]);

            if (!empty($activities['activities'])) {
                foreach ($activities['activities'] as $activity) {
                    echo '<li>' . $activity->action . '<span class="activity-time-since">' . bp_core_time_since($activity->date_recorded) . '</span></li>';
                }
            } else {
                echo '<li>No recent activity :(</li>';
            }
        } else {
            echo '<li>You should not be seeing this message.</li>';
        }

        echo '</ul>';
        echo '</div>';

        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : __('Recent Activity', 'hotsoup');
        $number = !empty($instance['number']) ? $instance['number'] : 5;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number'); ?>">Number of items to show:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($number); ?>" size="3">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['number'] = (!empty($new_instance['number'])) ? intval($new_instance['number']) : 5;
        return $instance;
    }
}


// Register the widget with WordPress
function hs_register_activity_widget()
{
    register_widget('HotSoup_Activity_Widget');
}
add_action('widgets_init', 'hs_register_activity_widget');


// Basic styling
function hs_widget_styles()
{
    echo '<style>
        .hotsoup-widget-feed ul { list-style: none; margin-left: 0; padding-left: 0; }
        .hotsoup-widget-feed li { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
        .hotsoup-widget-feed li .activity-time-since { display: block; color: #888; font-size: 0.8em; margin-top: 4px; }
        </style>';
}
add_action('wp_head', 'hs_widget_styles');
