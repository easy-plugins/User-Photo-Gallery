<?php
add_shortcode('notifications', 'imagepress_notifications');

function notification_count($reset = false) {
    global $wpdb;

    $user_ID = get_current_user_id();
    $counter = 0;

    $res = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "notifications WHERE status = %d", 0));
    foreach ($res as $line) {
        $postdata = get_post($line->postID, ARRAY_A);
        $authorID = $postdata['post_author'];
        $action = $line->actionType;

        if (
            ($action == 'loved' && $user_ID == $authorID) ||
            ($action == 'collected' && $user_ID == $authorID) ||
            ($action == 'added' && pwuf_is_following($user_ID, $authorID)) ||
            ($action == 'followed' && $user_ID == $line->postID) ||
            ($action == 'commented on' && $user_ID == $authorID && $user_ID != $line->userID) ||
            ($action == 'replied to a comment on' && $user_ID == get_comment($line->postID)->user_id) ||
            (0 == $line->postID || '-1' == $line->postID || $user_ID == $line->postID)
        ) {
            ++$counter;

            // Set status to 'read' for all notifications
            if ($reset === true) {
                $wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "notifications SET status = 1 WHERE ID = %d", $line->ID));
            }
        }
    }

    return $counter;
}

function notification_reset() {
    notification_count(true);
}

function imagepress_notifications($atts) {
    extract(shortcode_atts([
        'count' => 50
    ], $atts));

    global $wpdb;

    $user_ID = get_current_user_id();

    $ip_slug = get_imagepress_option('ip_slug');
    $display = '';

    $display .= '<div class="notifications-title">
        Notifications
        <a href="#" class="ip_notification_mark" data-userid="' . $user_ID . '">' . get_imagepress_option('ip_notifications_mark') . '</a>
    </div>';
    $display .= '<div class="notifications-inner" id="c">';

    $res = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "notifications ORDER BY actionTime DESC LIMIT %d", $count));

    foreach ($res as $line) {
        $action = $line->actionType;
        $nickname = get_the_author_meta('nickname', $line->userID);
        $time = human_time_diff(strtotime($line->actionTime), current_time('timestamp')) . ' ago';

        $postdata = get_post($line->postID, ARRAY_A);
        $authorID = $postdata['post_author'];

        if ( $line->status === 0) {
            $class = 'unread';
        } elseif ( $line->status === 1) {
            $class = 'read';
        }

        if ($action == 'loved' && $user_ID == $authorID)
            $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '"><div class="navatar">' . get_avatar($line->userID, 48) . '</div><a href="' . get_author_posts_url($line->userID) . '">' . $nickname . '</a> ' . $action . ' your ' . $ip_slug . ' <a href="' . get_permalink($line->postID) . '">' . get_the_title($line->postID) . '</a><time>' . $time . '</time></div>';

        if ($action == 'collected' && $user_ID == $authorID)
            $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '"><div class="navatar">' . get_avatar($line->userID, 48) . '</div><a href="' . get_author_posts_url($line->userID) . '">' . $nickname . '</a> ' . $action . ' your ' . $ip_slug . ' <a href="' . get_permalink($line->postID) . '">' . get_the_title($line->postID) . '</a><time>' . $time . '</time></div>';

        if ($action == 'added' && pwuf_is_following($user_ID, $authorID))
            $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '"><div class="navatar">' . get_avatar($line->userID, 48) . '</div><a href="' . get_author_posts_url($line->userID) . '">' . $nickname . '</a> ' . $action . ' <a href="' . get_permalink($line->postID) . '">' . get_the_title($line->postID) . '</a><time>' . $time . '</time></div>';

        if ($action == 'followed' && $user_ID == $line->postID)
            $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '"><div class="navatar">' . get_avatar($line->userID, 48) . '</div><a href="' . get_author_posts_url($line->userID) . '">' . $nickname . '</a> ' . $line->actionType . ' you<time>' . $time . '</time></div>';

        if ($action == 'commented on' && $user_ID == $authorID && $user_ID != $line->userID)
            $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '"><div class="navatar">' . get_avatar($line->userID, 48) . '</div><a href="' . get_author_posts_url($line->userID) . '">' . $nickname . '</a> ' . $action . ' your ' . $ip_slug . ' <a href="' . get_permalink($line->postID) . '">' . get_the_title($line->postID) . '</a><time>' . $time . '</time></div>';

        if ($action == 'replied to a comment on') {
            $comment_id = get_comment($line->postID);
            $comment_post_ID = $comment_id->comment_post_ID;
            $b = $comment_id->user_id;

            if($user_ID == $b)
                $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '"><div class="navatar">' . get_avatar($line->userID, 48) . '</div><a href="' . get_author_posts_url($line->userID) . '">' . $nickname . '</a> replied to your comment on <a href="' . get_permalink($comment_post_ID) . '">' . get_the_title($comment_post_ID) . '</a><time>' . $time . '</time></div>';
        }

        // custom
        if (0 == $line->postID || '-1' == $line->postID) {
            $display .= '<div class="notification-item n' . $line->ID . ' ' . $class . '" data-id="' . $line->ID . '">' . $line->actionType . '<time>' . $time . '</time></div>';
        }
    }

    $display .= '</div>';
    $display .= '<div class="nall"><a href="' . home_url() . '/notifications/">' . get_imagepress_option('ip_notifications_all') . '</a></div>';
    return $display;
}

