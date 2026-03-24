<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UMM_Withdrawal {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mycred_isp_withdrawals';

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Shortcode for form
        add_shortcode( 'umm_withdrawal', [ $this, 'withdrawal_form_shortcode' ] );
        add_shortcode( 'mycred_isp_withdrawal', [ $this, 'withdrawal_form_shortcode' ] ); // Backward compatibility

        // AJAX endpoints
        add_action( 'wp_ajax_mycred_isp_withdraw', [ $this, 'ajax_withdraw' ] );
        add_action( 'wp_ajax_nopriv_mycred_isp_withdraw', [ $this, 'ajax_login_required' ] );
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'umm-withdrawal', UMM_URL . 'assets/css/umm-withdrawal.css', [], UMM_VERSION );
        wp_enqueue_script( 'umm-withdrawal', UMM_URL . 'assets/js/umm-withdrawal.js', ['jquery'], UMM_VERSION, true );
        wp_localize_script( 'umm-withdrawal', 'MyCredISP', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mycred_isp_ajax' )
        ]);
    }

    public function withdrawal_form_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __('You must be logged in to withdraw.', 'user-monetization-manager') . '</p>';
        }

        $user_id = get_current_user_id();
        if ( function_exists('mycred_get_users_balance') ) {
            $balance = mycred_get_users_balance( $user_id );
        } else {
            $balance = 0;
        }

        // Check if user has a pending withdrawal request
        global $wpdb;
        $pending_request = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND status = 'pending' ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        // Get minimum withdrawal threshold
        $min_threshold = UMM_Admin::get_min_withdrawal_threshold();
        $threshold_met = $balance >= $min_threshold;
        $points_needed = $min_threshold - $balance;

        // Get which withdrawal methods are enabled
        $withdrawal_opts    = UMM_Admin::get_withdrawal_options();
        $airtime_enabled    = $withdrawal_opts['airtime'];
        $bank_enabled       = $withdrawal_opts['bank'];
        $any_method_enabled = $airtime_enabled || $bank_enabled;

        ob_start(); ?>
        <div class="mycred-isp-withdrawal-form">
            <!-- Balance Card with Gradient Background -->
            <div class="umm-balance-card">
                <div class="umm-balance-label">
                    <?php _e('Available Balance', 'user-monetization-manager'); ?>
                </div>
                <div class="umm-balance-amount">
                    <?php echo esc_html($balance); ?>
                </div>
                
                <?php if ( $min_threshold > 0 ) : ?>
                    <div class="umm-threshold-indicator">
                        <?php if ( $threshold_met ) : ?>
                            <span class="umm-threshold-met">
                                ✓ <?php printf(__('Ready to withdraw (Min: %d points)', 'user-monetization-manager'), $min_threshold); ?>
                            </span>
                        <?php else : ?>
                            <span class="umm-threshold-not-met">
                                <?php printf(__('%d more points needed (Min: %d)', 'user-monetization-manager'), $points_needed, $min_threshold); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( $pending_request ) : ?>
                <!-- Pending Request Status Card -->
                <div class="umm-form-card umm-pending-card">
                    <div class="umm-pending-icon">⏳</div>
                    <h3><?php _e('Withdrawal Request Pending', 'user-monetization-manager'); ?></h3>
                    
                    <div class="umm-pending-info">
                        <p class="umm-pending-message">
                            <?php _e('You have a withdrawal request currently being processed. Please wait for it to complete before submitting a new request.', 'user-monetization-manager'); ?>
                        </p>
                        
                        <div class="umm-pending-details">
                            <div class="umm-detail-row">
                                <span class="umm-detail-label"><?php _e('Amount:', 'user-monetization-manager'); ?></span>
                                <span class="umm-detail-value"><?php echo esc_html($pending_request->amount); ?> <?php _e('Points', 'user-monetization-manager'); ?></span>
                            </div>
                            <div class="umm-detail-row">
                                <span class="umm-detail-label"><?php _e('Method:', 'user-monetization-manager'); ?></span>
                                <span class="umm-detail-value">
                                    <?php 
                                    if ($pending_request->withdrawal_method === 'isp') {
                                        echo esc_html(strtoupper($pending_request->isp ?? 'N/A')) . ' ' . __('Airtime', 'user-monetization-manager');
                                    } else {
                                        echo esc_html(ucfirst($pending_request->bank_name ?? 'N/A')) . ' ' . __('Bank', 'user-monetization-manager');
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="umm-detail-row">
                                <span class="umm-detail-label"><?php _e('Submitted:', 'user-monetization-manager'); ?></span>
                                <span class="umm-detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($pending_request->created ?? 'now'))); ?></span>
                            </div>
                        </div>
                        
                        <div class="umm-pending-note">
                            <strong><?php _e('Note:', 'user-monetization-manager'); ?></strong>
                            <?php _e('Requests are typically processed within 24-48 hours. You will be able to make a new withdrawal once this request is completed or cancelled.', 'user-monetization-manager'); ?>
                        </div>
                    </div>
                </div>
            <?php elseif ( !$threshold_met && $min_threshold > 0 ) : ?>
                <!-- Threshold Not Met Card -->
                <div class="umm-form-card umm-threshold-card">
                    <div class="umm-threshold-icon-large">🎯</div>
                    <h3><?php _e('Minimum Threshold Not Met', 'user-monetization-manager'); ?></h3>
                    
                    <div class="umm-threshold-info">
                        <p class="umm-threshold-message">
                            <?php printf(__('You need at least %d points to make a withdrawal.', 'user-monetization-manager'), $min_threshold); ?>
                        </p>
                        
                        <div class="umm-threshold-progress-container">
                            <div class="umm-threshold-progress-label">
                                <span><?php _e('Your Progress:', 'user-monetization-manager'); ?></span>
                                <span><strong><?php echo esc_html($balance); ?></strong> / <?php echo esc_html($min_threshold); ?></span>
                            </div>
                            <div class="umm-progress-bar">
                                <div class="umm-progress-fill" style="width: <?php echo min(100, ($balance / $min_threshold) * 100); ?>%"></div>
                            </div>
                            <p class="umm-points-needed">
                                <?php printf(__('Just %d more points to go!', 'user-monetization-manager'), $points_needed); ?>
                            </p>
                        </div>
                        
                        <div class="umm-threshold-tips">
                            <strong><?php _e('How to Earn More Points:', 'user-monetization-manager'); ?></strong>
                            <ul>
                                <li>💬 <?php _e('Comment in the community', 'user-monetization-manager'); ?></li>
                                <li>📝 <?php _e('Create community posts', 'user-monetization-manager'); ?></li>
                                <li>🤝 <?php _e('Engage with other members', 'user-monetization-manager'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php elseif ( ! $any_method_enabled ) : ?>
                <!-- Withdrawals Disabled Notice -->
                <div class="umm-form-card umm-threshold-card">
                    <div class="umm-threshold-icon-large">🚫</div>
                    <h3><?php _e('Withdrawals Currently Unavailable', 'user-monetization-manager'); ?></h3>
                    <div class="umm-threshold-info">
                        <p class="umm-threshold-message">
                            <?php _e('Withdrawals are temporarily disabled. Please check back later.', 'user-monetization-manager'); ?>
                        </p>
                    </div>
                </div>
            <?php else : ?>
                <!-- Withdrawal Form Card -->
                <div class="umm-form-card">
                    <form id="mycred-isp-form">
                        <h3><?php _e('Redeem Your Points', 'user-monetization-manager'); ?></h3>

                        <!-- Withdrawal Method Selection -->
                        <div class="umm-form-group">
                            <label for="umm-withdrawal-method"><?php _e('Withdrawal Method', 'user-monetization-manager'); ?></label>
                            <select name="withdrawal_method" id="umm-withdrawal-method" required>
                                <?php if ( $airtime_enabled ) : ?>
                                    <option value="isp"><?php _e('Airtime Top-up', 'user-monetization-manager'); ?></option>
                                <?php endif; ?>
                                <?php if ( $bank_enabled ) : ?>
                                    <option value="bank"><?php _e('Direct Deposit', 'user-monetization-manager'); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <?php if ( $airtime_enabled ) : ?>
                        <!-- Airtime Top-up Method -->
                        <div id="umm-method-isp" class="umm-method-group" <?php echo ( ! $bank_enabled ) ? '' : ''; ?>>
                            <div class="umm-form-group">
                                <label for="umm-phone"><?php _e('Phone Number', 'user-monetization-manager'); ?></label>
                                <input type="text" id="umm-phone" name="phone" placeholder="e.g. 08012345678">
                            </div>
                            <div class="umm-form-group">
                                <label for="umm-isp"><?php _e('Network Provider', 'user-monetization-manager'); ?></label>
                                <select id="umm-isp" name="isp">
                                    <option value="mtn">MTN Nigeria</option>
                                    <option value="airtel">Airtel Nigeria</option>
                                    <option value="glo">Glo Mobile</option>
                                    <option value="9mobile">9mobile</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ( $bank_enabled ) : ?>
                        <!-- Direct Deposit Method -->
                        <div id="umm-method-bank" class="umm-method-group" <?php echo ( $airtime_enabled ) ? 'style="display:none;"' : ''; ?>>
                            <div class="umm-form-group">
                                <label for="umm-account-number"><?php _e('Account Number', 'user-monetization-manager'); ?></label>
                                <input type="text" id="umm-account-number" name="account_number" placeholder="e.g. 1234567890">
                            </div>
                            <div class="umm-form-group">
                                <label for="umm-bank-name"><?php _e('Bank Name', 'user-monetization-manager'); ?></label>
                                <select id="umm-bank-name" name="bank_name">
                                    <option value="opay">Opay</option>
                                    <option value="palmpay">Palmpay</option>
                                    <option value="kuda">Kuda Bank</option>
                                    <option value="moniepoint">Moniepoint</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Amount Input -->
                        <div class="umm-form-group">
                            <label for="umm-amount"><?php _e('Amount (Points)', 'user-monetization-manager'); ?></label>
                            <input type="number" id="umm-amount" name="amount" min="1" placeholder="Enter amount" required>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="umm-submit-btn">
                            <?php _e('Redeem Points', 'user-monetization-manager'); ?>
                        </button>

                        <!-- Message Container -->
                        <div class="mycred-isp-message"></div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // Handle AJAX
    public function ajax_login_required() {
        wp_send_json_error(['message' => __('You must be logged in.', 'user-monetization-manager')]);
    }

    public function ajax_withdraw() {
        check_ajax_referer( 'mycred_isp_ajax', 'security' );
        if ( ! is_user_logged_in() ) wp_send_json_error(['message' => __('You must be logged in.', 'user-monetization-manager')]);

        $user_id = get_current_user_id();
        $balance = function_exists('mycred_get_users_balance') ? mycred_get_users_balance( $user_id ) : 0;

        $method = sanitize_text_field( $_POST['withdrawal_method'] ?? 'isp' );
        $amount = floatval( $_POST['amount'] ?? 0 );

        // Check that the submitted method is currently enabled
        $withdrawal_opts = UMM_Admin::get_withdrawal_options();
        if ( $method === 'isp' && ! $withdrawal_opts['airtime'] ) {
            wp_send_json_error(['message' => __('Airtime Top-up withdrawals are currently disabled.', 'user-monetization-manager')]);
        }
        if ( $method === 'bank' && ! $withdrawal_opts['bank'] ) {
            wp_send_json_error(['message' => __('Direct Deposit withdrawals are currently disabled.', 'user-monetization-manager')]);
        }

        if ( $amount <= 0 ) wp_send_json_error(['message' => __('Invalid amount', 'user-monetization-manager')]);
        if ( $amount > $balance ) wp_send_json_error(['message' => __('Not enough points to redeem', 'user-monetization-manager')]);

        // Check minimum withdrawal threshold
        $min_threshold = UMM_Admin::get_min_withdrawal_threshold();
        if ( $min_threshold > 0 && $balance < $min_threshold ) {
            wp_send_json_error(['message' => sprintf(__('You need at least %d points to make a withdrawal.', 'user-monetization-manager'), $min_threshold)]);
        }

        $data = [
            'user_id' => $user_id,
            'amount'  => $amount,
            'status'  => 'pending',
            'withdrawal_method' => $method
        ];

        if ( $method === 'isp' ) {
            $phone = sanitize_text_field( $_POST['phone'] ?? '' );
            $isp   = sanitize_text_field( $_POST['isp'] ?? '' );
            if ( empty($phone) ) wp_send_json_error(['message' => __('Phone number is required.', 'user-monetization-manager')]);
            
            $data['phone'] = $phone;
            $data['isp']   = $isp;
        } elseif ( $method === 'bank' ) {
            $account_number = sanitize_text_field( $_POST['account_number'] ?? '' );
            $bank_name      = sanitize_text_field( $_POST['bank_name'] ?? '' );
            if ( empty($account_number) ) wp_send_json_error(['message' => __('Account number is required.', 'user-monetization-manager')]);

            $data['account_number'] = $account_number;
            $data['bank_name']      = $bank_name;
        } else {
            wp_send_json_error(['message' => __('Invalid withdrawal method.', 'user-monetization-manager')]);
        }

        global $wpdb;

        // 🔒 Check if user has a pending request
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        if ( $pending > 0 ) {
            wp_send_json_error(['message' => __('⚠️ You already have a pending request. Please wait for it to be processed.', 'user-monetization-manager')]);
        }

        // Insert new request
        $wpdb->insert( $this->table, $data );
        $insert_id = $wpdb->insert_id;

        // Notify managers
        $this->send_manager_alert( $data, $insert_id );

        wp_send_json_success(['message' => __('✅ Your withdrawal request has been submitted!', 'user-monetization-manager')]);
    }

    /* ── Manager email alert ─────────────────────────────────────── */
    private function send_manager_alert( $data, $request_id ) {
        $recipients = UMM_Admin::get_manager_emails();
        if ( empty( $recipients ) ) return;

        $user        = get_userdata( $data['user_id'] );
        $user_name   = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'User #' . $data['user_id'];
        $method      = $data['withdrawal_method'] === 'bank' ? __( 'Direct Deposit', 'user-monetization-manager' ) : __( 'Airtime Top-up', 'user-monetization-manager' );
        $details     = $data['withdrawal_method'] === 'bank'
            ? sprintf( __( 'Account: %s | Bank: %s', 'user-monetization-manager' ), $data['account_number'] ?? '', ucfirst( $data['bank_name'] ?? '' ) )
            : sprintf( __( 'Phone: %s | Network: %s', 'user-monetization-manager' ), $data['phone'] ?? '', strtoupper( $data['isp'] ?? '' ) );

        $admin_url = admin_url( 'admin.php?page=user-monetization-manager&tab=withdrawals' );
        $site_name = get_bloginfo( 'name' );

        $subject = sprintf( __( '[%s] New Withdrawal Request #%d', 'user-monetization-manager' ), $site_name, $request_id );

        $body  = "A new withdrawal request has been submitted.\n\n";
        $body .= "Request ID : #" . $request_id . "\n";
        $body .= "User       : " . $user_name . "\n";
        $body .= "Method     : " . $method . "\n";
        $body .= "Details    : " . $details . "\n";
        $body .= "Amount     : " . number_format( floatval( $data['amount'] ), 2 ) . " points\n\n";
        $body .= "Review the request here:\n" . $admin_url . "\n\n";
        $body .= "— " . $site_name;

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        foreach ( $recipients as $email ) {
            wp_mail( $email, $subject, $body, $headers );
        }
    }
}

