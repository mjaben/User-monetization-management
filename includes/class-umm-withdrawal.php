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

        ob_start(); ?>
        <div class="mycred-isp-withdrawal-form">
            <form id="mycred-isp-form">
                <h3><?php _e('Redeem Your Points', 'user-monetization-manager'); ?></h3>
                <p><?php printf(__('Your Balance: <strong>%s</strong>', 'user-monetization-manager'), esc_html($balance)); ?></p>
                
                <p>
                    <label><?php _e('Withdrawal Method', 'user-monetization-manager'); ?></label>
                    <select name="withdrawal_method" id="umm-withdrawal-method" required>
                        <option value="isp"><?php _e('Airtime Top-up', 'user-monetization-manager'); ?></option>
                        <option value="bank"><?php _e('Direct Deposit', 'user-monetization-manager'); ?></option>
                    </select>
                </p>

                <div id="umm-method-isp" class="umm-method-group">
                    <p>
                        <label><?php _e('Phone Number', 'user-monetization-manager'); ?></label>
                        <input type="text" name="phone">
                    </p>
                    <p>
                        <label><?php _e('ISP', 'user-monetization-manager'); ?></label>
                        <select name="isp">
                            <option value="mtn">MTN</option>
                            <option value="airtel">Airtel</option>
                            <option value="glo">Glo</option>
                            <option value="9mobile">9mobile</option>
                        </select>
                    </p>
                </div>

                <div id="umm-method-bank" class="umm-method-group" style="display:none;">
                    <p>
                        <label><?php _e('Account Number', 'user-monetization-manager'); ?></label>
                        <input type="text" name="account_number">
                    </p>
                    <p>
                        <label><?php _e('Bank Name', 'user-monetization-manager'); ?></label>
                        <select name="bank_name">
                            <option value="opay">Opay</option>
                            <option value="palmpay">Palmpay</option>
                            <option value="kuda">Kuda</option>
                            <option value="moniepoint">Moniepoint</option>
                        </select>
                    </p>
                </div>

                <p>
                    <label><?php _e('Amount', 'user-monetization-manager'); ?></label>
                    <input type="number" name="amount" min="1" required>
                </p>
                <button type="submit"><?php _e('Redeem Points', 'user-monetization-manager'); ?></button>
                <div class="mycred-isp-message"></div>
            </form>
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

        if ( $amount <= 0 ) wp_send_json_error(['message' => __('Invalid amount', 'user-monetization-manager')]);
        if ( $amount > $balance ) wp_send_json_error(['message' => __('Not enough points to redeem', 'user-monetization-manager')]);

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

        wp_send_json_success(['message' => __('✅ Your withdrawal request has been submitted!', 'user-monetization-manager')]);
    }
}
