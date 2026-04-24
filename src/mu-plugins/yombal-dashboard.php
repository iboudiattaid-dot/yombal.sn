<?php
// Yombal Dashboard - simplified vendor dashboard without overlaps

if (function_exists('get_option') && in_array('yombal-core/yombal-core.php', (array) get_option('active_plugins', []), true)) {
    return;
}

// Check if current user is a WCFM vendor
function yombal_is_wcfm_vendor() {
    $uid = get_current_user_id();
    if (!$uid) return false;
    $caps = (array) get_user_meta($uid, 'wp_capabilities', true);
    return isset($caps['wcfm_vendor']) || user_can($uid, 'wcfm_vendor') ||
           get_user_meta($uid, 'wcfm_vendor', true);
}

// Get vendor first name
function yombal_vendor_firstname() {
    $u = wp_get_current_user();
    return $u->first_name ? $u->first_name : $u->display_name;
}

function yombal_vendor_profile_completion($uid = 0) {
    if (!$uid) $uid = get_current_user_id();

    $user = get_userdata($uid);
    $fields = array(
        (string) ($user->display_name ?? ''),
        (string) get_user_meta($uid, 'wcfm_store_name', true),
        (string) get_user_meta($uid, 'billing_phone', true),
        (string) get_user_meta($uid, 'phone', true),
        (string) ($user->description ?? ''),
        (string) (function_exists('yombal_vendor_type') ? yombal_vendor_type($uid) : ''),
    );

    $completed = 0;
    foreach ($fields as $field) {
        if (trim($field) !== '') {
            $completed++;
        }
    }

    return (int) round(($completed / max(count($fields), 1)) * 100);
}

function yombal_vendor_type_label($type) {
    $map = array(
        'tailleur' => 'Couturier',
        'tissu' => 'Vendeur de tissus',
        'hybride' => 'Partenaire hybride',
        'hybrid' => 'Partenaire hybride',
        'tailor' => 'Couturier',
        'fabric_vendor' => 'Vendeur de tissus',
    );

    return isset($map[$type]) ? $map[$type] : 'Partenaire';
}

// Get product count for vendor
function yombal_vendor_product_count($uid = 0) {
    if (!$uid) $uid = get_current_user_id();
    $q = new WP_Query(array(
        'post_type'      => 'product',
        'author'         => $uid,
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    return (int) $q->found_posts;
}

// Get order count for vendor
function yombal_vendor_order_count($uid = 0, $status = '') {
    if (!$uid) $uid = get_current_user_id();
    global $wpdb;
    $p = $wpdb->prefix;
    if ($status) {
        $r = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT order_id) FROM {$p}wcfm_marketplace_orders WHERE vendor_id=%d AND order_status=%s",
            $uid, $status
        ));
    } else {
        $r = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT order_id) FROM {$p}wcfm_marketplace_orders WHERE vendor_id=%d",
            $uid
        ));
    }
    return (int) $r;
}

// Get unread messages count
function yombal_vendor_message_count($uid = 0) {
    if (!$uid) $uid = get_current_user_id();
    global $wpdb;
    $p = $wpdb->prefix;
    $r = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}wcfm_messages WHERE receiver_id=%d AND is_read=0",
        $uid
    ));
    return (int) $r;
}

// Get recent orders for vendor
function yombal_vendor_recent_orders($uid = 0, $limit = 5) {
    if (!$uid) $uid = get_current_user_id();
    global $wpdb;
    $p = $wpdb->prefix;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT order_id, order_status, modified
         FROM {$p}wcfm_marketplace_orders
         WHERE vendor_id=%d
         ORDER BY modified DESC
         LIMIT %d",
        $uid, $limit
    ));
    return $rows ? $rows : array();
}

// Status label map
function yombal_order_status_label($s) {
    $map = array(
        'wc-processing' => 'En cours',
        'processing'    => 'En cours',
        'wc-completed'  => 'Terminee',
        'completed'     => 'Terminee',
        'wc-pending'    => 'En attente',
        'pending'       => 'En attente',
        'wc-on-hold'    => 'En pause',
        'on-hold'       => 'En pause',
        'wc-cancelled'  => 'Annulee',
        'cancelled'     => 'Annulee',
    );
    return isset($map[$s]) ? $map[$s] : ucfirst(str_replace(array('wc-','-'), array('',''), $s));
}
function yombal_status_color($s) {
    if (strpos($s,'complet')!==false) return '#27ae60';
    if (strpos($s,'cours')!==false)   return '#e67e22';
    if (strpos($s,'attente')!==false) return '#3498db';
    if (strpos($s,'annul')!==false)   return '#e74c3c';
    return '#888';
}

// --- CSS for dashboard ---
add_action('wp_head', 'yombal_dashboard_css');
function yombal_dashboard_css() {
    if (!is_page('store-manager') && !function_exists('wcfm_is_store_manager')) return;
    if (!yombal_is_wcfm_vendor()) return;
    echo '<style>
    /* Fix WCFM dashboard overlaps */
    .wcfm-container             { overflow: visible !important; }
    #wcfm_dashboard_wrapper     { padding: 20px !important; }
    /* Hide default WCFM widgets on dashboard */
    .wcfm_dashboard_top_block,
    .wcfm_dashboard_right_blocks,
    .wcfm_dashboard_earning_block,
    .wcfm-tab-wrapper { display: none !important; }
    #yombal-dash { padding: 0 10px; }
    #yombal-dash .yombal-card { margin-bottom: 0; }
    #yombal-dash .yombal-grid--stats { margin-bottom: 0; }
    </style>';
}

