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

        // ── Rule 1: Character Count ──────────────────────────────────
        $min_chars = !empty($options['min_comment_chars']) ? intval($options['min_comment_chars']) : 0;
        if ($min_chars > 0) {
            $content = is_object($comment) && isset($comment->message) ? $comment->message : ($comment['message'] ?? '');
            $content_clean = trim(strip_tags(html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if (mb_strlen($content_clean) < $min_chars) {
                return; // Comment is too short
            }
        }

        // ── Rule 2: Echo-Chamber Prevention (Strict Reply) ───────────
        $strict_reply = !empty($options['enable_strict_reply']) ? true : false;
        if ($strict_reply) {
            $parent_id = is_object($comment) && isset($comment->parent_id) ? $comment->parent_id : ($comment['parent_id'] ?? 0);
            $post_author_id = is_object($feed) && isset($feed->user_id) ? $feed->user_id : ($feed['user_id'] ?? 0);
            
            if (empty($parent_id)) {
                // It's a top-level comment directly on the post
                if ($user_id == $post_author_id) {
                    return; // Scenario A: Author commenting on their own post
                }
            } else {
                // It's a reply to another comment
                global $wpdb;
                $parent_author_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}fcom_post_comments WHERE id = %d",
                    $parent_id
                ));
                if ($user_id == $parent_author_id) {
                    return; // Scenario C: User replying to their own comment
                }
            }
        }

        // ── Rule 3: Cooldown Limit ───────────────────────────────────
        $max_per_hour = !empty($options['max_comments_per_hour']) ? intval($options['max_comments_per_hour']) : 0;
        if ($max_per_hour > 0) {
            global $wpdb;
            $log_table = $wpdb->prefix . 'mycred_log';
            $time_1_hour_ago = time() - 3600;
            
            $recent_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$log_table} WHERE ref = %s AND user_id = %d AND time > %d",
                'fluent_community_new_comment', $user_id, $time_1_hour_ago
            ));
            
            if ($recent_count >= $max_per_hour) {
                return; // Reached max comments per hour limit
            }
        }

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
