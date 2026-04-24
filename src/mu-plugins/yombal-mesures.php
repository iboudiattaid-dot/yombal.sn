<?php
/* Plugin Name: Yombal Systeme Mesures Client */

if (function_exists('get_option') && in_array('yombal-core/yombal-core.php', (array) get_option('active_plugins', []), true)) {
    return;
}

/**
 * SYSTEME DE MESURES CLIENT YOMBAL
 * 
 * Permet a chaque client d'avoir plusieurs profils de mesures
 * (ex: mesures personnelles, mesures mariage invites, etc.)
 * et de selectionner le bon profil au moment de la commande.
 */

// === 1. TABLE DE MESURES ===
// Creee a l'activation via dbDelta

add_action('init', function() {
    if (get_option('yombal_mesures_table_v2') !== '1') {
        global $wpdb;
        $table = $wpdb->prefix . 'yombal_mesures';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            profil_nom varchar(100) NOT NULL DEFAULT 'Mon profil',
            occasion varchar(100) DEFAULT NULL,
            poitrine decimal(5,1) DEFAULT NULL,
            taille decimal(5,1) DEFAULT NULL,
            hanches decimal(5,1) DEFAULT NULL,
            epaules decimal(5,1) DEFAULT NULL,
            longueur_buste decimal(5,1) DEFAULT NULL,
            longueur_robe decimal(5,1) DEFAULT NULL,
            longueur_manche decimal(5,1) DEFAULT NULL,
            tour_bras decimal(5,1) DEFAULT NULL,
            encolure decimal(5,1) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('yombal_mesures_table_v2', '1');
    }
});

