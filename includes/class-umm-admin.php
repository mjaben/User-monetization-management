<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UMM_Admin {

    const OPTION_KEY  = 'fc_mycred_settings';
    const PAGE_SLUG   = 'user-monetization-manager';
    const CAPABILITY  = 'manage_options';

    public function __construct() {
        add_action( 'admin_menu',            [$this, 'register_menu'] );
        add_action( 'admin_init',            [$this, 'register_settings'] );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_assets'] );
        add_action( 'wp_ajax_umm_clear_declined', [$this, 'ajax_clear_declined'] );
    }

    /* ── Menu ───────────────────────────────────────────────────── */
    public function register_menu() {
        add_menu_page(
            __( 'User Monetization', 'user-monetization-manager' ),
            __( 'User Monetization', 'user-monetization-manager' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-money-alt',
            81
        );
    }

    /* ── Settings API ───────────────────────────────────────────── */
    public function register_settings() {
        register_setting( 'fc_mycred_settings_group', self::OPTION_KEY, [$this, 'sanitize_settings'] );

        // Points section
        add_settings_section( 'fc_mycred_main', __( 'Points Settings', 'user-monetization-manager' ),
            function() { echo '<p>' . esc_html__( 'Configure how many points users earn for actions in Fluent Community.', 'user-monetization-manager' ) . '</p>'; },
            self::PAGE_SLUG );

        foreach ( [
            ['post_points',    __( 'Points per Post',    'user-monetization-manager' ), [$this,'field_post_points']],
            ['post_label',     __( 'Post Label',         'user-monetization-manager' ), [$this,'field_post_label']],
            ['comment_points', __( 'Points per Comment', 'user-monetization-manager' ), [$this,'field_comment_points']],
            ['comment_label',  __( 'Comment Label',      'user-monetization-manager' ), [$this,'field_comment_label']],
            ['min_comment_chars',    __( 'Min Comment Characters',      'user-monetization-manager' ), [$this,'field_min_comment_chars']],
            ['max_comments_per_hour',__( 'Max Comments per Hour',       'user-monetization-manager' ), [$this,'field_max_comments_per_hour']],
            ['enable_strict_reply',  __( 'Enable Strict Reply Mode',    'user-monetization-manager' ), [$this,'field_enable_strict_reply']],
            ['excluded_words',       __( 'Excluded Words',              'user-monetization-manager' ), [$this,'field_excluded_words']],
        ] as [$id, $label, $cb] ) {
            add_settings_field( $id, $label, $cb, self::PAGE_SLUG, 'fc_mycred_main' );
        }

        // Referral section
        add_settings_section( 'umm_referral_settings', __( 'Referral Settings', 'user-monetization-manager' ),
            function() { echo '<p>' . esc_html__( 'Configure rewards for user referrals.', 'user-monetization-manager' ) . '</p>'; },
            self::PAGE_SLUG );

        foreach ( [
            ['enable_referrals',        __( 'Enable Referrals',           'user-monetization-manager' ), [$this,'field_enable_referrals']],
            ['referral_visit_points',   __( 'Points per Visitor',         'user-monetization-manager' ), [$this,'field_referral_visit_points']],
            ['referral_signup_points',  __( 'Points per Signup',          'user-monetization-manager' ), [$this,'field_referral_signup_points']],
        ] as [$id, $label, $cb] ) {
            add_settings_field( $id, $label, $cb, self::PAGE_SLUG, 'umm_referral_settings' );
        }

        // Withdrawal section
        add_settings_section( 'umm_withdrawal_settings', __( 'Withdrawal Settings', 'user-monetization-manager' ),
            function() { echo '<p>' . esc_html__( 'Configure withdrawal requirements, limits and notifications.', 'user-monetization-manager' ) . '</p>'; },
            self::PAGE_SLUG );

        foreach ( [
            ['min_withdrawal_threshold', __( 'Minimum Withdrawal Threshold', 'user-monetization-manager' ), [$this,'field_min_threshold']],
            ['enable_airtime_withdrawal', __( 'Enable Airtime Top-up',       'user-monetization-manager' ), [$this,'field_enable_airtime']],
            ['enable_bank_withdrawal',   __( 'Enable Direct Deposit (Bank)', 'user-monetization-manager' ), [$this,'field_enable_bank']],
            ['enable_data_withdrawal',   __( 'Enable Internet Data',         'user-monetization-manager' ), [$this,'field_enable_data']],
            ['manager_emails',           __( 'Manager Alert Emails',         'user-monetization-manager' ), [$this,'field_manager_emails']],
        ] as [$id, $label, $cb] ) {
            add_settings_field( $id, $label, $cb, self::PAGE_SLUG, 'umm_withdrawal_settings' );
        }
    }

    public function sanitize_settings( $input ) {
        $output   = [];
        $defaults = $this->get_defaults();

        $output['post_points'] = max( 0, (float) ( $input['post_points'] ?? $defaults['post_points'] ) );
        $output['post_label']  = sanitize_text_field( $input['post_label'] ?? '' ) ?: $defaults['post_label'];

        $output['comment_points'] = max( 0, (float) ( $input['comment_points'] ?? $defaults['comment_points'] ) );
        $output['comment_label']  = sanitize_text_field( $input['comment_label'] ?? '' ) ?: $defaults['comment_label'];

        $output['min_comment_chars']     = max( 0, (int) ( $input['min_comment_chars'] ?? $defaults['min_comment_chars'] ) );
        $output['max_comments_per_hour'] = max( 0, (int) ( $input['max_comments_per_hour'] ?? $defaults['max_comments_per_hour'] ) );
        $output['enable_strict_reply']   = isset( $input['enable_strict_reply'] ) ? 1 : 0;

        // Excluded words — sanitize and normalise to lowercase, comma-separated
        $raw_words = sanitize_textarea_field( $input['excluded_words'] ?? '' );
        $words     = array_filter( array_map( 'trim', explode( ',', $raw_words ) ) );
        $output['excluded_words'] = implode( ', ', array_map( 'mb_strtolower', $words ) );

        $output['enable_referrals']       = isset( $input['enable_referrals'] ) ? 1 : 0;
        $output['referral_visit_points']  = max( 0, (float) ( $input['referral_visit_points'] ?? $defaults['referral_visit_points'] ) );
        $output['referral_signup_points'] = max( 0, (float) ( $input['referral_signup_points'] ?? $defaults['referral_signup_points'] ) );

        $output['min_withdrawal_threshold']  = max( 0, (float) ( $input['min_withdrawal_threshold'] ?? $defaults['min_withdrawal_threshold'] ) );
        $output['enable_airtime_withdrawal'] = isset( $input['enable_airtime_withdrawal'] ) ? 1 : 0;
        $output['enable_bank_withdrawal']    = isset( $input['enable_bank_withdrawal'] ) ? 1 : 0;
        $output['enable_data_withdrawal']    = isset( $input['enable_data_withdrawal'] ) ? 1 : 0;

        // Manager emails — sanitize each address individually
        $raw_emails = sanitize_textarea_field( $input['manager_emails'] ?? '' );
        $emails     = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw_emails ) ) ) );
        $output['manager_emails'] = implode( ', ', $emails );

        add_settings_error( self::OPTION_KEY, 'settings_saved', __( 'Settings saved.', 'user-monetization-manager' ), 'updated' );
        return $output;
    }

    /* ── Field renderers ────────────────────────────────────────── */
    public function field_post_points() {
        $o = $this->get_options();
        printf( '<input type="number" step="0.01" class="small-text" name="%s[post_points]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (float) $o['post_points'] ) );
        echo '<p class="description">' . esc_html__( 'Points awarded when a user creates a community post.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_post_label() {
        $o = $this->get_options();
        printf( '<input type="text" class="regular-text" name="%s[post_label]" value="%s" />', esc_attr( self::OPTION_KEY ), esc_attr( $o['post_label'] ) );
        echo '<p class="description">' . esc_html__( 'Label for post awards (e.g. "Post", "Thread").', 'user-monetization-manager' ) . '</p>';
    }

    public function field_comment_points() {
        $o = $this->get_options();
        printf( '<input type="number" step="0.01" class="small-text" name="%s[comment_points]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (float) $o['comment_points'] ) );
        echo '<p class="description">' . esc_html__( 'Points awarded when a user comments in the community.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_comment_label() {
        $o = $this->get_options();
        printf( '<input type="text" class="regular-text" name="%s[comment_label]" value="%s" />', esc_attr( self::OPTION_KEY ), esc_attr( $o['comment_label'] ) );
        echo '<p class="description">' . esc_html__( 'Label for comment awards (e.g. "Comment", "Reply").', 'user-monetization-manager' ) . '</p>';
    }

    public function field_min_comment_chars() {
        $o = $this->get_options();
        printf( '<input type="number" step="1" class="small-text" name="%s[min_comment_chars]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (int) $o['min_comment_chars'] ) );
        echo '<p class="description">' . esc_html__( 'Minimum character length required for a comment to be rewarded (0 to disable).', 'user-monetization-manager' ) . '</p>';
    }

    public function field_max_comments_per_hour() {
        $o = $this->get_options();
        printf( '<input type="number" step="1" class="small-text" name="%s[max_comments_per_hour]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (int) $o['max_comments_per_hour'] ) );
        echo '<p class="description">' . esc_html__( 'Maximum number of comments a user can be rewarded for per hour (Cooldown limit). Set to 0 to disable.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_enable_strict_reply() {
        $o = $this->get_options();
        printf( '<label><input type="checkbox" name="%s[enable_strict_reply]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_KEY ), checked( 1, (int) $o['enable_strict_reply'], false ),
            esc_html__( 'Enable Strict Reply Hierarchy (Echo-Chamber Prevention)', 'user-monetization-manager' ) );
        echo '<p class="description">' . esc_html__( 'If enabled, users will not be rewarded for replying to their own posts or comments.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_excluded_words() {
        $o = $this->get_options();
        printf(
            '<textarea name="%s[excluded_words]" rows="4" class="large-text" placeholder="%s">%s</textarea>',
            esc_attr( self::OPTION_KEY ),
            esc_attr__( 'nice, great, wow, lol', 'user-monetization-manager' ),
            esc_textarea( $o['excluded_words'] )
        );
        echo '<p class="description">' . esc_html__( 'Comma-separated list of words. If a comment contains any of these words, the user will NOT be rewarded. Matching is case-insensitive and whole-word (e.g. "nice" will not block "nicely").', 'user-monetization-manager' ) . '</p>';
    }

    public function field_enable_referrals() {
        $o = $this->get_options();
        printf( '<label><input type="checkbox" name="%s[enable_referrals]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_KEY ), checked( 1, (int) $o['enable_referrals'], false ),
            esc_html__( 'Enable referral tracking and rewards', 'user-monetization-manager' ) );
        echo '<p class="description">' . esc_html__( 'Users can refer others using their unique link. Use shortcode [umm_referral_link] to display it.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_referral_visit_points() {
        $o = $this->get_options();
        printf( '<input type="number" step="0.01" class="small-text" name="%s[referral_visit_points]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (float) $o['referral_visit_points'] ) );
        echo '<p class="description">' . esc_html__( 'Points awarded for each unique visitor referred. Set to 0 to disable.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_referral_signup_points() {
        $o = $this->get_options();
        printf( '<input type="number" step="0.01" class="small-text" name="%s[referral_signup_points]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (float) $o['referral_signup_points'] ) );
        echo '<p class="description">' . esc_html__( 'Points awarded when a referred user successfully signs up. Set to 0 to disable.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_min_threshold() {
        $o = $this->get_options();
        printf( '<input type="number" step="0.01" class="small-text" name="%s[min_withdrawal_threshold]" value="%s" min="0" />', esc_attr( self::OPTION_KEY ), esc_attr( (float) $o['min_withdrawal_threshold'] ) );
        echo '<p class="description">' . esc_html__( 'Minimum points required before users can withdraw. Set to 0 for no minimum.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_enable_airtime() {
        $o = $this->get_options();
        printf( '<label><input type="checkbox" name="%s[enable_airtime_withdrawal]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_KEY ), checked( 1, (int) $o['enable_airtime_withdrawal'], false ),
            esc_html__( 'Allow users to redeem points as airtime top-up', 'user-monetization-manager' ) );
        echo '<p class="description">' . esc_html__( 'When disabled, Airtime Top-up is hidden from the withdrawal form.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_enable_bank() {
        $o = $this->get_options();
        printf( '<label><input type="checkbox" name="%s[enable_bank_withdrawal]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_KEY ), checked( 1, (int) $o['enable_bank_withdrawal'], false ),
            esc_html__( 'Allow users to redeem points via direct bank deposit', 'user-monetization-manager' ) );
        echo '<p class="description">' . esc_html__( 'When disabled, Direct Deposit is hidden from the withdrawal form.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_enable_data() {
        $o = $this->get_options();
        printf( '<label><input type="checkbox" name="%s[enable_data_withdrawal]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_KEY ), checked( 1, (int) $o['enable_data_withdrawal'], false ),
            esc_html__( 'Allow users to redeem points for internet data', 'user-monetization-manager' ) );
        echo '<p class="description">' . esc_html__( 'When disabled, Internet Data is hidden from the withdrawal form.', 'user-monetization-manager' ) . '</p>';
    }

    public function field_manager_emails() {
        $o = $this->get_options();
        printf( '<input type="text" class="large-text" name="%s[manager_emails]" value="%s" placeholder="admin@example.com, manager@example.com" />',
            esc_attr( self::OPTION_KEY ), esc_attr( $o['manager_emails'] ) );
        echo '<p class="description">' . esc_html__( 'Comma-separated list of email addresses that receive an alert when a new withdrawal request is submitted.', 'user-monetization-manager' ) . '</p>';
    }

    /* ── Page renderer ──────────────────────────────────────────── */
    public function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) return;

        $active_tab = sanitize_key( $_GET['tab'] ?? 'rewards' );
        ?>
        <div class="wrap umm-admin-wrap">
            <h1 class="umm-page-title">
                <span class="dashicons dashicons-money-alt"></span>
                <?php esc_html_e( 'User Monetization Manager', 'user-monetization-manager' ); ?>
            </h1>

            <nav class="umm-nav-tabs">
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=rewards"
                   class="umm-tab <?php echo $active_tab === 'rewards' ? 'is-active' : ''; ?>">
                    <?php esc_html_e( 'Rewards Settings', 'user-monetization-manager' ); ?>
                </a>
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=withdrawals"
                   class="umm-tab <?php echo $active_tab === 'withdrawals' ? 'is-active' : ''; ?>">
                    <?php esc_html_e( 'Withdrawals', 'user-monetization-manager' ); ?>
                </a>
            </nav>

            <?php
            if ( $active_tab === 'rewards' ) {
                $this->render_rewards_tab();
            } else {
                $this->render_withdrawals_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_rewards_tab() {
        settings_errors( self::OPTION_KEY );
        echo '<form action="options.php" method="post" class="umm-settings-form">';
        settings_fields( 'fc_mycred_settings_group' );
        do_settings_sections( self::PAGE_SLUG );
        submit_button( __( 'Save Changes', 'user-monetization-manager' ) );
        echo '</form>';
    }

    /* ── Withdrawals Dashboard ──────────────────────────────────── */
    private function render_withdrawals_tab() {
        global $wpdb;
        $table = $wpdb->prefix . 'mycred_isp_withdrawals';

        // ── Handle approve / reject ─────────────────────────────
        if ( isset( $_GET['action'], $_GET['id'] ) && check_admin_referer( 'umm_withdraw_action' ) ) {
            $id      = absint( $_GET['id'] );
            $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

            if ( $request ) {
                $action = sanitize_key( $_GET['action'] );

                if ( $action === 'approve' && $request->status === 'pending' ) {
                    $desc = $request->withdrawal_method === 'bank'
                        ? 'Withdrawal approved: ' . $request->account_number
                        : 'Withdrawal approved: ' . $request->phone;

                    $deducted = mycred_subtract( 'isp_withdrawal', $request->user_id, floatval( $request->amount ), $desc, 0, 'mycred_default' );

                    if ( $deducted !== false ) {
                        $wpdb->update( $table, ['status' => 'approved'], ['id' => $id] );
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Withdrawal approved and points deducted.', 'user-monetization-manager' ) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to deduct points. Check point type or balance.', 'user-monetization-manager' ) . '</p></div>';
                    }
                }

                if ( $action === 'reject' && $request->status === 'pending' ) {
                    $wpdb->update( $table, ['status' => 'rejected'], ['id' => $id] );
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Withdrawal request rejected.', 'user-monetization-manager' ) . '</p></div>';
                }
            }
        }

        // ── Table check ─────────────────────────────────────────
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Database table missing. Please deactivate and reactivate the plugin.', 'user-monetization-manager' ) . '</p></div>';
            return;
        }

        // ── Analytics queries ─────────────────────────────────
        $stats = $this->get_analytics( $table );

        // ── Monthly data for chart (last 6 months) ────────────
        $monthly = $this->get_monthly_data( $table );

        // ── All requests ───────────────────────────────────────
        $requests = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created DESC" );

        // ── Active filter ──────────────────────────────────────
        $filter = sanitize_key( $_GET['filter'] ?? 'all' );
        ?>

        <div class="umm-dashboard">

            <!-- ── Stat Cards ── -->
            <div class="umm-stat-grid">

                <div class="umm-stat-card umm-stat-airtime">
                    <div class="umm-stat-icon">📱</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Airtime Approved', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['airtime_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts total', 'user-monetization-manager' ), number_format( $stats['airtime_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-bank">
                    <div class="umm-stat-icon">🏦</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Bank Approved', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['bank_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts total', 'user-monetization-manager' ), number_format( $stats['bank_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-data">
                    <div class="umm-stat-icon">📶</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Data Approved', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['data_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts total', 'user-monetization-manager' ), number_format( $stats['data_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-total-approved">
                    <div class="umm-stat-icon">✅</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Total Approved', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['airtime_count'] + $stats['bank_count'] + $stats['data_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts total', 'user-monetization-manager' ), number_format( $stats['airtime_total'] + $stats['bank_total'] + $stats['data_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-current-month">
                    <div class="umm-stat-icon">📅</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Current Month', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['current_month_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts total', 'user-monetization-manager' ), number_format( $stats['current_month_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-daily">
                    <div class="umm-stat-icon">⚡</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Daily Distributed', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['daily_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts today', 'user-monetization-manager' ), number_format( $stats['daily_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-pending">
                    <div class="umm-stat-icon">⏳</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Pending', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['pending_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts queued', 'user-monetization-manager' ), number_format( $stats['pending_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-rejected">
                    <div class="umm-stat-icon">❌</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'Rejected', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( $stats['rejected_count'] ); ?></div>
                        <div class="umm-stat-sub"><?php printf( esc_html__( '%s pts declined', 'user-monetization-manager' ), number_format( $stats['rejected_total'], 2 ) ); ?></div>
                    </div>
                </div>

                <div class="umm-stat-card umm-stat-all-time">
                    <div class="umm-stat-icon">📊</div>
                    <div class="umm-stat-body">
                        <div class="umm-stat-label"><?php esc_html_e( 'All-time Requests', 'user-monetization-manager' ); ?></div>
                        <div class="umm-stat-value"><?php echo number_format( count( $requests ) ); ?></div>
                        <div class="umm-stat-sub"><?php esc_html_e( 'total submissions', 'user-monetization-manager' ); ?></div>
                    </div>
                </div>

            </div><!-- /.umm-stat-grid -->

            <!-- ── Charts ── -->
            <div class="umm-chart-grid">

                <div class="umm-chart-card">
                    <div class="umm-chart-header">
                        <h3><?php esc_html_e( 'Method Breakdown', 'user-monetization-manager' ); ?></h3>
                        <span class="umm-chart-sub"><?php esc_html_e( 'Approved requests', 'user-monetization-manager' ); ?></span>
                    </div>
                    <div class="umm-chart-body umm-chart-donut-wrap">
                        <canvas id="umm-chart-donut" width="220" height="220"></canvas>
                        <div class="umm-donut-legend">
                            <span class="umm-legend-dot umm-dot-airtime"></span><?php esc_html_e( 'Airtime', 'user-monetization-manager' ); ?>
                            &nbsp;&nbsp;
                            <span class="umm-legend-dot umm-dot-bank"></span><?php esc_html_e( 'Bank', 'user-monetization-manager' ); ?>
                            &nbsp;&nbsp;
                            <span class="umm-legend-dot umm-dot-data" style="background:#10b981; display:inline-block; width:10px; height:10px; border-radius:50%;"></span><?php esc_html_e( 'Data', 'user-monetization-manager' ); ?>
                        </div>
                    </div>
                </div>

                <div class="umm-chart-card">
                    <div class="umm-chart-header">
                        <h3><?php esc_html_e( 'Monthly Approved Points', 'user-monetization-manager' ); ?></h3>
                        <span class="umm-chart-sub"><?php esc_html_e( 'Last 6 months', 'user-monetization-manager' ); ?></span>
                    </div>
                    <div class="umm-chart-body">
                        <canvas id="umm-chart-bar" height="120"></canvas>
                    </div>
                </div>

            </div><!-- /.umm-chart-grid -->

            <!-- ── Filter + Actions bar ── -->
            <div class="umm-toolbar">
                <div class="umm-filter-pills">
                    <?php foreach ( ['all' => __('All','user-monetization-manager'), 'pending' => __('Pending','user-monetization-manager'), 'approved' => __('Approved','user-monetization-manager'), 'rejected' => __('Rejected','user-monetization-manager')] as $key => $label ) : ?>
                        <button class="umm-pill <?php echo $filter === $key ? 'is-active' : ''; ?>"
                                data-filter="<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $label ); ?>
                            <span class="umm-pill-count" data-count="<?php echo esc_attr( $key ); ?>"></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php if ( $stats['rejected_count'] > 0 ) : ?>
                    <button id="umm-clear-declined"
                            class="umm-btn-danger"
                            data-nonce="<?php echo esc_attr( wp_create_nonce('umm_clear_declined') ); ?>">
                        🗑 <?php esc_html_e( 'Clear Declined History', 'user-monetization-manager' ); ?>
                    </button>
                <?php endif; ?>
            </div><!-- /.umm-toolbar -->

            <!-- ── Requests Table ── -->
            <div class="umm-table-card">
                <?php if ( empty( $requests ) ) : ?>
                    <div class="umm-empty">
                        <span class="umm-empty-icon">📭</span>
                        <p><?php esc_html_e( 'No withdrawal requests yet.', 'user-monetization-manager' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="umm-table" id="umm-requests-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( '#', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'User', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'Method', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'Details', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'Amount', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'user-monetization-manager' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'user-monetization-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $requests as $row ) :
                            $user        = get_userdata( $row->user_id );
                            $username    = $user ? esc_html( $user->display_name ) : __( 'Deleted User', 'user-monetization-manager' );
                            $initials    = $user ? strtoupper( substr( $user->display_name, 0, 2 ) ) : 'DU';
                            $is_bank     = $row->withdrawal_method === 'bank';
                            $is_data     = $row->withdrawal_method === 'data';
                            $method_label = $is_bank ? __( 'Bank', 'user-monetization-manager' ) : ( $is_data ? __( 'Data', 'user-monetization-manager' ) : __( 'Airtime', 'user-monetization-manager' ) );
                            $details     = $is_bank
                                ? esc_html( $row->account_number ) . ' <em>(' . esc_html( ucfirst( $row->bank_name ) ) . ')</em>'
                                : esc_html( $row->phone ) . ' <em>(' . esc_html( strtoupper( $row->isp ) ) . ')</em>';

                            $approve_url = wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=withdrawals&action=approve&id=' . $row->id ), 'umm_withdraw_action' );
                            $reject_url  = wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=withdrawals&action=reject&id='  . $row->id ), 'umm_withdraw_action' );
                            $status_class = 'umm-badge-' . esc_attr( $row->status );
                        ?>
                            <tr data-status="<?php echo esc_attr( $row->status ); ?>">
                                <td class="umm-td-id">#<?php echo absint( $row->id ); ?></td>
                                <td>
                                    <div class="umm-user-cell">
                                        <div class="umm-avatar"><?php echo esc_html( $initials ); ?></div>
                                        <span><?php echo $username; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="umm-method-badge umm-method-<?php echo esc_attr( $row->withdrawal_method ); ?>">
                                        <?php echo $is_bank ? '🏦' : ($is_data ? '📶' : '📱'); ?> <?php echo esc_html( $method_label ); ?>
                                    </span>
                                </td>
                                <td class="umm-td-details"><?php echo $details; ?></td>
                                <td class="umm-td-amount"><strong><?php echo number_format( floatval( $row->amount ), 2 ); ?></strong> <small><?php esc_html_e( 'pts', 'user-monetization-manager' ); ?></small></td>
                                <td><span class="umm-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
                                <td class="umm-td-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $row->created ) ) ); ?></td>
                                <td class="umm-td-actions">
                                    <?php if ( $row->status === 'pending' ) : ?>
                                        <a href="<?php echo esc_url( $approve_url ); ?>" class="umm-action-btn umm-approve-btn" title="<?php esc_attr_e( 'Approve', 'user-monetization-manager' ); ?>">✓</a>
                                        <a href="<?php echo esc_url( $reject_url ); ?>"  class="umm-action-btn umm-reject-btn"  title="<?php esc_attr_e( 'Reject', 'user-monetization-manager' ); ?>"  onclick="return confirm('<?php esc_attr_e( 'Reject this request?', 'user-monetization-manager' ); ?>')">✕</a>
                                    <?php else : ?>
                                        <span class="umm-action-done">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div><!-- /.umm-table-card -->

        </div><!-- /.umm-dashboard -->

        <?php
    }

    /* ── Analytics helper ───────────────────────────────────────── */
    private function get_analytics( $table ) {
        global $wpdb;

        $row = $wpdb->get_row( "
            SELECT
                SUM( CASE WHEN status='approved' AND withdrawal_method='isp'  THEN 1     ELSE 0 END ) AS airtime_count,
                SUM( CASE WHEN status='approved' AND withdrawal_method='isp'  THEN amount ELSE 0 END ) AS airtime_total,
                SUM( CASE WHEN status='approved' AND withdrawal_method='bank' THEN 1     ELSE 0 END ) AS bank_count,
                SUM( CASE WHEN status='approved' AND withdrawal_method='bank' THEN amount ELSE 0 END ) AS bank_total,
                SUM( CASE WHEN status='approved' AND withdrawal_method='data' THEN 1     ELSE 0 END ) AS data_count,
                SUM( CASE WHEN status='approved' AND withdrawal_method='data' THEN amount ELSE 0 END ) AS data_total,
                SUM( CASE WHEN status='pending'  THEN 1     ELSE 0 END ) AS pending_count,
                SUM( CASE WHEN status='pending'  THEN amount ELSE 0 END ) AS pending_total,
                SUM( CASE WHEN status='rejected' THEN 1     ELSE 0 END ) AS rejected_count,
                SUM( CASE WHEN status='rejected' THEN amount ELSE 0 END ) AS rejected_total,
                SUM( CASE WHEN status='approved' AND MONTH(created) = MONTH(CURRENT_DATE()) AND YEAR(created) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END ) AS current_month_count,
                SUM( CASE WHEN status='approved' AND MONTH(created) = MONTH(CURRENT_DATE()) AND YEAR(created) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END ) AS current_month_total,
                SUM( CASE WHEN status='approved' AND DATE(created) = CURRENT_DATE() THEN 1 ELSE 0 END ) AS daily_count,
                SUM( CASE WHEN status='approved' AND DATE(created) = CURRENT_DATE() THEN amount ELSE 0 END ) AS daily_total
            FROM {$table}
        " );

        return [
            'airtime_count'       => (int)   ( $row->airtime_count       ?? 0 ),
            'airtime_total'       => (float) ( $row->airtime_total       ?? 0 ),
            'bank_count'          => (int)   ( $row->bank_count          ?? 0 ),
            'bank_total'          => (float) ( $row->bank_total          ?? 0 ),
            'data_count'          => (int)   ( $row->data_count          ?? 0 ),
            'data_total'          => (float) ( $row->data_total          ?? 0 ),
            'pending_count'       => (int)   ( $row->pending_count       ?? 0 ),
            'pending_total'       => (float) ( $row->pending_total       ?? 0 ),
            'rejected_count'      => (int)   ( $row->rejected_count      ?? 0 ),
            'rejected_total'      => (float) ( $row->rejected_total      ?? 0 ),
            'current_month_count' => (int)   ( $row->current_month_count ?? 0 ),
            'current_month_total' => (float) ( $row->current_month_total ?? 0 ),
            'daily_count'         => (int)   ( $row->daily_count         ?? 0 ),
            'daily_total'         => (float) ( $row->daily_total         ?? 0 ),
        ];
    }

    /* ── Monthly data for bar chart ─────────────────────────────── */
    private function get_monthly_data( $table ) {
        global $wpdb;
        $rows = $wpdb->get_results( "
            SELECT DATE_FORMAT(created,'%Y-%m') AS month,
                   SUM(amount) AS total
            FROM   {$table}
            WHERE  status='approved'
              AND  created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP  BY month
            ORDER  BY month ASC
        " );

        $data   = [];
        $labels = [];
        for ( $i = 5; $i >= 0; $i-- ) {
            $key      = date( 'Y-m', strtotime( "-{$i} months" ) );
            $label    = date( 'M Y', strtotime( "-{$i} months" ) );
            $labels[] = $label;
            $data[ $key ] = 0;
        }

        foreach ( $rows as $r ) {
            if ( isset( $data[ $r->month ] ) ) {
                $data[ $r->month ] = (float) $r->total;
            }
        }

        return [ 'labels' => $labels, 'values' => array_values( $data ) ];
    }

    /* ── AJAX: clear declined ───────────────────────────────────── */
    public function ajax_clear_declined() {
        check_ajax_referer( 'umm_clear_declined', 'nonce' );
        if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( ['message' => __( 'Unauthorised.', 'user-monetization-manager' )] );

        global $wpdb;
        $table = $wpdb->prefix . 'mycred_isp_withdrawals';
        $deleted = $wpdb->delete( $table, ['status' => 'rejected'], ['%s'] );

        wp_send_json_success( [
            'message' => sprintf( _n( '%d declined request cleared.', '%d declined requests cleared.', $deleted, 'user-monetization-manager' ), $deleted ),
            'deleted' => $deleted,
        ] );
    }

    /* ── Assets ─────────────────────────────────────────────────── */
    public function enqueue_assets( $hook ) {
        if ( empty( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) return;

        // Styles
        wp_enqueue_style( 'umm-admin', UMM_URL . 'assets/css/admin.css', [], UMM_VERSION );

        // Chart.js from CDN
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js', [], '4.4.2', true );

        // Our admin JS
        wp_enqueue_script( 'umm-admin', UMM_URL . 'assets/js/umm-admin.js', ['jquery', 'chartjs'], UMM_VERSION, true );

        // Pass chart data + nonce to JS
        global $wpdb;
        $table   = $wpdb->prefix . 'mycred_isp_withdrawals';
        $monthly = $this->get_monthly_data( $table );
        $stats   = $this->get_analytics( $table );

        wp_localize_script( 'umm-admin', 'UMM_Admin', [
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'clear_nonce'   => wp_create_nonce( 'umm_clear_declined' ),
            'chartLabels'   => $monthly['labels'],
            'chartValues'   => $monthly['values'],
            'airtimeCount'  => $stats['airtime_count'],
            'bankCount'     => $stats['bank_count'],
            'dataCount'     => $stats['data_count'],
            'i18n'          => [
                'confirmClear' => __( 'This will permanently delete all rejected withdrawal requests. This cannot be undone. Continue?', 'user-monetization-manager' ),
                'cleared'      => __( 'Declined history cleared.', 'user-monetization-manager' ),
                'error'        => __( 'An error occurred. Please try again.', 'user-monetization-manager' ),
                'airtime'      => __( 'Airtime', 'user-monetization-manager' ),
                'bank'         => __( 'Bank', 'user-monetization-manager' ),
                'data'         => __( 'Data', 'user-monetization-manager' ),
            ],
        ] );
    }

    /* ── Helpers ─────────────────────────────────────────────────── */
    private function get_defaults() {
        return [
            'post_points'               => 10,
            'post_label'                => 'Post',
            'comment_points'            => 5,
            'comment_label'             => 'Comment',
            'min_comment_chars'         => 0,
            'max_comments_per_hour'     => 0,
            'enable_strict_reply'       => 0,
            'excluded_words'            => '',
            'enable_referrals'          => 0,
            'referral_visit_points'     => 1,
            'referral_signup_points'    => 5,
            'min_withdrawal_threshold'  => 1000,
            'enable_airtime_withdrawal' => 1,
            'enable_bank_withdrawal'    => 1,
            'enable_data_withdrawal'    => 1,
            'manager_emails'            => get_option( 'admin_email', '' ),
        ];
    }

    private function get_options() {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), $this->get_defaults() );
    }

    public static function get_min_withdrawal_threshold() {
        $opts = wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'min_withdrawal_threshold'  => 1000,
            'enable_airtime_withdrawal' => 1,
            'enable_bank_withdrawal'    => 1,
            'enable_data_withdrawal'    => 1,
        ] );
        return (float) $opts['min_withdrawal_threshold'];
    }

    public static function get_withdrawal_options() {
        $opts = wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'enable_airtime_withdrawal' => 1,
            'enable_bank_withdrawal'    => 1,
            'enable_data_withdrawal'    => 1,
        ] );
        return [
            'airtime' => (bool) $opts['enable_airtime_withdrawal'],
            'bank'    => (bool) $opts['enable_bank_withdrawal'],
            'data'    => (bool) $opts['enable_data_withdrawal'],
        ];
    }

    public static function get_manager_emails() {
        $opts = wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'manager_emails' => get_option( 'admin_email', '' ),
        ] );
        $raw = $opts['manager_emails'];
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }
}