$ip_slug = get_imagepress_option('ip_slug');

//add_action('post_updated', 'imagepress_post_update');
//add_action('publish_' . $ip_slug, 'imagepress_post_add');
add_action('new_to_publish', 'imagepress_post_add');
//add_action('draft_to_publish', 'imagepress_post_add');
add_action('comment_post', 'imagepress_comment_add');

function imagepress_post_add($act_post) {
    global $wpdb, $user_ID;

    // there's also this: if ($post->post_status != "publish") return;
    // http://wordpress.stackexchange.com/questions/63976/do-new-to-publish-hooks-work-for-custom-post-types
    if (!wp_is_post_revision($act_post)) {
        $ip_slug = get_imagepress_option('ip_slug');

        if (get_query_var('post_type') == $ip_slug && is_numeric($act_post)) {
            $act_time = current_time('mysql', true);
            $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "notifications (ID, userID, postID, actionType, actionTime) VALUES (null, %d, %d, 'added', %s)", $user_ID, $act_post, $act_time));
        }
    }
}
// function for image add
function imagepress_post_add_custom($post, $author) {
    global $wpdb;

    $act_time = current_time('mysql', true);
    $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "notifications (ID, userID, postID, actionType, actionTime) VALUES (null, %d, %d, 'added', %s)", $author, $post, $act_time));
}
function imagepress_comment_add($act_comment) {
    global $wpdb, $user_ID;

    $ip_slug = get_imagepress_option('ip_slug');

    $comment_id = get_comment($act_comment);
    $comment_post_ID = $comment_id->comment_post_ID;
    $comment_parent = $comment_id->comment_parent;

    $act_time = current_time('mysql', true);

    if (get_post_type($comment_id->comment_post_ID) == $ip_slug) {
        if (empty($comment_parent)) {
            $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "notifications (ID, userID, postID, actionType, actionTime) VALUES (null, %d, %d, 'commented on', %s)", $user_ID, $comment_post_ID, $act_time));
        } else {
            $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "notifications (ID, userID, postID, actionType, actionTime) VALUES (null, %d, %d, 'replied to a comment on', %s)", $user_ID, $comment_parent, $act_time));
        }
    }
}



add_action('wp_ajax_notification_read', 'notification_read');
function notification_read() {
    global $wpdb;
    $wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "notifications SET status = 1 WHERE ID = %d LIMIT 1", $_REQUEST['id']));
        echo 'success';
    die();
}
add_action('wp_ajax_notification_read_all', 'notification_reset');

function ajax_trash_action_callback() {
    global $wpdb;
    $odvm_post = $_POST['odvm_post'];

    $wpdb->query($wpdb->prepare("DELETE FROM " . $wpdb->prefix . "notifications WHERE ID = %d LIMIT 1", $odvm_post));

    echo 'Notification deleted successfully!';

    exit();
}
add_action('wp_ajax_ajax_trash_action', 'ajax_trash_action_callback');



/*
 * Helper functions
 *
 * Use these functions inside themes to display various notification-related information
 */
function ip_notifications_menu_item() {
    $item = '<a href="#" class="notifications-bell"><i class="fas fa-bell"></i><sup class="ui-accent-background">' . notification_count() . '</sup></a><div class="notifications-container ui">' . do_shortcode('[notifications]') . '</div>';

    return $item;
}
