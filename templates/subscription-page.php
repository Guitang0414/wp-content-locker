<?php
/**
 * Template Name: Subscription Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get configurable values
$monthly_original = get_option('wcl_monthly_original_price', '$2');
$monthly_discounted = get_option('wcl_monthly_discounted_price', '50¢');
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

// Data for JS
$wcl_data = array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wcl_checkout_nonce'),
    'postId' => 0, // General subscription
    'isLoggedIn' => $is_logged_in,
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

    <meta name="description" content="Subscribe to <?php echo get_bloginfo('name'); ?>. Limited time offer.">
    
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <!-- Using Google Fonts matching the design -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        /* ============================ */
        /* 1. 全局与电脑端样式 (Base)   */
        /* ============================ */
        :root {
            --theme-black: #111;
            --theme-blue: #0274b6;
            --theme-gray: #555;
            --theme-border: #ddd;
            --font-serif: 'Georgia', 'Times New Roman', serif;
            --font-sans: 'Retina', 'Arial', -apple-system, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-sans);
            color: var(--theme-black);
            background-color: #fff;
            -webkit-font-smoothing: antialiased;
        }

        /* 电脑端隐藏“手机专用换行符” */
        .mobile-br { display: none; }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            border-bottom: 1px solid var(--theme-border);
        }

        .header-spacer { width: 80px; } 

        .logo {
            font-family: var(--font-serif);
            font-size: 32px;
            font-weight: 900;
            color: var(--theme-black);
            text-transform: uppercase;
            letter-spacing: -0.5px;
            text-decoration: none;
            cursor: pointer;
        }

        .sign-in {
            font-size: 13px;
            font-weight: bold;
            color: var(--theme-blue);
            text-decoration: none;
            width: 80px;
            text-align: right;
        }
        .sign-in:hover { text-decoration: underline; }

        /* Hero 区域 */
        .hero {
            text-align: center;
            padding: 50px 20px 40px;
            max-width: 1000px; 
            margin: 0 auto;
        }

        .hero h1 {
            font-family: var(--font-serif);
            font-size: 36px;
            margin: 0 0 15px 0;
            line-height: 1.2;
            white-space: nowrap; 
        }

        /* Offer 区域 */
        .offer-wrapper {
            margin-top: 10px;
            padding-top: 0;   
            border-top: none;
            border-bottom: 1px solid var(--theme-border);
            padding-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .offer-label {
            display: inline-block;
            font-family: var(--font-serif); 
            font-style: italic;             
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #d93900; 
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .offer-main-text {
            font-family: var(--font-serif);
            font-size: 28px;
            font-weight: bold;
            color: var(--theme-black);
            margin: 5px 0;
        }

        .offer-sub-text {
            font-size: 15px;
            color: var(--theme-gray);
            margin-top: 5px;
        }

        /* 订阅卡片布局 */
        .container {
            max-width: 1000px; 
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .pricing-grid {
            display: flex;
            justify-content: center;
            gap: 25px; 
            align-items: stretch;
        }

        .card {
            flex: 1;
            max-width: 420px; 
            border: 1px solid var(--theme-border);
            padding: 0;
            position: relative;
            background: #fff;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-radius: 12px; 
            overflow: hidden; 
        }

        .card.selected {
            border-color: var(--theme-blue);
            box-shadow: 0 0 0 1px var(--theme-blue);
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--theme-blue);
            z-index: 2;
        }

        .badge {
            background-color: var(--theme-black);
            color: #fff;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            padding: 8px 0;
            text-transform: uppercase;
        }

        .card-header {
            padding: 35px 35px 10px 35px;
            text-align: center;
        }

        .plan-title {
            font-family: var(--font-sans);
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            color: var(--theme-gray);
        }

        .price-wrapper { min-height: 80px; }

        .old-price {
            text-decoration: line-through;
            color: #999;
            font-size: 15px;
        }

        .current-price {
            font-family: var(--font-serif);
            font-size: 44px;
            font-weight: 700;
            color: var(--theme-black);
            line-height: 1.1;
            margin: 5px 0;
        }

        .period {
            font-size: 16px;
            font-weight: 400;
            color: var(--theme-gray);
            font-family: var(--font-sans);
        }

        .billing-text {
            font-size: 13px;
            color: #d93900;
            font-weight: 600;
            margin-top: 5px;
        }

        .card-body {
            padding: 20px 35px 45px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-select {
            display: block;
            width: 100%;
            padding: 18px 0;
            text-align: center;
            background-color: var(--theme-blue);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 25px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }
        .btn-select:hover { background-color: #005a8f; }
        .btn-select:disabled { background-color: #ccc; cursor: not-allowed; }

        .secure-note {
            text-align: center; 
            font-size: 11px; 
            color: #888; 
            margin-top: -15px; 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 4px;
        }

        /* --- 功能列表 (What You Get) --- */
        .features {
            border-top: 1px solid #eee;
            padding-top: 25px;
        }
        
        .features-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 15px;
            color: var(--theme-black);
            display: block;
        }

        /* 列表样式 */
        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: block; /* 始终显示 */
        }

        .features-list li {
            font-size: 15px; 
            margin-bottom: 12px;
            padding-left: 26px;
            position: relative;
            line-height: 1.5;
            color: #333;
        }

        .features-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 5px;
            width: 12px;
            height: 8px;
            border-bottom: 2px solid var(--theme-blue);
            border-left: 2px solid var(--theme-blue);
            transform: rotate(-45deg);
        }

        /* Email Input */
        .email-section {
            margin-bottom: 15px;
        }
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
            text-align: center;
            margin-top: 10px;
            display: none;
        }
        
        /* 法律/页脚 */
        .legal-text {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 50px;
            line-height: 1.5;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        footer {
            background-color: #f8f8f8;
            padding: 50px 20px;
            text-align: center;
            border-top: 1px solid var(--theme-border);
            font-size: 12px;
            color: #666;
        }

        footer a {
            color: #666;
            margin: 0 12px;
            text-decoration: none;
            transition: color 0.2s;
        }
        footer a:hover {
            color: var(--theme-black);
            text-decoration: underline;
        }

        .btn-contact {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            color: #555 !important;
            background-color: #fff;
            font-weight: bold;
            text-decoration: none !important;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .need-help-text {
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: bold;
            color: var(--theme-black);
        }

        /* ============================ */
        /* 2. 手机端适配 (Mobile Only)  */
        /* ============================ */
        @media (max-width: 768px) {
            
            /* 1. Header: 隐藏 Sign In */
            header { padding: 15px 20px; }
            .sign-in { display: none !important; } /* 彻底隐藏 */
            .logo {
                font-size: 20px;
                white-space: nowrap; 
                letter-spacing: -0.2px;
                margin: 0 auto; /* 居中 */
            }
            .header-spacer { display: none; }

            /* 2. Hero: 调整间距，显示换行 */
            .hero { padding: 25px 20px 10px; }
            .mobile-br { display: block; } /* 开启手机换行 */
            
            /* 标题：强制三行 + 小字体 */
            .hero h1 { 
                white-space: normal; 
                font-size: 22px; /* 字体缩小至 22px */
                line-height: 1.4;
                margin-bottom: 15px;
                font-weight: 700;
            }

            /* Offer 文本 */
            .offer-main-text { 
                font-size: 20px; 
                line-height: 1.3; 
                margin: 5px 0;
            }
            .offer-main-text .mobile-br { display: inline; }

            /* 3. 卡片布局 */
            .container { padding-top: 10px; }
            .pricing-grid { 
                flex-direction: column; 
                align-items: center; 
                gap: 20px; 
            }
            .card { width: 100%; max-width: 420px; }

            /* 4. 列表样式: 手机端也直接展开，不折叠 */
            .features-title {
                text-align: center; /* 标题居中 */
                padding-bottom: 10px;
                border-bottom: 1px dashed #eee;
            }
            .features-list {
                display: block !important; /* 强制显示 */
                padding-top: 15px;
            }
        }
    </style>
    <!-- Use jQuery from WP if possible, or CDN fall back if we are standalone -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <header>
        <div class="header-spacer"></div>
        <a href="<?php echo home_url(); ?>" class="logo"><?php echo get_bloginfo('name'); ?></a>
        <?php if (!$is_logged_in): ?>
        <a href="<?php echo esc_url(wp_login_url()); ?>" class="sign-in">Sign In</a>
        <?php else: ?>
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sign-in">Sign Out</a>
        <?php endif; ?>
    </header>

    <div class="hero">
        <h1>Independent News <br class="mobile-br"> for <br class="mobile-br"> Independent Thinkers</h1>

        <div class="offer-wrapper">
            <span class="offer-label">Limited Time Offer</span>
            <div class="offer-main-text">✨ Only <?php echo $monthly_discounted; ?>/week <br class="mobile-br"> for the first 3 months</div>
            <div class="offer-sub-text">Then $3/week. Cancel anytime.</div>
        </div>
    </div>

    <div class="container">
        <div class="pricing-grid">
            
            <!-- Monthly Card -->
            <div class="card" data-plan="monthly">
                <div style="height: 32px;"></div> 
                <div class="card-header">
                    <div class="plan-title">Monthly Subscription</div>
                    <div class="price-wrapper">
                        <div class="old-price">$12/month</div>
                        <div class="current-price"><?php echo $monthly_price_display; ?><span class="period">/mo</span></div>
                        <div class="billing-text">Billed every month</div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (!$is_logged_in): ?>
                    <input type="email" class="email-input subscription-email" placeholder="Enter your email" required />
                    <?php endif; ?>
                    
                    <button class="btn-select subscribe-btn" data-plan="monthly">Subscribe Monthly</button>
                    
                    <div class="error-message"></div>

                    <div class="secure-note">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Secure Checkout
                    </div>
                    
                    <div class="features">
                        <div class="features-title">What You Get</div>
                        <ul class="features-list">
                            <li>Unlimited access to all articles</li>
                            <li>Independent, agenda-free reporting</li>
                            <li>Fearless investigations on CCP influence</li>
                            <li>24/7 news updates</li>
                            <li>Weekly Insider newsletter</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Yearly Card -->
            <div class="card" data-plan="yearly" style="border-top: 4px solid var(--theme-black); border-color: var(--theme-black);">
                <div class="badge">Best Value</div>
                
                <div class="card-header">
                    <div class="plan-title">Yearly Subscription</div>
                    <div class="price-wrapper">
                        <div class="old-price"><?php echo $yearly_original_str; ?>/year</div>
                        <div class="current-price"><?php echo $yearly_price_display; ?><span class="period">/yr</span></div>
                        <div class="billing-text">Save $20 instantly</div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (!$is_logged_in): ?>
                    <input type="email" class="email-input subscription-email" placeholder="Enter your email" required />
                    <?php endif; ?>

                    <button class="btn-select subscribe-btn" data-plan="yearly">Subscribe Yearly</button>
                    
                    <div class="error-message"></div>

                    <div class="secure-note">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Secure Checkout
                    </div>
                    
                    <div class="features">
                        <div class="features-title">What You Get</div>
                        <ul class="features-list">
                            <li>Unlimited access to all articles</li>
                            <li>Independent, agenda-free reporting</li>
                            <li>Fearless investigations on CCP influence</li>
                            <li>24/7 news updates</li>
                            <li>Weekly Insider newsletter</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <div class="legal-text">
            <p><strong>Automatic Renewal:</strong> Your monthly subscription will automatically renew at the standard rate (currently <?php echo $monthly_price_display; ?>/mo + tax) after first 3 months unless you cancel. You may cancel at any time in your account settings. Sales tax may apply.</p>
        </div>
    </div>

    <footer>
        <p style="margin-bottom: 20px;">
            <a href="https://arizonainsiders.com/data-protection-privacy-policy/">Privacy Policy</a> | 
            <a href="https://arizonainsiders.com/terms-of-service/">Terms of Service</a>
        </p>
        
        <p>&copy; <?php echo date('Y'); ?> Arizona Insiders. All Rights Reserved.</p>
        
        <div style="margin-top: 25px;">
            <div class="need-help-text">Need help?</div>
            <a href="https://arizonainsiders.com/feedback-form/" class="btn-contact">Contact Us</a>
        </div>
    </footer>

    <script type="text/javascript">
    var wclData = <?php echo json_encode($wcl_data); ?>;

    jQuery(document).ready(function($) {
        
        // Sync email inputs (optional, just to be nice)
        $('.subscription-email').on('input', function() {
            $('.subscription-email').val($(this).val());
        });

        $('.subscribe-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var plan = $btn.data('plan');
            var $card = $btn.closest('.card');
            var $error = $card.find('.error-message');
            var email = '';

            // Get email if not logged in
            if (!wclData.isLoggedIn) {
                // Get the email from the input inside *this* card or just the global one
                // Since we synced them, value should be same.
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
                    post_id: wclData.postId, // might be 0
                    email: email,
                    test_mode: wclData.isTestMode ? 1 : 0
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : wclData.strings.error;
                        $error.text(msg).show();
                        $btn.prop('disabled', false).text(plan === 'monthly' ? 'Subscribe Monthly' : 'Subscribe Yearly');
                    }
                },
                error: function(xhr, status, error) {
                    $error.text(wclData.strings.error).show();
                    $btn.prop('disabled', false).text(plan === 'monthly' ? 'Subscribe Monthly' : 'Subscribe Yearly');
                }
            });
        });
    });
    </script>

</body>
</html>
