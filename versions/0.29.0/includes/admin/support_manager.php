<?php

// NOTE: THIS IS A BETA VERSION. IF THIS WORKS, REWRITE IT.


function support_tickets_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_tickets';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_email varchar(100) NOT NULL,
        subject varchar(255) NOT NULL,
        message text NOT NULL,
        status varchar(20) DEFAULT 'open',
        admin_response text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add admin menu
function support_tickets_add_menu() {
    add_menu_page(
        'Support Tickets',
        'Support Tickets',
        'manage_options',
        'support-tickets',
        'support_tickets_admin_page',
        'dashicons-tickets-alt',
        30
    );
}
add_action('admin_menu', 'support_tickets_add_menu');

// Enqueue frontend scripts
function support_tickets_enqueue_scripts() {
    if (has_shortcode(get_post()->post_content, 'support_form')) {
        wp_enqueue_style('support-ticket-style', plugins_url('assets/support-ticket-style.css', __FILE__));
        wp_enqueue_script('support-ticket-script', plugins_url('assets/support-ticket-script.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script('support-ticket-script', 'supportTicket', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('support_ticket_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'support_tickets_enqueue_scripts');

// Enqueue admin scripts
function support_tickets_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_support-tickets') {
        return;
    }
    
    wp_enqueue_style('support-ticket-admin-style', plugins_url('assets/support-ticket-admin-style.css', __FILE__));
    wp_enqueue_script('support-ticket-admin-script', plugins_url('assets/support-ticket-admin-script.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('support-ticket-admin-script', 'supportTicketAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('support_ticket_admin_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'support_tickets_enqueue_admin_scripts');

// Shortcode to display support form
function support_tickets_form_shortcode($atts) {
    ob_start();
    
    $user = wp_get_current_user();
    $user_email = $user->exists() ? $user->user_email : '';
    
    ?>
    <div class="support-ticket-form-wrapper">
        <form id="support-ticket-form" class="support-ticket-form">
            <?php if (!is_user_logged_in()): ?>
            <div class="form-group">
                <label for="ticket-email">Email Address *</label>
                <input type="email" id="ticket-email" name="email" required>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="ticket-subject">Subject *</label>
                <input type="text" id="ticket-subject" name="subject" required>
            </div>
            
            <div class="form-group">
                <label for="ticket-message">Message *</label>
                <textarea id="ticket-message" name="message" rows="6" required></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="submit-ticket-btn">Submit Ticket</button>
            </div>
            
            <div id="ticket-response-message"></div>
        </form>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode('support_form', 'support_tickets_form_shortcode');

// Handle ticket submission
function support_tickets_handle_submission() {
    check_ajax_referer('support_ticket_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_tickets';
    
    $user = wp_get_current_user();
    $user_id = $user->exists() ? $user->ID : 0;
    $email = $user->exists() ? $user->user_email : sanitize_email($_POST['email']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = sanitize_textarea_field($_POST['message']);
    
    if (empty($email) || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => 'Please fill in all required fields.'));
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'user_email' => $email,
            'subject' => $subject,
            'message' => $message,
            'status' => 'open'
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
    
    if ($result) {
        wp_send_json_success(array('message' => 'Your support ticket has been submitted successfully!'));
    } else {
        wp_send_json_error(array('message' => 'There was an error submitting your ticket. Please try again.'));
    }
}
add_action('wp_ajax_submit_support_ticket', 'support_tickets_handle_submission');
add_action('wp_ajax_nopriv_submit_support_ticket', 'support_tickets_handle_submission');

// Admin page display
function support_tickets_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_tickets';
    
    $tickets = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
    
    ?>
    <div class="wrap">
        <h1>Support Tickets</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="7">No tickets found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?php echo esc_html($ticket->id); ?></td>
                        <td><?php echo esc_html($ticket->user_id ? get_userdata($ticket->user_id)->display_name : 'Guest'); ?></td>
                        <td><?php echo esc_html($ticket->user_email); ?></td>
                        <td><?php echo esc_html($ticket->subject); ?></td>
                        <td><span class="status-badge status-<?php echo esc_attr($ticket->status); ?>"><?php echo esc_html($ticket->status); ?></span></td>
                        <td><?php echo esc_html(date('M j, Y g:i a', strtotime($ticket->created_at))); ?></td>
                        <td>
                            <button class="button view-ticket-btn" data-ticket-id="<?php echo esc_attr($ticket->id); ?>">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Ticket Detail Modal -->
        <div id="ticket-modal" class="ticket-modal" style="display:none;">
            <div class="ticket-modal-content">
                <span class="close-modal">&times;</span>
                <div id="ticket-details"></div>
            </div>
        </div>
    </div>
    <?php
}

// Get ticket details for admin modal
function support_tickets_get_details() {
    check_ajax_referer('support_ticket_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_tickets';
    $ticket_id = intval($_POST['ticket_id']);
    
    $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $ticket_id));
    
    if ($ticket) {
        $user_name = $ticket->user_id ? get_userdata($ticket->user_id)->display_name : 'Guest';
        
        ob_start();
        ?>
        <h2>Ticket #<?php echo esc_html($ticket->id); ?> - <?php echo esc_html($ticket->subject); ?></h2>
        <p><strong>From:</strong> <?php echo esc_html($user_name); ?> (<?php echo esc_html($ticket->user_email); ?>)</p>
        <p><strong>Status:</strong> <span class="status-badge status-<?php echo esc_attr($ticket->status); ?>"><?php echo esc_html($ticket->status); ?></span></p>
        <p><strong>Date:</strong> <?php echo esc_html(date('M j, Y g:i a', strtotime($ticket->created_at))); ?></p>
        
        <div class="ticket-message-box">
            <h3>User Message:</h3>
            <p><?php echo nl2br(esc_html($ticket->message)); ?></p>
        </div>
        
        <?php if ($ticket->admin_response): ?>
        <div class="ticket-response-box">
            <h3>Your Response:</h3>
            <p><?php echo nl2br(esc_html($ticket->admin_response)); ?></p>
        </div>
        <?php endif; ?>
        
        <form id="admin-response-form">
            <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>">
            
            <div class="form-group">
                <label for="admin-response">Response:</label>
                <textarea id="admin-response" name="response" rows="6" required><?php echo esc_textarea($ticket->admin_response); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="ticket-status">Status:</label>
                <select id="ticket-status" name="status">
                    <option value="open" <?php selected($ticket->status, 'open'); ?>>Open</option>
                    <option value="in-progress" <?php selected($ticket->status, 'in-progress'); ?>>In Progress</option>
                    <option value="closed" <?php selected($ticket->status, 'closed'); ?>>Closed</option>
                </select>
            </div>
            
            <button type="submit" class="button button-primary">Send Response</button>
        </form>
        <div id="admin-response-message"></div>
        <?php
        
        wp_send_json_success(array('html' => ob_get_clean()));
    } else {
        wp_send_json_error(array('message' => 'Ticket not found'));
    }
}
add_action('wp_ajax_get_ticket_details', 'support_tickets_get_details');

// Handle admin response
function support_tickets_handle_response() {
    check_ajax_referer('support_ticket_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_tickets';
    
    $ticket_id = intval($_POST['ticket_id']);
    $response = sanitize_textarea_field($_POST['response']);
    $status = sanitize_text_field($_POST['status']);
    
    // Get ticket details
    $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $ticket_id));
    
    if (!$ticket) {
        wp_send_json_error(array('message' => 'Ticket not found'));
    }
    
    // Update ticket
    $result = $wpdb->update(
        $table_name,
        array(
            'admin_response' => $response,
            'status' => $status
        ),
        array('id' => $ticket_id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        // Send email notification
        support_tickets_send_email($ticket, $response);
        
        // Send BuddyPress notification if user is registered and BuddyPress is active
        if ($ticket->user_id > 0 && function_exists('bp_notifications_add_notification')) {
            support_tickets_send_bp_notification($ticket->user_id, $ticket_id);
        }
        
        wp_send_json_success(array('message' => 'Response sent successfully!'));
    } else {
        wp_send_json_error(array('message' => 'Error updating ticket'));
    }
}
add_action('wp_ajax_submit_ticket_response', 'support_tickets_handle_response');

// Send email notification
function support_tickets_send_email($ticket, $response) {
    $subject = 'Response to your support ticket: ' . $ticket->subject;
    $message = "Hello,\n\n";
    $message .= "You have received a response to your support ticket.\n\n";
    $message .= "Original Subject: " . $ticket->subject . "\n\n";
    $message .= "Admin Response:\n" . $response . "\n\n";
    $message .= "Thank you for contacting us!\n";
    $message .= get_bloginfo('name');
    
    wp_mail($ticket->user_email, $subject, $message);
}

// Send BuddyPress notification
function support_tickets_send_bp_notification($user_id, $ticket_id) {
    if (!function_exists('bp_notifications_add_notification')) {
        return;
    }
    
    bp_notifications_add_notification(array(
        'user_id' => $user_id,
        'item_id' => $ticket_id,
        'component_name' => 'support_tickets',
        'component_action' => 'ticket_response',
        'date_notified' => bp_core_current_time(),
        'is_new' => 1,
    ));
}

// Inline CSS (if you don't want separate files)
function support_tickets_inline_styles() {
    if (has_shortcode(get_post()->post_content, 'support_form')) {
        ?>
        <style>
        .support-ticket-form-wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .support-ticket-form .form-group { margin-bottom: 20px; }
        .support-ticket-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .support-ticket-form input[type="text"],
        .support-ticket-form input[type="email"],
        .support-ticket-form textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .submit-ticket-btn { background-color: #0073aa; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .submit-ticket-btn:hover { background-color: #005177; }
        #ticket-response-message { margin-top: 15px; padding: 10px; border-radius: 4px; }
        #ticket-response-message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        #ticket-response-message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        </style>
        <?php
    }
}
add_action('wp_head', 'support_tickets_inline_styles');

// Inline admin CSS
function support_tickets_admin_inline_styles() {
    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_support-tickets') {
        return;
    }
    ?>
    <style>
    .status-badge { padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; }
    .status-open { background-color: #ffeb3b; color: #333; }
    .status-in-progress { background-color: #2196f3; color: white; }
    .status-closed { background-color: #4caf50; color: white; }
    .ticket-modal { position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
    .ticket-modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 5px; }
    .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .close-modal:hover, .close-modal:focus { color: #000; }
    .ticket-message-box, .ticket-response-box { background-color: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
    #admin-response-form .form-group { margin-bottom: 15px; }
    #admin-response-form label { display: block; margin-bottom: 5px; font-weight: bold; }
    #admin-response-form textarea, #admin-response-form select { width: 100%; padding: 8px; }
    #admin-response-message { margin-top: 15px; padding: 10px; border-radius: 4px; }
    #admin-response-message.success { background-color: #d4edda; color: #155724; }
    #admin-response-message.error { background-color: #f8d7da; color: #721c24; }
    </style>
    <?php
}
add_action('admin_head', 'support_tickets_admin_inline_styles');

// Inline frontend JavaScript
function support_tickets_inline_script() {
    if (has_shortcode(get_post()->post_content, 'support_form')) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#support-ticket-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'submit_support_ticket',
                    nonce: '<?php echo wp_create_nonce('support_ticket_nonce'); ?>',
                    email: $('#ticket-email').val(),
                    subject: $('#ticket-subject').val(),
                    message: $('#ticket-message').val()
                };
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    beforeSend: function() {
                        $('.submit-ticket-btn').prop('disabled', true).text('Submitting...');
                    },
                    success: function(response) {
                        var messageDiv = $('#ticket-response-message');
                        if (response.success) {
                            messageDiv.removeClass('error').addClass('success').text(response.data.message);
                            $('#support-ticket-form')[0].reset();
                        } else {
                            messageDiv.removeClass('success').addClass('error').text(response.data.message);
                        }
                        $('.submit-ticket-btn').prop('disabled', false).text('Submit Ticket');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'support_tickets_inline_script');

// Inline admin JavaScript
function support_tickets_admin_inline_script() {
    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_support-tickets') {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.view-ticket-btn', function() {
            var ticketId = $(this).data('ticket-id');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_ticket_details',
                    nonce: '<?php echo wp_create_nonce('support_ticket_admin_nonce'); ?>',
                    ticket_id: ticketId
                },
                success: function(response) {
                    if (response.success) {
                        $('#ticket-details').html(response.data.html);
                        $('#ticket-modal').show();
                    }
                }
            });
        });
        
        $(document).on('click', '.close-modal', function() {
            $('#ticket-modal').hide();
        });
        
        $(window).on('click', function(e) {
            if ($(e.target).is('#ticket-modal')) {
                $('#ticket-modal').hide();
            }
        });
        
        $(document).on('submit', '#admin-response-form', function(e) {
            e.preventDefault();
            var formData = {
                action: 'submit_ticket_response',
                nonce: '<?php echo wp_create_nonce('support_ticket_admin_nonce'); ?>',
                ticket_id: $('input[name="ticket_id"]').val(),
                response: $('#admin-response').val(),
                status: $('#ticket-status').val()
            };
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    $('.button-primary').prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    var messageDiv = $('#admin-response-message');
                    if (response.success) {
                        messageDiv.removeClass('error').addClass('success').text(response.data.message);
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        messageDiv.removeClass('success').addClass('error').text(response.data.message);
                        $('.button-primary').prop('disabled', false).text('Send Response');
                    }
                }
            });
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'support_tickets_admin_inline_script');
