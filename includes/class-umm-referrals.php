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
            $points = !empty($options['referral_visit_points']) ? intval($options['referral_visit_points']) : 0;
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
            $points = !empty($options['referral_signup_points']) ? intval($options['referral_signup_points']) : 0;

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
}