// === 2. SHORTCODE PAGE MESURES CLIENT ===
add_shortcode('yombal_mes_mesures', function() {
    if (!is_user_logged_in()) {
        return '<p>Vous devez etre connecte pour gerer vos mesures. <a href="' . wc_get_page_permalink('myaccount') . '">Se connecter</a></p>';
    }
    ob_start();
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . 'yombal_mesures';
    
    // Traitement formulaire
    if (isset($_POST['yombal_save_mesure']) && wp_verify_nonce($_POST['_wpnonce'], 'yombal_mesure_' . $user_id)) {
        $id = intval($_POST['mesure_id'] ?? 0);
        $data = [
            'user_id'          => $user_id,
            'profil_nom'       => sanitize_text_field($_POST['profil_nom'] ?? 'Mon profil'),
            'occasion'         => sanitize_text_field($_POST['occasion'] ?? ''),
            'poitrine'         => floatval($_POST['poitrine'] ?? 0) ?: null,
            'taille'           => floatval($_POST['taille'] ?? 0) ?: null,
            'hanches'          => floatval($_POST['hanches'] ?? 0) ?: null,
            'epaules'          => floatval($_POST['epaules'] ?? 0) ?: null,
            'longueur_buste'   => floatval($_POST['longueur_buste'] ?? 0) ?: null,
            'longueur_robe'    => floatval($_POST['longueur_robe'] ?? 0) ?: null,
            'longueur_manche'  => floatval($_POST['longueur_manche'] ?? 0) ?: null,
            'tour_bras'        => floatval($_POST['tour_bras'] ?? 0) ?: null,
            'encolure'         => floatval($_POST['encolure'] ?? 0) ?: null,
            'notes'            => sanitize_textarea_field($_POST['notes'] ?? ''),
        ];
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id, 'user_id' => $user_id]);
        } else {
            $wpdb->insert($table, $data);
        }
        echo '<div style="background:#d4edda;border:1px solid #c3e6cb;padding:12px 16px;border-radius:6px;margin-bottom:20px;color:#155724">Profil de mesures enregistre.</div>';
    }
    
    // Suppression
    if (isset($_GET['delete_mesure']) && isset($_GET['_n']) && wp_verify_nonce($_GET['_n'], 'del_mesure_' . $_GET['delete_mesure'])) {
        $wpdb->delete($table, ['id' => intval($_GET['delete_mesure']), 'user_id' => $user_id]);
        echo '<div style="background:#fff3cd;padding:12px 16px;border-radius:6px;margin-bottom:20px">Profil supprime.</div>';
    }
    
    // Liste des profils
    $profils = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC", $user_id));
    ?>
    <div class="yombal-mesures-wrap" style="font-family:sans-serif;max-width:900px">
        <h2 style="color:#04273e;border-bottom:2px solid #C8963E;padding-bottom:10px">Mes profils de mesures</h2>
        
        <?php if ($profils): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px">
            <?php foreach($profils as $p): ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <strong style="color:#04273e;font-size:1.05rem"><?php echo esc_html($p->profil_nom); ?></strong>
                    <span style="background:#f0f4f8;padding:2px 10px;border-radius:20px;font-size:0.78rem;color:#666"><?php echo esc_html($p->occasion ?: 'General'); ?></span>
                </div>
                <table style="width:100%;font-size:0.85rem;border-collapse:collapse">
                    <?php
                    $champs = ['poitrine'=>'Poitrine','taille'=>'Taille','hanches'=>'Hanches','epaules'=>'Epaules','longueur_buste'=>'Long. buste','longueur_robe'=>'Long. robe','longueur_manche'=>'Long. manche','tour_bras'=>'Tour bras','encolure'=>'Encolure'];
                    foreach($champs as $key=>$label):
                        if (!$p->$key) continue;
                    ?>
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:4px 0;color:#666"><?php echo $label; ?></td>
                        <td style="padding:4px 0;font-weight:600;text-align:right"><?php echo $p->$key; ?> cm</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if ($p->notes): ?>
                <p style="font-size:0.82rem;color:#888;margin-top:8px;font-style:italic"><?php echo esc_html($p->notes); ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:8px;margin-top:14px">
                    <a href="?edit_mesure=<?php echo $p->id; ?>" style="flex:1;text-align:center;padding:7px;background:#e8f0f8;color:#1a3c5e;border-radius:6px;text-decoration:none;font-size:0.85rem">Modifier</a>
                    <a href="?delete_mesure=<?php echo $p->id; ?>&_n=<?php echo wp_create_nonce('del_mesure_'.$p->id); ?>" onclick="return confirm('Supprimer ce profil ?')" style="flex:1;text-align:center;padding:7px;background:#ffeaea;color:#c0392b;border-radius:6px;text-decoration:none;font-size:0.85rem">Supprimer</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:#666;margin-bottom:24px">Aucun profil de mesures enregistre. Creez votre premier profil ci-dessous.</p>
        <?php endif; ?>

        <!-- Formulaire ajout/edition -->
        <?php
        $edit = null;
        if (isset($_GET['edit_mesure'])) {
            $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND user_id=%d", intval($_GET['edit_mesure']), $user_id));
        }
        ?>
        <div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:10px;padding:24px;margin-top:8px">
            <h3 style="color:#04273e;margin-top:0"><?php echo $edit ? 'Modifier le profil' : 'Ajouter un profil de mesures'; ?></h3>
            <form method="post">
                <?php wp_nonce_field('yombal_mesure_' . $user_id); ?>
                <?php if ($edit): ?><input type="hidden" name="mesure_id" value="<?php echo $edit->id; ?>"><?php endif; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;color:#333">Nom du profil *</label>
                        <input type="text" name="profil_nom" value="<?php echo esc_attr($edit->profil_nom ?? ''); ?>" placeholder="Ex: Mes mesures, Mariage Fatou..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box" required>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;color:#333">Occasion</label>
                        <input type="text" name="occasion" value="<?php echo esc_attr($edit->occasion ?? ''); ?>" placeholder="Ex: Mariage, Bapteme, Quotidien..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
                    <?php
                    $fields_map = ['poitrine'=>'Poitrine (cm)','taille'=>'Taille (cm)','hanches'=>'Hanches (cm)','epaules'=>'Epaules (cm)','longueur_buste'=>'Long. buste (cm)','longueur_robe'=>'Long. robe (cm)','longueur_manche'=>'Long. manche (cm)','tour_bras'=>'Tour bras (cm)','encolure'=>'Encolure (cm)'];
                    foreach($fields_map as $name=>$label):
                    ?>
                    <div>
                        <label style="display:block;font-size:0.88rem;font-weight:600;margin-bottom:4px;color:#555"><?php echo $label; ?></label>
                        <input type="number" step="0.5" min="0" name="<?php echo $name; ?>" value="<?php echo esc_attr($edit->$name ?? ''); ?>" style="width:100%;padding:9px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box">
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-bottom:16px">
                    <label style="display:block;font-weight:600;margin-bottom:4px;color:#333">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Informations complementaires..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box"><?php echo esc_textarea($edit->notes ?? ''); ?></textarea>
                </div>
                <button type="submit" name="yombal_save_mesure" style="background:#C8963E;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:600">
                    <?php echo $edit ? 'Mettre a jour' : 'Enregistrer ce profil'; ?>
                </button>
                <?php if ($edit): ?>
                <a href="?" style="margin-left:12px;color:#666;text-decoration:none">Annuler</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

