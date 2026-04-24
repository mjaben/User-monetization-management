<?php
if (!defined('ABSPATH'))
    exit;

class UMM_Referrals
{

    const COOKIE_NAME = 'umm_mref';
    const COOKIE_DAYS = 30;

    public function __construct()
    {
        add_action('init', [$this, 'track_visitor']);
        add_action('user_register', [$this, 'track_signup']);
        add_shortcode('umm_referral_link', [$this, 'shortcode_referral_link']);
        add_shortcode('umm_referral_dashboard', [$this, 'shortcode_referral_dashboard']);
        add_action('wp_footer', [$this, 'auto_append_referral_js'], 999);
        add_action('fluent_community/portal_footer', [$this, 'auto_append_referral_js'], 999);
    }

    public function auto_append_referral_js()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $options = get_option('fc_mycred_settings', []);
        if (empty($options['enable_referrals'])) {
            return;
        }

        $user = wp_get_current_user();
        $username = urlencode($user->user_login);

?>
        <script type="text/javascript">
            // UMM Referral Auto-Appender
            (function() {
                const umm_username = '<?php echo esc_js($username); ?>';
                if (!umm_username) return;

                // 1. Visually update the browser URL (so ANY button that grabs location.href will automatically have the parameter)
                try {
                    let currentUrl = new URL(window.location.href);
                    // Don't append if it's an admin page or already has mref
                    if (!currentUrl.pathname.includes('/wp-admin/') && !currentUrl.searchParams.has('mref')) {
                        currentUrl.searchParams.set('mref', umm_username);
                        window.history.replaceState({}, '', currentUrl.toString());
                    }
                } catch(e) {}

                function appendMref(text) {
                    if (!text || typeof text !== 'string') return text;
                    try {
                        let url;
                        if (text.startsWith('http')) {
                            url = new URL(text);
                        } else if (text.startsWith('/')) {
                            url = new URL(text, window.location.origin);
                        } else {
                            return text;
                        }

                        if (url.hostname === window.location.hostname && !url.searchParams.has('mref')) {
                            url.searchParams.set('mref', umm_username);
                            if (!text.startsWith('http')) {
                                return url.pathname + url.search + url.hash;
                            }
                            return url.toString();
                        }
                    } catch(e) {}
                    return text;
                }

                // 2. Intercept modern Clipboard API
                if (navigator.clipboard) {
                    const originalWriteText = navigator.clipboard.writeText;
                    if (originalWriteText) {
                        navigator.clipboard.writeText = function(text) {
                            text = appendMref(text);
                            return originalWriteText.apply(this, arguments);
                        };
                    }
                }

                // 3. Intercept legacy copy events
                document.addEventListener('copy', function(e) {
                    let text = '';
                    if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) {
                        text = document.activeElement.value.substring(document.activeElement.selectionStart, document.activeElement.selectionEnd);
                    } else {
                        text = window.getSelection().toString();
                    }

                    if (text) {
                        let newText = appendMref(text);
                        if (newText !== text) {
                            if (e.clipboardData) {
                                e.clipboardData.setData('text/plain', newText);
                                e.preventDefault();
                            }
                        }
                    }
                });

                // 4. Overzealous Mutation Observer to strictly inject ?mref into actual Share input fields (Fluent Community specific)
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) { // ELEMENT_NODE
                                    // Search for inputs inside modal or general DOM that hold the post URL
                                    let inputs = node.querySelectorAll ? node.querySelectorAll('input[type="text"]') : [];
                                    if (node.tagName === 'INPUT' && node.type === 'text') inputs = [node];
                                    
                                    inputs.forEach(function(input) {
                                        if (input.value && input.value.includes(window.location.hostname) && !input.value.includes('mref=')) {
                                            input.value = appendMref(input.value);
                                        }
                                    });
                                }
                            });
                        }
                    });
                });
                observer.observe(document.body, { childList: true, subtree: true });
            })();
        </script>
        <?php
    }

    public function track_visitor()
    {
        $options = get_option('fc_mycred_settings', []);
        $enabled = !empty($options['enable_referrals']);
        if (!$enabled)
            return;

        // Check if referral parameter is present
        if (isset($_GET['mref']) && !empty($_GET['mref'])) {
            $ref_id = sanitize_text_field(wp_unslash($_GET['mref']));

            // Set cookie for signup tracking
            if (!isset($_COOKIE[self::COOKIE_NAME])) {
                setcookie(self::COOKIE_NAME, $ref_id, time() + (DAY_IN_SECONDS * self::COOKIE_DAYS), COOKIEPATH, COOKIE_DOMAIN);
                $_COOKIE[self::COOKIE_NAME] = $ref_id; // Make it available immediately
            }

            // Check if visitor points are configured
            $points = !empty($options['referral_visit_points']) ? floatval($options['referral_visit_points']) : 0;
            if ($points > 0 && function_exists('mycred_add')) {
                $user = get_user_by('login', $ref_id);
                if ($user && $user->ID) {
                    $referrer_id = $user->ID;

                    // Don't award if referring oneself
                    if (get_current_user_id() == $referrer_id)
                        return;

                    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');

                    // Extract the clean URL path to ensure tracking is per-post/per-page
                    $current_path = sanitize_text_field(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

                    // Use WordPress transients for strict 30-day IP rate limiting per referrer PER PAGE
                    $ip_hash = md5($ip . '_' . $referrer_id . '_' . $current_path);
                    $transient_name = 'umm_ref_ip_' . $ip_hash;

                    if (get_transient($transient_name)) {
                        return; // Already awarded points for this IP + Referrer + Specific Page
                    }

                    // Set the lock for 30 days so the same user/visitor cannot earn multiple times from the same post by the same viewer
                    set_transient($transient_name, 1, DAY_IN_SECONDS * 30);

                    mycred_add(
                        'visitor_referral',
                        $referrer_id,
                        $points,
                        __('Points for referring a visitor', 'user-monetization-manager'),
                        time(),
                        $ip,
                        'mycred_default'
                    );
                }
            }
        }
    }

    public function track_signup($user_id)
    {
        $options = get_option('fc_mycred_settings', []);
        $enabled = !empty($options['enable_referrals']);
        if (!$enabled)
            return;

        if (isset($_COOKIE[self::COOKIE_NAME]) && !empty($_COOKIE[self::COOKIE_NAME])) {
            $ref_id = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
            $points = !empty($options['referral_signup_points']) ? floatval($options['referral_signup_points']) : 0;

            if ($points > 0 && function_exists('mycred_add')) {
                $user = get_user_by('login', $ref_id);
                if ($user && $user->ID) {
                    $referrer_id = $user->ID;

                    // Award points to referrer
                    mycred_add(
                        'signup_referral',
                        $referrer_id,
                        $points,
                        __('Points for referring a new member', 'user-monetization-manager'),
                        $user_id,
                        '',
                        'mycred_default'
                    );

                    // Deleting the cookie so it doesn't trigger multiple times
                    setcookie(self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
                }
            }
        }
    }

    public function shortcode_referral_link($atts)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $ref_id = urlencode($user->user_login);
        $link = add_query_arg('mref', $ref_id, home_url('/'));

        ob_start();
?>
        <div class="umm-referral-widget" style="display: flex; align-items: center; max-width: 100%; border-radius: 6px; padding: 4px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif;">
            <input type="text" readonly value="<?php echo esc_attr($link); ?>" id="umm-ref-url-<?php echo get_current_user_id(); ?>" style="flex-grow: 1; border: none; background: transparent; padding: 8px 12px; font-size: 14px; color: #334155; width: 100%; outline: none; box-shadow: none;" onclick="this.select()" />
            <button type="button" class="umm-copy-btn" onclick="let input = document.getElementById('umm-ref-url-<?php echo get_current_user_id(); ?>'); input.select(); document.execCommand('copy'); let oldText = this.innerText; this.innerText='<?php esc_attr_e('Copied!', 'user-monetization-manager'); ?>'; this.style.backgroundColor='#10b981'; this.style.color='#fff'; setTimeout(() => { this.innerText=oldText; this.style.backgroundColor='#e2e8f0'; this.style.color='#334155'; }, 2000);" style="background-color: #e2e8f0; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; color: #334155; font-size: 14px; font-weight: 500; transition: all 0.2s ease;">
                <?php esc_html_e('Copy Link', 'user-monetization-manager'); ?>
            </button>
        </div>
        <style>
            .umm-referral-widget:focus-within { border-color: #94a3b8; box-shadow: 0 0 0 1px #94a3b8; }
            .umm-copy-btn:hover { background-color: #cbd5e1 !important; }
        </style>
        <?php
        return ob_get_clean();
    }

    public function shortcode_referral_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your referral dashboard.', 'user-monetization-manager') . '</p>';
        }

        global $wpdb;
        $user_id = get_current_user_id();
        
        // Ensure mycred is installed
        if (!function_exists('mycred')) {
            return '';
        }

        $mycred = mycred();
        $log_table = $mycred->log_table;

        // Calculate Totals Using SQL
        $total_points = $wpdb->get_var($wpdb->prepare("SELECT SUM(creds) FROM {$log_table} WHERE user_id = %d AND ref IN ('visitor_referral', 'signup_referral')", $user_id));
        $total_points = $total_points ? $mycred->format_creds($total_points) : $mycred->format_creds(0);

        $total_referrals = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$log_table} WHERE user_id = %d AND ref = 'signup_referral'", $user_id));
        $total_referrals = $total_referrals ? intval($total_referrals) : 0;

        // Get Signup Records
        $signups = $wpdb->get_results($wpdb->prepare("SELECT time, ref_id FROM {$log_table} WHERE user_id = %d AND ref = 'signup_referral' ORDER BY time DESC", $user_id));

        ob_start();
        ?>
        <div class="umm-referral-dashboard-wrapper">
            <style>
                .umm-referral-dashboard-wrapper {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: var(--fc-bg-base, #ffffff);
                    border: 1px solid var(--fc-border-color, #e2e8f0);
                    border-radius: 12px;
                    padding: 24px;
                    color: var(--fc-text-color, #1e293b);
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                }
                .umm-cards-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .umm-stat-card {
                    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                    color: #fff;
                    padding: 20px;
                    border-radius: 10px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                    position: relative;
                    overflow: hidden;
                }
                .umm-stat-card::after {
                    content: '';
                    position: absolute;
                    top: -50%; right: -50%;
                    width: 200%; height: 200%;
                    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 60%);
                    opacity: 0.5;
                    pointer-events: none;
                }
                .umm-stat-card-title {
                    font-size: 0.875rem;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #94a3b8;
                    margin-bottom: 8px;
                }
                .umm-stat-card-value {
                    font-size: 2rem;
                    font-weight: 700;
                    line-height: 1.2;
                }
                .umm-referrals-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    background: var(--fc-bg-base, #ffffff);
                    border: 1px solid var(--fc-border-color, #e2e8f0);
                    border-radius: 8px;
                    overflow: hidden;
                }
                .umm-referrals-table th, .umm-referrals-table td {
                    padding: 14px 16px;
                    text-align: left;
                    border-bottom: 1px solid var(--fc-border-color, #e2e8f0);
                }
                .umm-referrals-table th {
                    background: var(--fc-bg-muted, #f8fafc);
                    font-weight: 600;
                    font-size: 0.875rem;
                    color: var(--fc-text-muted, #64748b);
                    text-transform: uppercase;
                }
                .umm-referrals-table tr:last-child td {
                    border-bottom: none;
                }
                .umm-referrals-table tr:hover {
                    background: var(--fc-bg-muted, #f1f5f9);
                }
                .umm-profile-link {
                    display: inline-block;
                    background: #3b82f6;
                    color: #ffffff !important;
                    text-decoration: none;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 0.875rem;
                    font-weight: 500;
                    transition: background 0.2s;
                }
                .umm-profile-link:hover {
                    background: #2563eb;
                }
                .umm-empty-state {
                    padding: 40px;
                    text-align: center;
                    color: var(--fc-text-muted, #64748b);
                    background: var(--fc-bg-muted, #f8fafc);
                    border-radius: 8px;
                    border: 1px solid var(--fc-border-color, #e2e8f0);
                }
                .umm-user-badge {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .umm-user-avatar img {
                    border-radius: 50%;
                    width: 32px;
                    height: 32px;
                }
                .umm-user-name {
                    font-weight: 500;
                }
            </style>

            <div class="umm-cards-container">
                <div class="umm-stat-card">
                    <div class="umm-stat-card-title"><?php esc_html_e('Total Referrals', 'user-monetization-manager'); ?></div>
                    <div class="umm-stat-card-value"><?php echo esc_html($total_referrals); ?></div>
                </div>
                <div class="umm-stat-card">
                    <div class="umm-stat-card-title"><?php esc_html_e('Total Points Earned', 'user-monetization-manager'); ?></div>
                    <div class="umm-stat-card-value"><?php echo esc_html($total_points); ?></div>
                </div>
            </div>

            <h3 style="margin-bottom: 15px; font-size: 1.25rem; font-weight: 600;"><?php esc_html_e('Recent Signups', 'user-monetization-manager'); ?></h3>
            
            <?php if (!empty($signups)) : ?>
                <div style="overflow-x: auto;">
                    <table class="umm-referrals-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php esc_html_e('User', 'user-monetization-manager'); ?></th>
                                <th><?php esc_html_e('Date Joined', 'user-monetization-manager'); ?></th>
                                <th><?php esc_html_e('Action', 'user-monetization-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 1;
                            foreach ($signups as $signup) : 
                                $referred_user = get_userdata($signup->ref_id);
                                if (!$referred_user) continue;
                                
                                // Best-guess fluent community profile URL: /social/user/{user_nicename} OR /user/{user_nicename}
                                // We'll link to standard WP author URL which is safely interceptable, or build a fluent-friendly path
                                $profile_url = home_url('/social/user/' . $referred_user->user_nicename);

                                // Let plugins natively filter the user profile link if buddyboss/fluent exist
                                $profile_url = apply_filters('umm_referral_profile_url', $profile_url, $referred_user->ID);
                            ?>
                                <tr>
                                    <td><?php echo esc_html($count++); ?></td>
                                    <td>
                                        <div class="umm-user-badge">
                                            <div class="umm-user-avatar"><?php echo get_avatar($referred_user->ID, 32); ?></div>
                                            <div class="umm-user-name"><?php echo esc_html($referred_user->display_name ?: $referred_user->user_login); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), $signup->time)); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($profile_url); ?>" class="umm-profile-link" target="_blank">
                                            <?php esc_html_e('View Profile', 'user-monetization-manager'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="umm-empty-state">
                    <p><?php esc_html_e('You haven\'t referred any users yet. Share your links to earn points!', 'user-monetization-manager'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