// --- Custom dashboard HTML injection ---
add_action('wcfm_dashboard_top', 'yombal_render_custom_dashboard', 1);
function yombal_render_custom_dashboard() {
    if (!yombal_is_wcfm_vendor()) return;

    $uid      = get_current_user_id();
    $name     = yombal_vendor_firstname();
    $type     = yombal_vendor_type($uid);
    $is_tiss  = yombal_is_tissu_vendor($uid);
    $is_tail  = yombal_is_tailleur_vendor($uid);

    $n_orders  = yombal_vendor_order_count($uid);
    $n_msgs    = yombal_vendor_message_count($uid);
    $n_prods   = yombal_vendor_product_count($uid);
    $completion = yombal_vendor_profile_completion($uid);

    $store_url = function_exists('wcfm_get_store_url') ? wcfm_get_store_url($uid) : get_author_posts_url($uid);
    $mgr_url   = wc_get_page_permalink('store-manager');
    $orders_url = $mgr_url . '?orders';

    if ($is_tail && !$is_tiss) {
        $prod_label  = 'Mes modeles';
        $btn_label   = '+ Ajouter un modele';
        $btn_url     = $mgr_url . '?products-manage';
    } elseif ($is_tiss && !$is_tail) {
        $prod_label  = 'Mes tissus';
        $btn_label   = '+ Ajouter un tissu';
        $btn_url     = $mgr_url . '?products-manage';
    } else {
        $prod_label  = 'Mes produits';
        $btn_label   = '+ Ajouter un produit';
        $btn_url     = $mgr_url . '?products-manage';
    }

    $recent = yombal_vendor_recent_orders($uid, 5);

    echo '<div id="yombal-dash" class="yombal-ui yombal-shell">';
    echo '<section class="yombal-hero">';
    echo '<span class="yombal-eyebrow">Mon espace partenaire</span>';
    echo '<h1>Bonjour, ' . esc_html($name) . '</h1>';
    echo '<div class="yombal-inline-meta"><span>' . esc_html(wp_date('l j F Y', current_time('timestamp'))) . '</span><span>Activite: <strong>' . esc_html(yombal_vendor_type_label($type)) . '</strong></span></div>';
    echo '<p>Retrouvez ici un apercu clair de vos commandes, de votre catalogue et des prochaines actions utiles pour votre activite.</p>';
    echo '<div class="yombal-dashboard-progress">';
    echo '<div class="yombal-dashboard-progress__header"><strong>Profil complete a ' . esc_html((string) $completion) . '%</strong><span>Mon espace</span></div>';
    echo '<div class="yombal-dashboard-progress__bar"><span style="width:' . esc_attr((string) $completion) . '%"></span></div>';
    echo '<p>Ajoutez vos informations de boutique et vos produits pour mieux accueillir les clients.</p>';
    echo '</div>';
    echo '</section>';

    if (class_exists('\\Yombal\\Core\\UI\\Dashboard_Shell')) {
        echo \Yombal\Core\UI\Dashboard_Shell::render_metrics(array(
            array('value' => (string) $n_orders, 'label' => 'Commandes'),
            array('value' => (string) $n_prods, 'label' => $prod_label),
            array('value' => (string) $n_msgs, 'label' => 'Messages non lus'),
        ));

        echo \Yombal\Core\UI\Dashboard_Shell::render_section(
            'Actions rapides',
            'Gerez les actions essentielles de votre espace en quelques clics.',
            \Yombal\Core\UI\Dashboard_Shell::render_action_cards(array(
                array(
                    'label' => $btn_label,
                    'description' => 'Ajouter une nouvelle fiche a votre catalogue.',
                    'url' => $btn_url,
                    'tone' => 'accent',
                ),
                array(
                    'label' => 'Voir ma boutique',
                    'description' => 'Consulter votre vitrine publique comme un client.',
                    'url' => $store_url,
                    'tone' => 'secondary',
                ),
                array(
                    'label' => 'Suivre mes commandes',
                    'description' => 'Ouvrir la liste des commandes les plus recentes.',
                    'url' => $orders_url,
                    'tone' => 'secondary',
                ),
            )),
            'soft'
        );
    }

    echo '<section class="yombal-card">';
    echo '<div class="yombal-card__header"><div class="yombal-stack"><h2 class="yombal-section-title">Activite recente</h2><div class="yombal-card__meta">Suivez vos dernieres commandes et leur statut en un coup d oeil.</div></div></div>';
    if (empty($recent)) {
        echo '<div class="yombal-empty-state">Aucune commande pour le moment.</div>';
    } else {
        echo '<ul class="yombal-list">';
        foreach ($recent as $o) {
            $lbl = yombal_order_status_label($o->order_status);
            $order_url = $mgr_url . '?orders&order_id=' . $o->order_id;
            echo '<li><strong><a href="' . esc_url($order_url) . '">Commande #' . esc_html((string) $o->order_id) . '</a></strong>';
            echo '<div class="yombal-inline-meta"><span>' . esc_html($lbl) . '</span><span>' . esc_html((string) $o->modified) . '</span></div></li>';
        }
        echo '</ul>';
    }
    echo '</section>';
    echo '</div>';
}
