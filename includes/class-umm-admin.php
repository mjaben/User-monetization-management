<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UMM_Admin {

    const OPTION_KEY  = 'fc_mycred_settings';
    const PAGE_SLUG   = 'user-monetization-manager';
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
            __('User Monetization', 'user-monetization-manager'),
            __('User Monetization', 'user-monetization-manager'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-money-alt',
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
            __('Points Settings', 'user-monetization-manager'),
            function () {
                echo '<p>' . esc_html__('Configure how many points users earn for actions in Fluent Community.', 'user-monetization-manager') . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'post_points',
            __('Points per Post', 'user-monetization-manager'),
            [$this, 'field_post_points'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        add_settings_field(
            'post_label',
            __('Post Label', 'user-monetization-manager'),
            [$this, 'field_post_label'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        add_settings_field(
            'comment_points',
            __('Points per Comment', 'user-monetization-manager'),
            [$this, 'field_comment_points'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        add_settings_field(
            'comment_label',
            __('Comment Label', 'user-monetization-manager'),
            [$this, 'field_comment_label'],
            self::PAGE_SLUG,
            'fc_mycred_main'
        );

        // Withdrawal Settings Section
        add_settings_section(
            'umm_withdrawal_settings',
            __('Withdrawal Settings', 'user-monetization-manager'),
            function () {
                echo '<p>' . esc_html__('Configure withdrawal requirements and limits.', 'user-monetization-manager') . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'min_withdrawal_threshold',
            __('Minimum Withdrawal Threshold', 'user-monetization-manager'),
            [$this, 'field_min_threshold'],
            self::PAGE_SLUG,
            'umm_withdrawal_settings'
        );
    }

    public function sanitize_settings( $input ) {
        $output   = [];
        $defaults = $this->get_defaults();

        // Points per Post
        $output['post_points'] = isset($input['post_points']) ? (int) $input['post_points'] : $defaults['post_points'];
        if ( $output['post_points'] < 0 ) {
            $output['post_points'] = 0;
            add_settings_error(self::OPTION_KEY, 'post_points', __('Points per Post cannot be negative.', 'user-monetization-manager'), 'error');
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
            add_settings_error(self::OPTION_KEY, 'comment_points', __('Points per Comment cannot be negative.', 'user-monetization-manager'), 'error');
        }

        // Comment Label
        $output['comment_label'] = isset($input['comment_label']) ? sanitize_text_field($input['comment_label']) : $defaults['comment_label'];
        if ( $output['comment_label'] === '' ) {
            $output['comment_label'] = $defaults['comment_label'];
        }

        // Minimum Withdrawal Threshold
        $output['min_withdrawal_threshold'] = isset($input['min_withdrawal_threshold']) ? (int) $input['min_withdrawal_threshold'] : $defaults['min_withdrawal_threshold'];
        if ( $output['min_withdrawal_threshold'] < 0 ) {
            $output['min_withdrawal_threshold'] = 0;
            add_settings_error(self::OPTION_KEY, 'min_threshold', __('Minimum withdrawal threshold cannot be negative.', 'user-monetization-manager'), 'error');
        }

        // Success notice (will show after redirect)
        add_settings_error(self::OPTION_KEY, 'settings_saved', __('Settings saved.', 'user-monetization-manager'), 'updated');

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
        echo '<p class="description">' . esc_html__('Points awarded when a user creates a community post.', 'user-monetization-manager') . '</p>';
    }

    public function field_post_label() {
        $opts = $this->get_options();
        printf(
            '<input type="text" class="regular-text" name="%1$s[post_label]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($opts['post_label'])
        );
        echo '<p class="description">' . esc_html__('Label used in logs/UI for post awards (e.g., "Post", "Thread").', 'user-monetization-manager') . '</p>';
    }

    public function field_comment_points() {
        $opts = $this->get_options();
        printf(
            '<input type="number" class="small-text" name="%1$s[comment_points]" value="%2$d" min="0" />',
            esc_attr(self::OPTION_KEY),
            (int) $opts['comment_points']
        );
        echo '<p class="description">' . esc_html__('Points awarded when a user comments in the community.', 'user-monetization-manager') . '</p>';
    }

    public function field_comment_label() {
        $opts = $this->get_options();
        printf(
            '<input type="text" class="regular-text" name="%1$s[comment_label]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($opts['comment_label'])
        );
        echo '<p class="description">' . esc_html__('Label used in logs/UI for comment awards (e.g., "Comment", "Reply").', 'user-monetization-manager') . '</p>';
    }

    public function field_min_threshold() {
        $opts = $this->get_options();
        printf(
            '<input type="number" class="small-text" name="%1$s[min_withdrawal_threshold]" value="%2$d" min="0" />',
            esc_attr(self::OPTION_KEY),
            (int) $opts['min_withdrawal_threshold']
        );
        echo '<p class="description">' . esc_html__('Minimum points required before users can withdraw. Set to 0 for no minimum.', 'user-monetization-manager') . '</p>';
    }

    /* ------------------------
     * Page renderer
     * ------------------------ */
    public function render_page() {
        if ( ! current_user_can(self::CAPABILITY) ) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'rewards';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('User Monetization Manager', 'user-monetization-manager'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=rewards" class="nav-tab <?php echo $active_tab == 'rewards' ? 'nav-tab-active' : ''; ?>"><?php _e('Rewards Settings', 'user-monetization-manager'); ?></a>
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=withdrawals" class="nav-tab <?php echo $active_tab == 'withdrawals' ? 'nav-tab-active' : ''; ?>"><?php _e('Withdrawal Requests', 'user-monetization-manager'); ?></a>
            </h2>

            <?php
            if ( $active_tab == 'rewards' ) {
                $this->render_rewards_tab();
            } else {
                $this->render_withdrawals_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_rewards_tab() {
        // Show notices (success/errors from sanitize_settings)
        settings_errors(self::OPTION_KEY);

        echo '<form action="options.php" method="post">';
        settings_fields('fc_mycred_settings_group');
        do_settings_sections(self::PAGE_SLUG);
        submit_button(__('Save Changes', 'user-monetization-manager'));
        echo '</form>';
    }

    private function render_withdrawals_tab() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mycred_isp_withdrawals';

        // Handle Approve/Reject
        if ( isset($_GET['action'], $_GET['id']) && check_admin_referer('umm_withdraw_action') ) {
            $id = intval($_GET['id']);
            $request = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE id = $id" );

            if ( $request ) {
                if ( $_GET['action'] == 'approve' && $request->status == 'pending' ) {
                    $point_type = 'mycred_default'; // change if using custom type

                    $deducted = mycred_subtract( 
                        'isp_withdrawal', 
                        $request->user_id, 
                        floatval($request->amount), 
                        'Withdrawal approved: ' . $request->phone, 
                        0, 
                        $point_type
                    );

                    if ( $deducted !== false ) {
                        $wpdb->update( $table_name, [ 'status' => 'approved' ], [ 'id' => $id ] );
                        echo '<div class="notice notice-success"><p>' . __('Withdrawal approved and points deducted.', 'user-monetization-manager') . '</p></div>';
                    }
                    else {
                        echo '<div class="notice notice-error"><p>' . __('Failed to deduct points. Check point type or balance.', 'user-monetization-manager') . '</p></div>';
                    }
                }

                if ( $_GET['action'] == 'reject' && $request->status == 'pending' ) {
                    $wpdb->update( $table_name, [ 'status' => 'rejected' ], [ 'id' => $id ] );
                    echo '<div class="notice notice-success"><p>' . __('Withdrawal rejected.', 'user-monetization-manager') . '</p></div>';
                }
            }
        }

        // Check if table exists
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            echo '<div class="notice notice-error"><p>' . __('Database table missing. Please deactivate and reactivate the plugin.', 'user-monetization-manager') . '</p></div>';
            return;
        }

        $results = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created DESC" );
        ?>
        <table class="widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Method</th>
                    <th>Details</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty($results) ): ?>
                <tr><td colspan="8"><?php _e('No withdrawal requests found.', 'user-monetization-manager'); ?></td></tr>
            <?php else: ?>
                <?php foreach( $results as $row ): 
                    $user = get_userdata( $row->user_id ); 
                    $approve_url = wp_nonce_url("?page=" . self::PAGE_SLUG . "&tab=withdrawals&action=approve&id=" . $row->id, 'umm_withdraw_action');
                    $reject_url = wp_nonce_url("?page=" . self::PAGE_SLUG . "&tab=withdrawals&action=reject&id=" . $row->id, 'umm_withdraw_action');
                    
                    $method_label = $row->withdrawal_method === 'bank' ? __('Direct Deposit', 'user-monetization-manager') : __('Airtime', 'user-monetization-manager');
                    
                    if ( $row->withdrawal_method === 'bank' ) {
                        $details = sprintf('%s (%s)', esc_html($row->account_number), esc_html(ucfirst($row->bank_name)));
                    } else {
                        $details = sprintf('%s (%s)', esc_html($row->phone), esc_html(ucfirst($row->isp)));
                    }
                    ?>
                    <tr>
                        <td><?php echo $row->id; ?></td>
                        <td><?php echo $user ? esc_html($user->user_login) : 'Deleted User'; ?></td>
                        <td><?php echo $method_label; ?></td>
                        <td><?php echo $details; ?></td>
                        <td><?php echo $row->amount; ?></td>
                        <td><?php echo ucfirst($row->status); ?></td>
                        <td><?php echo $row->created; ?></td>
                        <td>
                            <?php if ( $row->status == 'pending' ): ?>
                                <a href="<?php echo $approve_url; ?>" class="button button-small button-primary"><?php _e('Approve', 'user-monetization-manager'); ?></a>
                                <a href="<?php echo $reject_url; ?>" class="button button-small button-secondary"><?php _e('Reject', 'user-monetization-manager'); ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
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
            'min_withdrawal_threshold' => 1000,
        ];
    }

    private function get_options() {
        $opts = get_option(self::OPTION_KEY, []);
        return wp_parse_args($opts, $this->get_defaults());
    }

    public static function get_min_withdrawal_threshold() {
        $opts = get_option(self::OPTION_KEY, []);
        $defaults = [
            'post_points'    => 10,
            'post_label'     => 'Post',
            'comment_points' => 5,
            'comment_label'  => 'Comment',
            'min_withdrawal_threshold' => 1000,
        ];
        $opts = wp_parse_args($opts, $defaults);
        return (int) $opts['min_withdrawal_threshold'];
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_' . self::PAGE_SLUG ) {
            return;
        }
        // Optional custom styles for the settings screen
        $css_path = UMM_PATH . 'assets/css/admin.css';
        if ( file_exists($css_path) ) {
            wp_enqueue_style('umm-admin', UMM_URL . 'assets/css/admin.css', [], UMM_VERSION);
        }
    }
}
