<?php
// Yombal Store Display - Fiverr-style store cards and vendor profile pages

if (function_exists('get_option') && in_array('yombal-core/yombal-core.php', (array) get_option('active_plugins', []), true)) {
    return;
}

// --- Badge type helper ---
function yombal_vendor_badge($uid) {
    $t = (string) get_user_meta($uid, 'wcfm_vendor_tax', true);
    if (strpos($t,'hybride') !== false) {
        return array('label'=>'Tailleur + Tissus', 'color'=>'#8e44ad');
    } elseif (strpos($t,'tailleur') !== false) {
        return array('label'=>'Tailleur', 'color'=>'#04273e');
    } elseif (strpos($t,'tissus') !== false) {
        return array('label'=>'Tissus', 'color'=>'#e67e22');
    }
    return array('label'=>'Partenaire', 'color'=>'#888');
}

// --- Completed orders count for a vendor ---
function yombal_vendor_completed_count($uid) {
    global $wpdb; $p = $wpdb->prefix;
    $r = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT order_id) FROM {$p}wcfm_marketplace_orders
         WHERE vendor_id=%d AND order_status IN ('completed','wc-completed')",
        $uid
    ));
    return (int)$r;
}

// --- In-progress orders count ---
function yombal_vendor_inprogress_count($uid) {
    global $wpdb; $p = $wpdb->prefix;
    $r = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT order_id) FROM {$p}wcfm_marketplace_orders
         WHERE vendor_id=%d AND order_status IN ('processing','wc-processing','on-hold','wc-on-hold')",
        $uid
    ));
    return (int)$r;
}

// --- Vendor rating ---
function yombal_vendor_rating($uid) {
    global $wpdb; $p = $wpdb->prefix;
    $r = $wpdb->get_var($wpdb->prepare(
        "SELECT avg_rating FROM {$p}wcfm_marketplace_vendor_ratings WHERE vendor_id=%d",
        $uid
    ));
    return $r ? number_format((float)$r, 1) : null;
}

// --- Member since year ---
function yombal_vendor_since($uid) {
    $u = get_userdata($uid);
    return $u ? date('Y', strtotime($u->user_registered)) : '';
}

