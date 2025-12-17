<?php
/**
 * Template Name: Subscription Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get configurable values
$monthly_original = get_option('wcl_monthly_original_price', '$2');
$monthly_discounted = get_option('wcl_monthly_discounted_price', '50¢'); // Kept for reference but strictly using new design text
$monthly_desc = get_option('wcl_monthly_description', 'every week for first 3 months,<br>then $12 per month');

// Calculate yearly savings
$yearly_original_str = get_option('wcl_yearly_original_price', '');
$yearly_original_val = 0;
if (!empty($yearly_original_str)) {
    // Remove non-numeric characters except dot
    $yearly_original_val = floatval(preg_replace('/[^0-9.]/', '', $yearly_original_str));
}

// Get prices from options or Stripe
$stripe = WCL_Stripe::get_instance();
$monthly_price_id = $stripe->get_monthly_price_id();
$yearly_price_id = $stripe->get_yearly_price_id();

$monthly_price_val = get_option('wcl_monthly_price', '9.99');
$yearly_price_val = get_option('wcl_yearly_price', '99.99');

// Logic to calculate savings
$monthly_total = floatval($monthly_price_val) * 12;
$yearly_total = floatval($yearly_price_val);
$base_price = ($yearly_original_val > 0) ? $yearly_original_val : $monthly_total;
$savings_percent = $base_price > 0 ? round((($base_price - $yearly_total) / $base_price) * 100) : 0;

if (empty($yearly_original_str) && $monthly_total > 0) {
    $yearly_original_str = '$' . number_format($monthly_total, 2);
}

// Format prices for display
$monthly_price_display = '$' . $monthly_price_val;
$yearly_price_display = '$' . $yearly_price_val;

// Check user status
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$user_email = $is_logged_in ? $current_user->user_email : '';

// Check for post_id in URL
$post_id_from_url = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

// Data for JS
$wcl_data = array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wcl_checkout_nonce'),
    'postId' => $post_id_from_url,
    'isLoggedIn' => $is_logged_in,
    'isTestMode' => (isset($_GET['wcl_test_mode']) && ($_GET['wcl_test_mode'] === '1' || $_GET['wcl_test_mode'] === 'wcl_test_secret')),
    'strings' => array(
        'invalidEmail' => __('Please enter a valid email address.', 'wp-content-locker'),
        'error' => __('An error occurred. Please try again.', 'wp-content-locker'),
    )
);

// Check URL params for test mode
$is_test_mode = false;
if (isset($_GET['wcl_test_mode']) && ($_GET['wcl_test_mode'] == '1' && current_user_can('manage_options') || $_GET['wcl_test_mode'] === 'wcl_test_secret')) {
    $is_test_mode = true;
    $wcl_data['isTestMode'] = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - <?php echo get_bloginfo('name'); ?></title>
    <!-- Debug: WCL 1.2.0 New Design -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@300;400;500;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400&display=swap" rel="stylesheet">
    
    <!-- Use jQuery from WP if possible, or CDN fall back if we are standalone -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --theme-black: #000;
            --theme-dark: #333;
            --theme-gray: #555;
            --theme-border: #ccc;
            --font-headline: 'Playfair Display', Georgia, serif;
            --font-ui: 'Libre Franklin', Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-ui);
            color: var(--theme-black);
            background-color: #fff;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Header --- */
        header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 40px;
            border-bottom: 1px solid #e2e2e2;
            position: relative;
        }

        .logo {
            font-family: var(--font-headline);
            font-size: 32px;
            font-weight: 900;
            color: var(--theme-black);
            text-transform: uppercase;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .sign-in {
            position: absolute;
            right: 40px;
            font-size: 13px;
            font-weight: 700;
            color: #0274b6;
            text-decoration: none;
        }
        .sign-in:hover { text-decoration: underline; }

        /* --- Hero 区域 --- */
        .hero {
            text-align: center;
            padding: 30px 20px 0;
            max-width: 900px;
            margin: 0 auto;
        }

        /* Limited Time Offer */
        .offer-label {
            font-family: var(--font-ui);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #d93900;
            margin-bottom: 10px;
        }

        /* 大标题 */
        .hero h1 {
            font-family: var(--font-headline);
            font-size: 36px;
            font-weight: 700;
            margin: 0 0 25px 0;
            color: var(--theme-black);
            line-height: 1.2;
        }

        /* 分割线 */
        .separator {
            width: 100%;
            max-width: 800px;
            height: 1px;
            background-color: #e2e2e2;
            margin: 0 auto 30px;
        }

        /* Choose your Subscription - 不大写，不加粗 */
        .sub-headline {
            font-family: var(--font-ui);
            font-size: 20px;
            color: var(--theme-black);
            font-weight: 400;
            margin-bottom: 25px;
            text-transform: none; /* 强制取消大写 */
            letter-spacing: 0;
        }

        /* --- 订阅卡片区 --- */
        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 20px 80px;
        }

        .pricing-grid {
            display: flex;
            justify-content: center;
            gap: 25px;
            align-items: stretch;
        }

        .card {
            flex: 1;
            max-width: 400px;
            border: 1px solid var(--theme-border);
            border-radius: 12px;
            padding: 35px 30px;
            background: #fff;
            display: flex;
            flex-direction: column;
            text-align: center;
            transition: all 0.2s ease;
            position: relative; /* For loader positioning */
        }

        .card:hover {
            border-color: var(--theme-black);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .plan-title {
            font-family: var(--font-ui);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            margin-bottom: 10px;
        }

        .plan-name {
            font-family: var(--font-headline);
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 20px 0;
            line-height: 1.1;
        }

        /* 价格区域 */
        .price-wrapper {
            margin-bottom: 20px;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .old-price {
            font-family: var(--font-ui);
            font-size: 26px;
            text-decoration: line-through;
            color: #888;
            margin-bottom: 5px;
            font-weight: 400;
            line-height: 1.2;
        }

        .current-price {
            font-family: var(--font-ui);
            font-size: 26px;
            font-weight: 700;
            color: var(--theme-black);
            margin: 5px 0;
            line-height: 1.2;
            white-space: nowrap;
        }

        .billing-text {
            font-family: var(--font-ui);
            font-size: 14px;
            color: #000; 
            font-weight: 400;
            margin-top: 5px;
        }

        /* 上标样式调整，防止撑开行高 */
        sup {
            font-size: 0.6em;
            vertical-align: top;
            position: relative;
            top: -0.2em;
        }

        /* 按钮 */
        .btn-subscribe {
            display: block;
            width: 100%;
            padding: 14px 0;
            background-color: var(--theme-black);
            color: #fff;
            text-decoration: none;
            font-family: var(--font-ui);
            font-weight: 700;
            font-size: 15px;
            border-radius: 30px;
            margin-bottom: 10px;
            transition: background 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-subscribe:hover {
            background-color: #333;
        }
        
        .btn-subscribe:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .cancel-text {
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            font-weight: 500;
        }

        /* 功能列表 - 不大写 */
        .features-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: none; /* 确保不大写 */
            color: var(--theme-black);
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
        }

        .features-list li {
            font-size: 14px;
            margin-bottom: 10px;
            padding-left: 0;
            line-height: 1.4;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        /* 对号图标 - 再次放大至 32px */
        .check-icon {
            flex-shrink: 0;
            width: 32px;   /* 更大 */
            height: 32px;  /* 更大 */
            margin-left: 10px;
            fill: none;
            stroke: var(--theme-black);
            stroke-width: 1.2; /* 稍微细一点，显精致 */
            margin-top: -6px; /* 微调垂直对齐，因为图标变大了 */
        }
        
        /* Email Input Integration */
        .email-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--theme-border);
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        
        .error-message {
            color: #d93900;
            font-size: 13px;
            margin-top: 10px;
            display: none;
        }

        @media (max-width: 768px) {
            header { padding: 15px 20px; }
            .sign-in { display: none; }
            .pricing-grid { flex-direction: column; align-items: center; gap: 30px; }
            .card { width: 100%; max-width: 420px; padding: 30px 20px; }
            .hero h1 { font-size: 28px; }
            .logo { font-size: 24px; }
            .current-price { white-space: normal; line-height: 1.3; }
        }
    </style>
</head>
<body>

    <header>
        <a href="<?php echo home_url(); ?>" class="logo"><?php echo get_bloginfo('name'); ?></a>
        <?php if (!$is_logged_in): ?>
        <a href="<?php echo esc_url(wp_login_url()); ?>" class="sign-in">Sign In</a>
        <?php else: ?>
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sign-in">Sign Out</a>
        <?php endif; ?>
    </header>

    <div class="hero">
        <div class="offer-label">Limited Time Offer</div>
        <h1>✨ Only $1/week for the first 3 months</h1>
        
        <div class="separator"></div>

        <div class="sub-headline">Choose your Subscription</div>
    </div>

    <div class="container">
        <div class="pricing-grid">
            
            <!-- Monthly Card -->
            <div class="card" data-plan="monthly">
                <div class="plan-title">Monthly Access</div>
                <div class="plan-name">Monthly Subscription</div>
                
                <div class="price-wrapper">
                    <div class="old-price">$12/month</div>
                    <div class="current-price">$4/month for 3 months</div>
                    <div class="billing-text">$12/month from the 4<sup>th</sup> month</div>
                </div>

                <?php if (!$is_logged_in): ?>
                <input type="email" class="email-input subscription-email" placeholder="Enter your email" required />
                <?php endif; ?>

                <button class="btn-subscribe" data-plan="monthly">Subscribe Now</button>
                <div class="error-message"></div>
                
                <div class="cancel-text">You can cancel anytime.</div>

                <div class="features">
                    <div class="features-title">What you'll enjoy:</div>
                    <ul class="features-list">
                        <li>Unlimited access to ArizonaInsiders.com <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                        <li>Independent, agenda-free reporting <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                        <li>24/7 news updates <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                        <li>Weekly Insider newsletter <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                    </ul>
                </div>
            </div>

            <!-- Yearly Card -->
            <div class="card" data-plan="yearly">
                <div class="plan-title">Best Value</div>
                <div class="plan-name">Yearly Subscription</div>

                <div class="price-wrapper">
                    <?php if (!empty($yearly_original_str)) : ?>
                    <div class="old-price"><?php echo $yearly_original_str; ?>/year</div>
                    <?php endif; ?>
                    <div class="current-price"><?php echo $yearly_price_display; ?>/year for 1 year</div>
                    <div class="billing-text">$159/year from the 2<sup>nd</sup> year</div>
                </div>

                <?php if (!$is_logged_in): ?>
                <input type="email" class="email-input subscription-email" placeholder="Enter your email" required />
                <?php endif; ?>

                <button class="btn-subscribe" data-plan="yearly">Subscribe Now</button>
                <div class="error-message"></div>
                
                <div class="cancel-text">You can cancel anytime.</div>

                <div class="features">
                    <div class="features-title">What you'll enjoy:</div>
                    <ul class="features-list">
                        <li>Unlimited access to ArizonaInsiders.com <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                        <li>Independent, agenda-free reporting <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                        <li>24/7 news updates <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                        <li>Weekly Insider newsletter <svg class="check-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <footer style="text-align:center; padding: 40px 20px; font-size: 12px; color: #888; border-top: 1px solid #eee;">
        <p style="margin-bottom: 10px;">
            <a href="https://arizonainsiders.com/data-protection-privacy-policy/" style="color:#666; text-decoration:none; margin: 0 10px;">Privacy Policy</a>
            <a href="https://arizonainsiders.com/terms-of-service/" style="color:#666; text-decoration:none; margin: 0 10px;">Terms of Service</a>
        </p>
        <p>&copy; <?php echo date('Y'); ?> Arizona Insiders. All Rights Reserved.</p>
    </footer>

    <script type="text/javascript">
    var wclData = <?php echo json_encode($wcl_data); ?>;

    jQuery(document).ready(function($) {
        
        $('.btn-subscribe').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var plan = $btn.data('plan');
            var $card = $btn.closest('.card');
            var $error = $card.find('.error-message');
            var email = '';

            // Get email if not logged in
            if (!wclData.isLoggedIn) {
                // Get the email from the input inside *this* card
                email = $card.find('.subscription-email').val();
                
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    $error.text(wclData.strings.invalidEmail).show();
                    return;
                }
            }

            $btn.prop('disabled', true).text('Processing...');
            $error.hide();

            $.ajax({
                url: wclData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcl_create_checkout',
                    nonce: wclData.nonce,
                    plan_type: plan,
                    post_id: wclData.postId,
                    email: email,
                    test_mode: wclData.isTestMode ? 1 : 0
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : wclData.strings.error;
                        $error.text(msg).show();
                        $btn.prop('disabled', false).text('Subscribe Now');
                    }
                },
                error: function(xhr, status, error) {
                    $error.text(wclData.strings.error).show();
                    $btn.prop('disabled', false).text('Subscribe Now');
                }
            });
        });
    });
    </script>

</body>
</html>