// === 3. CHAMP MESURES AU CHECKOUT ===
add_action('woocommerce_before_order_notes', function($checkout) {
    $user_id = get_current_user_id();
    if (!$user_id) return;
    
    // Verifier si le panier contient des produits couture
    $has_couture = false;
    foreach (WC()->cart->get_cart() as $item) {
        $cats = wp_get_post_terms($item['product_id'], 'product_cat', ['fields'=>'slugs']);
        if (in_array('modeles-couture', $cats) || in_array('couture', $cats)) {
            $has_couture = true;
            break;
        }
    }
    if (!$has_couture) return;

    global $wpdb;
    $table = $wpdb->prefix . 'yombal_mesures';
    $profils = $wpdb->get_results($wpdb->prepare("SELECT id, profil_nom, occasion FROM $table WHERE user_id=%d ORDER BY created_at DESC", $user_id));
    
    echo '<div id="yombal-mesures-checkout" style="background:#f0f6ff;border:1px solid #c8d8f0;border-radius:8px;padding:20px;margin:20px 0">';
    echo '<h3 style="color:#04273e;margin-top:0;font-size:1.1rem">Mesures pour votre commande</h3>';
    
    if ($profils) {
        echo '<p style="color:#555;font-size:0.9rem;margin-bottom:12px">Selectionnez un profil de mesures enregistre :</p>';
        echo '<select name="yombal_mesure_id" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;margin-bottom:12px">';
        echo '<option value="">-- Choisir un profil --</option>';
        foreach ($profils as $p) {
            echo '<option value="'.esc_attr($p->id).'">'.esc_html($p->profil_nom).($p->occasion?' ('.$p->occasion.')':'').'</option>';
        }
        echo '</select>';
        echo '<p style="font-size:0.85rem;color:#888">Ou <a href="/mes-mesures/" style="color:#C8963E">ajouter un nouveau profil de mesures</a> avant de commander.</p>';
    } else {
        echo '<p style="color:#555;font-size:0.9rem">Vous n\'avez pas encore de profil de mesures. <a href="/mes-mesures/" style="color:#C8963E;font-weight:600">Creer mon profil de mesures</a></p>';
    }
    echo '</div>';
});

// === 4. SAUVEGARDER LES MESURES AVEC LA COMMANDE ===
add_action('woocommerce_checkout_order_created', function($order) {
    $mesure_id = intval($_POST['yombal_mesure_id'] ?? 0);
    if ($mesure_id > 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'yombal_mesures';
        $mesure = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND user_id=%d", $mesure_id, get_current_user_id()));
        if ($mesure) {
            $order->update_meta_data('_yombal_mesure_id', $mesure_id);
            $order->update_meta_data('_yombal_mesure_profil', $mesure->profil_nom);
            $order->update_meta_data('_yombal_mesure_data', json_encode((array)$mesure));
        }
    }
});

// === 5. AFFICHER MESURES DANS L'ADMIN COMMANDE ===
add_action('woocommerce_admin_order_data_after_shipping_address', function($order) {
    $data = $order->get_meta('_yombal_mesure_data');
    if (!$data) return;
    $m = json_decode($data, true);
    echo '<div style="margin-top:16px;padding:16px;background:#f8f9fa;border-radius:8px">';
    echo '<h4 style="margin:0 0 12px;color:#04273e">Mesures client - '.esc_html($m['profil_nom'] ?? '').'</h4>';
    $champs = ['poitrine'=>'Poitrine','taille'=>'Taille','hanches'=>'Hanches','epaules'=>'Epaules','longueur_buste'=>'L. buste','longueur_robe'=>'L. robe','longueur_manche'=>'L. manche','tour_bras'=>'Tour bras','encolure'=>'Encolure'];
    echo '<table style="width:100%;font-size:0.85rem"><tbody>';
    foreach ($champs as $k=>$l) {
        if (!empty($m[$k])) echo '<tr><td style="color:#666;padding:3px 0">'.$l.'</td><td style="font-weight:600">'.$m[$k].' cm</td></tr>';
    }
    echo '</tbody></table>';
    if (!empty($m['notes'])) echo '<p style="font-style:italic;color:#888;margin-top:8px;font-size:0.85rem">'.esc_html($m['notes']).'</p>';
    echo '</div>';
});

// === 6. CREER LA PAGE MES MESURES SI ELLE N'EXISTE PAS ===
add_action('init', function() {
    if (get_option('yombal_mesures_page_created') === '1') return;
    $existing = get_page_by_path('mes-mesures');
    if (!$existing) {
        $page_id = wp_insert_post([
            'post_title'   => 'Mes mesures',
            'post_name'    => 'mes-mesures',
            'post_content' => '[yombal_mes_mesures]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            update_option('yombal_mesures_page_created', '1');
        }
    } else {
        update_option('yombal_mesures_page_created', '1');
    }
});