// ============================================================
// A) CATALOGUE CARDS - CSS override
// ============================================================
add_action('wp_head', 'yombal_catalogue_card_css');
function yombal_catalogue_card_css() {
    $pages = array('catalogue-tailleurs','catalogue-tissus');
    $slug  = get_query_var('pagename');
    if (!$slug) { global $post; $slug = is_object($post) ? $post->post_name : ''; }
    if (!in_array($slug, $pages) && !is_page($pages)) return;
    echo '<style>
    /* Hide banner on store cards */
    .wcfmmp-store-list-wrapper .store-banner-img,
    .wcfmmp-store-list-wrapper .wcfmmp-store-banner,
    .wcfmmp-store-list-wrapper .store-card-banner { display: none !important; }

    /* Card layout */
    .wcfmmp-store-list-wrapper .woocommerce-store-card,
    .wcfmmp-store-list-wrapper .wcfmmp-store-card {
        text-align: center !important;
        padding: 24px 16px 20px !important;
        border-radius: 12px !important;
        box-shadow: 0 2px 8px rgba(0,0,0,.08) !important;
        border: 1px solid #eee !important;
        transition: transform .2s, box-shadow .2s;
        position: relative !important;
    }
    .wcfmmp-store-list-wrapper .woocommerce-store-card:hover,
    .wcfmmp-store-list-wrapper .wcfmmp-store-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,.13) !important;
    }

    /* Avatar */
    .wcfmmp-store-list-wrapper .store-avatar img,
    .wcfmmp-store-list-wrapper .wcfmmp-store-logo img {
        border-radius: 50% !important;
        width: 80px !important;
        height: 80px !important;
        object-fit: cover !important;
        margin: 0 auto 12px !important;
        display: block !important;
        border: 3px solid #f0f0f0 !important;
    }

    /* Store name */
    .wcfmmp-store-list-wrapper .wcfmmp-store-name,
    .wcfmmp-store-list-wrapper .store-name {
        font-weight: 700 !important;
        font-size: 1em !important;
        color: #1a1a1a !important;
        margin-bottom: 6px !important;
        display: block !important;
    }

    /* Badge */
    .y-store-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        color: #fff;
        font-size: .75em;
        font-weight: 600;
        margin-bottom: 8px;
    }

    /* Stars */
    .y-card-stars { color: #f39c12; font-size: .85em; margin-bottom: 6px; }
    .y-card-stats { font-size: .8em; color: #888; margin-bottom: 12px; }

    /* View button */
    .y-card-btn {
        display: inline-block;
        background: #04273e;
        color: #fff !important;
        padding: 8px 22px;
        border-radius: 6px;
        font-size: .85em;
        font-weight: 600;
        text-decoration: none !important;
        transition: background .2s;
    }
    .y-card-btn:hover { background: #063556; }
    </style>';
}

// ============================================================
// B) STORE PROFILE PAGE - Header + Stats + Tabs override
// ============================================================
add_action('wp_head', 'yombal_store_profile_css');
function yombal_store_profile_css() {
    if (!function_exists('wcfmmp_is_store_page') || !wcfmmp_is_store_page()) return;
    echo '<style>
    /* Hide default WCFM store banner */
    .wcfmmp-store-profile-header,
    .store_author_thumbnail,
    .wcfmmp-store-banner-wrap { display: none !important; }

    /* Custom header */
    #yombal-store-header {
        background: #04273e;
        padding: 32px 20px 24px;
        text-align: center;
        margin-bottom: 0;
    }
    #yombal-store-header .y-avatar-wrap {
        width: 100px; height: 100px;
        border-radius: 50%;
        border: 3px solid #fff;
        overflow: hidden;
        margin: 0 auto 12px;
        background: #fff;
    }
    #yombal-store-header .y-avatar-wrap img {
        width: 100%; height: 100%; object-fit: cover;
    }
    #yombal-store-header h1 {
        color: #fff !important;
        font-size: 1.4em;
        margin: 0 0 8px;
    }
    #yombal-store-header .y-badge {
        display: inline-block;
        padding: 4px 16px;
        border-radius: 20px;
        color: #fff;
        font-size: .8em;
        font-weight: 600;
        border: 1px solid rgba(255,255,255,.4);
        margin-bottom: 0;
    }

    /* Stats bar */
    #yombal-store-stats {
        display: flex;
        border-bottom: 1px solid #e0e0e0;
        background: #fff;
        margin-bottom: 20px;
    }
    .y-stat-item {
        flex: 1;
        text-align: center;
        padding: 16px 8px;
        border-right: 1px solid #e0e0e0;
    }
    .y-stat-item:last-child { border-right: none; }
    .y-stat-icon { font-size: 1.2em; margin-bottom: 4px; }
    .y-stat-val  { font-weight: 700; font-size: 1em; color: #1a1a1a; }
    .y-stat-lbl  { font-size: .72em; color: #888; margin-top: 2px; }

    /* Hide standard WCFM store tabs (About, Policy, Delivery, etc.) */
    .wcfmmp-store-policies-tab,
    .wcfmmp-store-about-tab,
    a[href*="tab=about"], a[href*="tab=policy"],
    a[href*="tab=delivery"], a[href*="tab=questions"],
    li.about_tab, li.policy_tab, li.delivery_tab, li.questions_tab,
    .wcfmmp_store_tab_about, .wcfmmp_store_tab_policy,
    .wcfmmp_store_tab_delivery, .wcfmmp_store_tab_qa { display: none !important; }

    /* Keep products + reviews tabs */
    .wcfmmp-store-profile { padding-top: 0 !important; }
    </style>';
}

// Inject custom header + stats bar on store page
add_action('wcfmmp_store_profile_banner', 'yombal_store_profile_header', 5);
function yombal_store_profile_header() {
    $vendor_id = apply_filters('wcfmmp_store_vendor_id', 0);
    if (!$vendor_id) {
        global $WCFM;
        $store_name = get_query_var('wcfmmp_store');
        if ($store_name) {
            $u = get_user_by('slug', $store_name);
            if ($u) $vendor_id = $u->ID;
        }
    }
    if (!$vendor_id) return;

    $badge      = yombal_vendor_badge($vendor_id);
    $rating     = yombal_vendor_rating($vendor_id);
    $completed  = yombal_vendor_completed_count($vendor_id);
    $inprogress = yombal_vendor_inprogress_count($vendor_id);
    $since      = yombal_vendor_since($vendor_id);
    $store_name = get_user_meta($vendor_id, 'wcfm_store_name', true);
    if (!$store_name) { $u = get_userdata($vendor_id); $store_name = $u ? $u->display_name : ''; }
    $avatar_url = get_avatar_url($vendor_id, array('size'=>200));

    // Header
    echo '<div id="yombal-store-header">';
    echo '<div class="y-avatar-wrap"><img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($store_name) . '"/></div>';
    echo '<h1>' . esc_html($store_name) . '</h1>';
    echo '<span class="y-badge" style="background:' . esc_attr($badge['color']) . '">' . esc_html($badge['label']) . '</span>';
    echo '</div>';

    // Stats bar
    echo '<div id="yombal-store-stats">';

    // Rating
    echo '<div class="y-stat-item">';
    echo '<div class="y-stat-icon">&#11088;</div>';
    if ($rating) {
        echo '<div class="y-stat-val">' . $rating . '/5</div>';
    } else {
        echo '<div class="y-stat-val">-</div>';
    }
    echo '<div class="y-stat-lbl">Note</div>';
    echo '</div>';

    // Completed
    echo '<div class="y-stat-item">';
    echo '<div class="y-stat-icon">&#10003;</div>';
    echo '<div class="y-stat-val">' . $completed . '</div>';
    echo '<div class="y-stat-lbl">Terminees</div>';
    echo '</div>';

    // In progress
    echo '<div class="y-stat-item">';
    echo '<div class="y-stat-icon">&#8987;</div>';
    echo '<div class="y-stat-val">' . $inprogress . '</div>';
    echo '<div class="y-stat-lbl">En cours</div>';
    echo '</div>';

    // Member since
    echo '<div class="y-stat-item">';
    echo '<div class="y-stat-icon">&#128197;</div>';
    echo '<div class="y-stat-val">' . esc_html($since) . '</div>';
    echo '<div class="y-stat-lbl">Membre depuis</div>';
    echo '</div>';

    echo '</div>'; // y-store-stats
}
