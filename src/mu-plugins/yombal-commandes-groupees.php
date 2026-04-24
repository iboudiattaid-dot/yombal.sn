<?php
/**
 * Plugin Name: Yombal Commandes Groupees
 */
add_action('init', function() {
  if (get_option('yombal_evt_v1')==='1') return;
  global $wpdb; $c=$wpdb->get_charset_collate();
  $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}yombal_evenements (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    organisateur_id bigint(20) NOT NULL,
    nom varchar(200) NOT NULL,
    date_evenement date DEFAULT NULL,
    nb_personnes int DEFAULT 1,
    description text DEFAULT NULL,
    code_invitation varchar(20) NOT NULL,
    mode_paiement enum('separe','groupe') DEFAULT 'separe',
    statut varchar(20) DEFAULT 'actif',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY code_invitation (code_invitation)
  ) $c");
  $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}yombal_evenement_participants (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    evenement_id bigint(20) NOT NULL,
    user_id bigint(20) DEFAULT NULL,
    nom_participant varchar(200) NOT NULL,
    email varchar(200) DEFAULT NULL,
    statut varchar(20) DEFAULT 'invite',
    order_id bigint(20) DEFAULT NULL,
    joined_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY evenement_id (evenement_id)
  ) $c");
  update_option('yombal_evt_v1','1');
  if (!get_page_by_path('rejoindre-evenement')) {
    wp_insert_post(['post_title'=>'Rejoindre un evenement','post_name'=>'rejoindre-evenement',
      'post_status'=>'publish','post_type'=>'page','post_author'=>1,
      'post_content'=>'<!-- wp:shortcode -->[yombal_rejoindre_evenement]<!-- /wp:shortcode -->']);
  }
});

// Onglet Mon compte
add_filter('woocommerce_account_menu_items', function($items) {
  $new=[];
  foreach ($items as $k=>$v) {
    $new[$k]=$v;
    if ($k==='orders') $new['mes-evenements']='Mes evenements';
  }
  return $new;
}, 40);
add_action('init', function() {
  add_rewrite_endpoint('mes-evenements', EP_ROOT|EP_PAGES);
}, 20);
add_action('woocommerce_account_mes-evenements_endpoint', function() {
  echo do_shortcode('[yombal_evenements]');
});

add_shortcode('yombal_evenements', function() {
  if (!is_user_logged_in()) return '<p><a href="/connexion/">Connectez-vous</a> pour gerer vos evenements.</p>';
  global $wpdb; $uid=get_current_user_id(); ob_start();
  if (isset($_POST['yombal_create_evt']) && wp_verify_nonce($_POST['_nonce'],'yombal_evt')) {
    $code=strtoupper(substr(md5(uniqid()),0,8));
    $wpdb->insert($wpdb->prefix.'yombal_evenements',[
      'organisateur_id'=>$uid,'nom'=>sanitize_text_field($_POST['nom']),
      'date_evenement'=>sanitize_text_field($_POST['date_evt']),
      'nb_personnes'=>intval($_POST['nb_personnes']),
      'description'=>sanitize_textarea_field($_POST['description']),
      'code_invitation'=>$code,'mode_paiement'=>sanitize_text_field($_POST['mode_paiement'])
    ]);
    echo '<div style="background:#d4edda;padding:12px;border-radius:8px;margin-bottom:16px">Evenement cree ! Code : <strong>'.$code.'</strong><br>
    Lien invites : <a href="/rejoindre-evenement/?code='.$code.'">/rejoindre-evenement/?code='.$code.'</a></div>';
  }
  $events=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}yombal_evenements WHERE organisateur_id=%d ORDER BY created_at DESC",$uid));
  echo '<div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;margin-bottom:24px">';
  echo '<h3 style="color:#04273e;margin-top:0">Creer un evenement</h3>';
  echo '<form method="post">'.wp_nonce_field('yombal_evt','_nonce',true,false);
  echo '<input type="hidden" name="yombal_create_evt" value="1">';
  echo '<p><label>Nom<br><input type="text" name="nom" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></label></p>';
  echo '<p><label>Date<br><input type="date" name="date_evt" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></label></p>';
  echo '<p><label>Nb personnes<br><input type="number" name="nb_personnes" value="2" min="1" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></label></p>';
  echo '<p><label>Description<br><textarea name="description" rows="3" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></textarea></label></p>';
  echo '<p><label>Paiement<br><select name="mode_paiement" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"><option value="separe">Chacun paie separement</option><option value="groupe">Paiement groupe</option></select></label></p>';
  echo '<button type="submit" style="background:#C8963E;color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-weight:600">Creer</button></form></div>';
  if ($events) {
    echo '<h3 style="color:#04273e">Mes evenements</h3>';
    foreach ($events as $e) {
      $nb=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}yombal_evenement_participants WHERE evenement_id=%d",$e->id));
      echo '<div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:16px;margin-bottom:12px">';
      echo '<strong style="color:#04273e">'.esc_html($e->nom).'</strong>';
      if ($e->date_evenement) echo ' - '.date('d/m/Y',strtotime($e->date_evenement));
      echo '<br><small>Code : <strong>'.$e->code_invitation.'</strong> | '.$nb.' participant(s)</small>';
      echo '<br><a href="/rejoindre-evenement/?code='.$e->code_invitation.'" style="color:#C8963E;font-size:0.85rem">Lien invitation</a></div>';
    }
  }
  return ob_get_clean();
});

add_shortcode('yombal_rejoindre_evenement', function() {
  global $wpdb; $code=isset($_GET['code'])?strtoupper(sanitize_text_field($_GET['code'])):''; ob_start();
  if (isset($_POST['yombal_join_evt']) && wp_verify_nonce($_POST['_nonce'],'yombal_join')) {
    $evt=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}yombal_evenements WHERE code_invitation=%s AND statut='actif'",sanitize_text_field($_POST['code'])));
    if ($evt) {
      $wpdb->insert($wpdb->prefix.'yombal_evenement_participants',[
        'evenement_id'=>$evt->id,'user_id'=>is_user_logged_in()?get_current_user_id():null,
        'nom_participant'=>sanitize_text_field($_POST['nom_participant']),
        'email'=>sanitize_email($_POST['email']),'statut'=>'confirme'
      ]);
      echo '<div style="background:#d4edda;padding:16px;border-radius:8px">Vous avez rejoint <strong>'.esc_html($evt->nom).'</strong> !</div>';
    } else {
      echo '<div style="background:#f8d7da;padding:16px;border-radius:8px">Code invalide ou evenement termine.</div>';
    }
  }
  echo '<div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;max-width:480px;margin:0 auto">';
  echo '<h2 style="color:#04273e;margin-top:0">Rejoindre un evenement</h2>';
  echo '<form method="post">'.wp_nonce_field('yombal_join','_nonce',true,false);
  echo '<input type="hidden" name="yombal_join_evt" value="1">';
  echo '<p><label>Code<br><input type="text" name="code" value="'.esc_attr($code).'" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;text-transform:uppercase"></label></p>';
  echo '<p><label>Votre nom<br><input type="text" name="nom_participant" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></label></p>';
  echo '<p><label>Votre email<br><input type="email" name="email" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></label></p>';
  echo '<button type="submit" style="background:#C8963E;color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-weight:600;width:100%">Rejoindre</button></form></div>';
  return ob_get_clean();
});