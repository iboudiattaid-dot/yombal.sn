<?php
/* Plugin Name: Yombal CSS Fix */

if (!(function_exists('get_option') && in_array('yombal-core/yombal-core.php', (array) get_option('active_plugins', []), true))) {
    add_action('wp_head', function() {
        echo '<style id="yombal-cssfix">
.wcfm_menu_items_wrap .wcfm_menu_item span { display: block !important; }
.footer-copyright { display: none !important; }
.elementor-kit-7,
.e-notice[data-notice_id],
div.notice:has(a[href*="royal"]),
div.notice:has(a[href*="go-pro"]),
#setting-error-tgmpa,
.elementor-admin-notices-wrap .e-notice { display: none !important; }
</style>';
    }, 3);
}

add_action('admin_head', function() {
    echo '<style id="yombal-admin-cssfix">
div.notice:has(a[href*="royal-elementor"]),
div.notice:has(a[href*="go-pro"]),
.royal-notice,
#royal-companion-notice,
.wpr-notice,
.wpr-admin-notice,
div[id^="wpr-"]:not(#wpcontent):not(#wpwrap) { display: none !important; }
</style>';
}, 1);

add_action('admin_init', function() {
    remove_action('admin_notices', 'wpr_rating_notice', 99);
    remove_action('admin_notices', 'wpr_go_pro_notice', 99);
}, 5);
