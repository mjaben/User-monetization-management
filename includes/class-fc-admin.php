<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FC_MyCRED_Admin {

    const OPTION_KEY  = 'fc_mycred_settings';
    const PAGE_SLUG   = 'fc-mycred-settings';
    const CAPABILITY  = 'manage_options';

    public function __construct() {
        add_action('admin_menu',          [$this, 'register_menu']);
        add_action('admin_init',          [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /* ------------------------
     * Menu & page
     * ------------------------ */
    public function register_menu() {
        add_menu_page(
            __('FCM myCRED', 'fcm-mycred'),
            __('FCM myCRED', 'fcm-mycred'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-awards',
            81
        );
    }

    /* ------------------------
     * Settings API
     * ------------------------ */
    public function register_settings() {
        register_setting(
            'fc_mycred_settings_group',
            self::OPTION_KEY,
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'fc_mycred_main',
            __('Points Settings', 'fcm-mycred'),
            function () {
                echo '<p>' . esc_html__('Configure how many points users earn for actions in Fluent Community.', 'fcm-mycred') . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'post_points',
            __('Points per Post', 'fcm-mycred'),
            [$this, 'field_post_points'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        add_settings_field(
            'post_label',
            __('Post Label', 'fcm-mycred'),
            [$this, 'field_post_label'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        add_settings_field(
            'comment_points',
            __('Points per Comment', 'fcm-mycred'),
            [$this, 'field_comment_points'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        add_settings_field(
            'comment_label',
            __('Comment Label', 'fcm-mycred'),
            [$this, 'field_comment_label'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );
    }

    public function sanitize_settings( $input ) {
        $output   = [];
        $defaults = $this->get_defaults();

        // Points per Post
        $output['post_points'] = isset($input['post_points']) ? (int) $input['post_points'] : $defaults['post_points'];
        if ( $output['post_points'] < 0 ) {
            $output['post_points'] = 0;
            add_settings_error(self::OPTION_KEY, 'post_points', __('Points per Post cannot be negative.', 'fcm-mycred'), 'error');
        }

        // Post Label
        $output['post_label'] = isset($input['post_label']) ? sanitize_text_field($input['post_label']) : $defaults['post_label'];
        if ( $output['post_label'] === '' ) {
            $output['post_label'] = $defaults['post_label'];
        }

        // Points per Comment
        $output['comment_points'] = isset($input['comment_points']) ? (int) $input['comment_points'] : $defaults['comment_points'];
        if ( $output['comment_points'] < 0 ) {
            $output['comment_points'] = 0;
            add_settings_error(self::OPTION_KEY, 'comment_points', __('Points per Comment cannot be negative.', 'fcm-mycred'), 'error');
        }

        // Comment Label
        $output['comment_label'] = isset($input['comment_label']) ? sanitize_text_field($input['comment_label']) : $defaults['comment_label'];
        if ( $output['comment_label'] === '' ) {
            $output['comment_label'] = $defaults['comment_label'];
        }

        // Success notice (will show after redirect)
        add_settings_error(self::OPTION_KEY, 'settings_saved', __('Settings saved.', 'fcm-mycred'), 'updated');

        return $output;
    }

    /* ------------------------
     * Fields
     * ------------------------ */
    public function field_post_points() {
        $opts = $this->get_options();
        printf(
            '<input type="number" class="small-text" name="%1$s[post_points]" value="%2$d" min="0" />',
            esc_attr(self::OPTION_KEY),
            (int) $opts['post_points']
        );
        echo '<p class="description">' . esc_html__('Points awarded when a user creates a community post.', 'fcm-mycred') . '</p>';
    }

    public function field_post_label() {
        $opts = $this->get_options();
        printf(
            '<input type="text" class="regular-text" name="%1$s[post_label]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($opts['post_label'])
        );
        echo '<p class="description">' . esc_html__('Label used in logs/UI for post awards (e.g., "Post", "Thread").', 'fcm-mycred') . '</p>';
    }

    public function field_comment_points() {
        $opts = $this->get_options();
        printf(
            '<input type="number" class="small-text" name="%1$s[comment_points]" value="%2$d" min="0" />',
            esc_attr(self::OPTION_KEY),
            (int) $opts['comment_points']
        );
        echo '<p class="description">' . esc_html__('Points awarded when a user comments in the community.', 'fcm-mycred') . '</p>';
    }

    public function field_comment_label() {
        $opts = $this->get_options();
        printf(
            '<input type="text" class="regular-text" name="%1$s[comment_label]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($opts['comment_label'])
        );
        echo '<p class="description">' . esc_html__('Label used in logs/UI for comment awards (e.g., "Comment", "Reply").', 'fcm-mycred') . '</p>';
    }

    /* ------------------------
     * Page renderer
     * ------------------------ */
    public function render_page() {
        if ( ! current_user_can(self::CAPABILITY) ) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('FCM myCRED Integration', 'fcm-mycred') . '</h1>';

        // Show notices (success/errors from sanitize_settings)
        settings_errors(self::OPTION_KEY);

        echo '<form action="options.php" method="post">';
        settings_fields('fc_mycred_settings_group');
        do_settings_sections(self::PAGE_SLUG);
        submit_button(__('Save Changes', 'fcm-mycred'));
        echo '</form>';

        echo '</div>';
    }

    /* ------------------------
     * Helpers
     * ------------------------ */
    private function get_defaults() {
        return [
            'post_points'    => 10,
            'post_label'     => 'Post',
            'comment_points' => 5,
            'comment_label'  => 'Comment',
        ];
    }

    private function get_options() {
        $opts = get_option(self::OPTION_KEY, []);
        return wp_parse_args($opts, $this->get_defaults());
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_' . self::PAGE_SLUG ) {
            return;
        }
        // Optional custom styles for the settings screen
        $css_path = FCM_MYCred_PATH . 'assets/css/admin.css';
        if ( file_exists($css_path) ) {
            wp_enqueue_style('fcm-mycred-admin', FCM_MYCred_URL . 'assets/css/admin.css', [], '1.0');
        }
    }
}
