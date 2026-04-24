<?php
/**
 * Plugin Name: Yombal Messagerie Bridee v2
 */
if (function_exists('get_option') && in_array('yombal-core/yombal-core.php', (array) get_option('active_plugins', []), true)) {
  return;
}

add_action('init', function() {
  if (get_option('yombal_msg_table_v1') === '1') return;
  global $wpdb;
  $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}yombal_msg_blocked (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    message_excerpt varchar(500),
    reason varchar(100),
    blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
  ) " . $wpdb->get_charset_collate());
  update_option('yombal_msg_table_v1', '1');
});
function yombal_has_contact($msg) {
  $patterns = [
    '/[7][0-9]{8}/','/\\+?221[0-9]{8,9}/',
    '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}/',
    '/whatsapp/i','/instagram/i','/telegram/i',
    '/mon.num/i','/contactez.?moi/i','/appelez.?moi/i',
  ];
  foreach ($patterns as $p) { if (preg_match($p, $msg)) return true; }
  return false;
}
add_action('wp_ajax_wcfm_ajax_submit_message', function() {
  if (!isset($_POST['message'])) return;
  $msg = stripslashes($_POST['message']);
  if (yombal_has_contact($msg)) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix.'yombal_msg_blocked', [
      'user_id' => get_current_user_id(),
      'message_excerpt' => substr($msg, 0, 200),
      'reason' => 'contact_info',
    ]);
    wp_send_json_error(['message' => 'Les coordonnees personnelles ne sont pas autorisees. Toutes les transactions doivent rester sur Yombal.']);
    exit;
  }
}, 1);
add_action('wp_footer', function() {
  if (!function_exists('wcfm_is_vendor') || !wcfm_is_vendor()) return;
  $type = strtolower(get_user_meta(get_current_user_id(),'wcfm_vendor_tax',true));
  if (strpos($type,'tailleur')===false && strpos($type,'hybride')===false) return;
  echo '<script>
  document.addEventListener("DOMContentLoaded",function(){
    var addBtn=function(){
      var m=document.querySelector(".wcfm-message-area,.wcfm_messages_form,.wcfm-message-submit");
      if(!m||document.getElementById("yombal-create-order-btn"))return;
      var b=document.createElement("button");
      b.id="yombal-create-order-btn";b.type="button";b.textContent="Creer une commande";
      b.style.cssText="background:#C8963E;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;margin-left:8px;font-size:0.9rem;font-weight:600";
      b.onclick=function(){window.location.href="/store-manager/?wcfm_page=wcfm-orders&wcfm_order_action=create";};
      m.parentNode.insertBefore(b,m.nextSibling);
    };
    addBtn();
    new MutationObserver(addBtn).observe(document.body,{childList:true,subtree:true});
  });
  </script>';
}, 20);
