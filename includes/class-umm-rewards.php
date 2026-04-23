<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UMM_Rewards {

    public function __construct() {
        add_action('fluent_community/feed/created', [$this, 'award_points_for_feed']);
        add_action('fluent_community/comment_added', [$this, 'award_points_for_comment'], 10, 2);
    }

    public function award_points_for_feed($feed) {
        if( ! function_exists('mycred_add') ) return;

        $user_id = $feed->user_id ?? null;
        $feed_id = $feed->id ?? 0;

        if( ! $user_id ) return;

        // Get settings
        $options = get_option('fc_mycred_settings', []);
        $points  = !empty($options['post_points']) ? floatval($options['post_points']) : 10;
        $label   = !empty($options['post_label']) ? sanitize_text_field($options['post_label']) : __('Post', 'user-monetization-manager');

        // Check if already awarded
        if ( function_exists('mycred_get_users_log_entries') ) {
            $existing = mycred_get_users_log_entries($user_id, 0, 1, [], [
                'ref' => 'fluent_community_new_post',
                'ref_id' => $feed_id
            ]);
            if (!empty($existing)) {
                return; // Already awarded
            }
        }

        // Award points
        mycred_add(
            'fluent_community_new_post', // static reference
            $user_id,
            $points,
            sprintf(__('Points for creating a new %s in Fluent Community', 'user-monetization-manager'), $label),
            $feed_id
        );
    }

    public function award_points_for_comment($comment, $feed) {
        if( ! function_exists('mycred_add') ) return;

        $user_id = is_object($comment) && isset($comment->user_id) 
            ? $comment->user_id 
            : ( $comment['user_id'] ?? null );

        if( ! $user_id ) return;

        $comment_id = is_object($comment) && isset($comment->id) 
            ? $comment->id 
            : ( $comment['id'] ?? 0 );

        // Get settings
        $options = get_option('fc_mycred_settings', []);
        $points  = !empty($options['comment_points']) ? floatval($options['comment_points']) : 5;
        $label   = !empty($options['comment_label']) ? sanitize_text_field($options['comment_label']) : __('Comment', 'user-monetization-manager');

        // Check if already awarded
        if ( function_exists('mycred_get_users_log_entries') ) {
            $existing = mycred_get_users_log_entries($user_id, 0, 1, [], [
                'ref' => 'fluent_community_new_comment',
                'ref_id' => $comment_id
            ]);
            if (!empty($existing)) {
                return; // Already awarded
            }
        }

        // Award points
        mycred_add(
            'fluent_community_new_comment', // static reference
            $user_id,
            $points,
            sprintf(__('Points for creating a new %s in Fluent Community', 'user-monetization-manager'), $label),
            $comment_id ?: ($feed->id ?? 0)
        );
    }

}
