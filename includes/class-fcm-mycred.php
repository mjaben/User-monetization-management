<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FC_MyCRED_Integration {

    public function __construct() {
        add_action('fluent_community/feed/created', [$this, 'award_points_for_feed']);
        add_action('fluent_community/comment_added', [$this, 'award_points_for_comment'], 10, 2);
    }

    public function award_points_for_feed($feed) {
        if( ! function_exists('mycred_add') ) return;

        $user_id = $feed->user_id;
        if( ! $user_id ) return;

        // Get settings
        $options = get_option('fc_mycred_settings', []);
        $points  = !empty($options['post_points']) ? intval($options['post_points']) : 10;
        $label   = !empty($options['post_label']) ? sanitize_text_field($options['post_label']) : __('Post', 'fcm-mycred');

        // Award points
        mycred_add(
            'fluent_community_new_post',
            $user_id,
            $points,
            sprintf(__('Points for creating a new %s ', 'fcm-mycred'), $label),
            $feed->id
        );
    }

    public function award_points_for_comment($comment, $feed) {
        if( ! function_exists('mycred_add') ) return;

        // Handle object OR array
        $user_id = is_object($comment) && isset($comment->user_id) 
            ? $comment->user_id 
            : ( $comment['user_id'] ?? null );

        if( ! $user_id ) return;

        // Get settings
        $options = get_option('fc_mycred_settings', []);
        $points  = !empty($options['comment_points']) ? intval($options['comment_points']) : 5;
        $label   = !empty($options['comment_label']) ? sanitize_text_field($options['comment_label']) : __('Comment', 'fcm-mycred');

        $comment_id = is_object($comment) && isset($comment->id) 
            ? $comment->id 
            : ( $comment['id'] ?? null );

        // Award points
        mycred_add(
            'fluent_community_new_comment',
            $user_id,
            $points,
            sprintf(__('Points for creating a new %s', 'fcm-mycred'), $label),
            $comment_id ?: ( $feed->id ?? 0 )
        );
    }

}
