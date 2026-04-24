<?php

/*
   Plugin Name: PayTech
   Description: PayTech propose une plateforme sécurisée de paiement en ligne pour faciliter les transactions entre les professionnels et leurs clients.
   Version: 6.0.3
   Author: PayTech
   Author URI: https://paytech.sn
 */

  //ini_set('display_errors', 1);
  //          error_reporting(E_ALL);

$current_error_reporting = error_reporting();
error_reporting($current_error_reporting & ~E_DEPRECATED);

const ipn_path = "/paytech/v6.0.3/ipn";



if (!defined('ABSPATH')) {
    exit;
}

function wpstars_list_active_plugins() {

	$_plugins = [];
  if ( function_exists( 'get_sites' ) && class_exists( 'WP_Site_Query' ) ) {



    $plugins = get_plugins();

    // Network activated
    $active_plugins = get_site_option('active_sitewide_plugins');
    foreach($active_plugins as $active_path => $active_plugin) {

      $plugins[$active_path]['Sites'] = "A,";
    }

    // Per site activated
    $sites = get_sites();
    foreach ( $sites as $site ) {

      $active_plugins = get_blog_option($site->blog_id, 'active_plugins');
      foreach($active_plugins as $active_plugin) {

        $plugins[$active_plugin]['Sites'] .= $site->blog_id . ",";
      }
    }

    foreach($plugins as $plugin) {
    	$_plugins[] = $plugin['Name'];
    }

  }

  return $_plugins;
}





add_action('plugins_loaded', 'woocommerce_paytech_init', 0);
add_action( 'wp_enqueue_scripts', 'paytech_cdn' );



function paytech_cdn(){

    wp_register_style( 'PayTech', 'https://paytech.sn/cdn/paytech.min.css' );
    wp_enqueue_style('PayTech');

    wp_register_script( 'PayTech', 'https://paytech.sn/cdn/paytech.min.js', null, null, true );
    wp_enqueue_script('PayTech');
    $ver = '1.0.' . rand(1, 10000);
    wp_register_script( 'Paytech-Checkout',  plugins_url( '/assets/js/checkout.js' , __FILE__ ), null, $ver, true );
    wp_enqueue_script('Paytech-Checkout');

    $options = @get_option('woocommerce_paytech_settings', array());

    if(!@$options['open_mode'] || $options['open_mode'] === 'popup'){
        wp_register_script( 'Paytech-Open-Mode',  plugins_url( '/assets/js/open-mode-popup.js' , __FILE__ ), null, null, true );
        wp_enqueue_script('Paytech-Open-Mode');
    }
}

function woocommerce_paytech_init()
{


    if (!in_array('WooCommerce', wpstars_list_active_plugins()) && !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        die('WooCommerce not found');
    }

    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Paytech extends WC_Payment_Gateway
    {

        public $paytech_errors;
        public $id;
        public $salt;
        public $merchant_id;
        public $medthod_title;
        public $icon;
        public $has_fields;
        public $api_key;
        public $secret_key;
        public $env;
        public $paytech_host;
        public $posturl;
        public $submit_id;

        public function __construct()
        {
            $this->paytech_errors = new WP_Error();
            /**
             * IPN
             */


            //id de la passerelle
            $this->id = 'paytech';
            $this->salt = '';
            $this->merchant_id = '';
            $this->medthod_title = 'PAYTECH';
            $this->icon = apply_filters('woocommerce_paytech_icon', plugins_url('assets/images/paytech.png', __FILE__));
            $this->has_fields = false;
            //charger les champs pour paramètres de la passerelle.
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            //Mes parametres
            $this->api_key = $this->settings['api_key'];
            $this->secret_key = $this->settings['secret_key'];
            $this->env = $this->settings['env'];
            $this->paytech_host = 'https://paytech.sn/';
            $this->posturl = $this->paytech_host . 'api/payment/request-payment';
            $this->submit_id = 0;

            $this->checkPaymentResponse();


            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }


        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activer/Désactiver', 'paytech'),
                    'type' => 'checkbox',
                    'label' => __('Activer le module de paiement PAYTECH.', 'paytech'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Titre:', 'paytech'),
                    'type' => 'text',
                    'description' => __('Texte que verra le client lors du paiement de sa commande.', 'paytech'),
                    'default' => __('Paiement avec PayTech', 'paytech')),
                'description' => array(
                    'title' => __('Description:', 'paytech'),
                    'type' => 'textarea',
                    'description' => __('Description que verra le client lors du paiement de sa commande.', 'paytech'),
                    'default' => __('Payer via PayTech avec Visa, MasterCard, Orange Money, Free Money…', 'paytech', 'paytech')),

                'api_key' => array(
                    'title' => __("Clé de l'api", 'paytech'),
                    'type' => 'text',
                    'description' => __("Clé de l'api fournie par PAYTECH ")),
                'secret_key' => array(
                    'title' => __("Clé secrete de l'api", 'paytech'),
                    'type' => 'text',
                    'description' => __('Clé secrete fournie par PAYTECH .')),
                'env' => array(
                    'title' => __("Environnement", 'paytech'),
                    'description' => __('Votre envirionnement de travail TEST ou PRODUCTION.'),
                    'css' => 'padding:0%;',
                    'type' => 'select',
                    'options' => array('prod' => 'Production', 'test' => 'Test'),
                ),

                'status_after_payment' => array(
                    'title' => __("Statut après le paiement", 'paytech'),
                    'description' => __('Statut après le paiement'),
                    'css' => 'padding:0%;',
                    'type' => 'select',
                    'options' => array(
                        'processing' => 'En cours',
                        'completed' => 'Terminé',
                    ),
                    'default' => 'completed'
                ),
                'open_mode' => array(
                    'title' => __("Mode ouverture de la page de paiement", 'paytech'),
                    'description' => __('Mode ouverture de la page de paiement'),
                    'css' => 'padding:0%;',
                    'type' => 'select',
                    'options' => array(
                        'popup' => 'Popup',
                        'window' => 'Nouvelle Fenêtre',
                    ),
                    'default' => 'popup'
                ),

            );
        }


        public function admin_options()
        {
            echo '<h3>' . __('PAYTECH', 'paytech') . '</h3>';
            echo '<p>' . __('Paytech est une passerelle qui assure sécurité et simplicité pour vos transactions') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            //   wp_enqueue_script('paytech_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
        }

        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        protected function get_paytech_args($order, $order_id)
        {

            global $woocommerce;

            $txnid = $order->get_id() . '_' . date("ymds");

            $redirect_url = wc_get_checkout_url();

            $productinfo = "Commande: " . $order->get_id();

            $str = "{$this->merchant_id}|{$txnid}|{$order->get_total()}|{$productinfo}|{$order->get_billing_first_name()}|{$order->get_billing_email()}|||||||||||{$this->salt}";
            $hash = hash('sha512', $str);

            WC()->session->set('paytech_wc_hash_key', $hash);

            $items = $woocommerce->cart->get_cart();
            //  $paytech_items = array();
            $produit = "";
            foreach ($items as $item) {
                $produit = $produit . $item["data"]->post->post_title . " ";

            }


            $itemsId = [];
            foreach ($order->get_items() as $item_id => $item) {
                array_push($itemsId, $item_id);
            }

            $opt = @get_option('woocommerce_paytech_settings', array());

            //dame arguments
            $postfields = array(
                "item_name" => $produit,
                "item_price" => $order->get_total(),
                "currency" => get_woocommerce_currency(),
                "ref_command" => $order->get_id() . '_' . time(),
                "command_name" => "Paiement de " . $order->get_total() . " " . get_woocommerce_currency() . " pour article(s) achetés sur " . get_bloginfo("name"),
                "env" => $opt['env'],
                "success_url" => $redirect_url . '?_success=1',
                "ipn_url" => get_site_url(null, '', 'https') . ipn_path,
                "cancel_url" => $redirect_url . '?_cancel=1',
                "custom_field" => json_encode([
                    'order' => $order_id,
                    'hash' => $hash,
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number()
                ]));
            //fin dame arguments

            apply_filters('woocommerce_paytech_args', $postfields, $order);

            return $postfields;
        }


        function post($url, $data, $order_id, $header = [])
        {

            $strPostField = http_build_query($data);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $strPostField);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($header, [
                'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
                'Content-Length: ' . mb_strlen($strPostField)
            ]));

            $response = curl_exec($ch);


            $jsonResponse = json_decode($response, true);

            WC()->session->set('paytech_wc_order_id', $order_id);

            if (array_key_exists('token', $jsonResponse)) {
                $url = $this->paytech_host . 'payment/checkout/' . $jsonResponse['token']; /*. $this->getTheme()*/;
                return $url;


            } else {
                if (array_key_exists('error', $jsonResponse))
                    wc_add_notice($jsonResponse['error'][0] . $response, "error");
                else
                    if (array_key_exists('success') && $jsonResponse['success'] === -1)
                        wc_add_notice($jsonResponse['message'], "error");

                    else
                        wc_add_notice("Erreur inconnue" . $response, "error");
                return '';
            }


        }

        //fin mon post dame

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);


            return array(
                'result' => 'success',
                'redirect' => $this->post($this->posturl, $this->get_paytech_args($order, $order_id), $order_id, [
                    "API_KEY: " . $this->api_key,
                    "API_SECRET: " . $this->secret_key
                ])
            );
        }


        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }


        static function woocommerce_add_paytech_gateway($methods)
        {
            $methods[] = 'WC_Paytech';
            return $methods;
        }

        // Add settings link on plugin page
        static function woocommerce_add_paytech_settings_link($links)
        {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_paytech">Paramètres</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

        function getTheme()
        {
            $theme = json_decode('{"pageBackgroundRadianStart":"#0178bc","pageBackgroundRadianEnd":"#00bdda","pageTextPrimaryColor":"#333","paymentFormBackground":"#fff","navControlNextBackgroundRadianStart":"#608d93","navControlNextBackgroundRadianEnd":"#28314e","navControlCancelBackgroundRadianStar":"#28314e","navControlCancelBackgroundRadianEnd":"#608d93","navControlTextColor":"#fff","paymentListItemTextColor":"#555","paymentListItemSelectedBackground":"#eee","commingIconBackgroundRadianStart":"#0178bc","commingIconBackgroundRadianEnd":"#00bdda","commingIconTextColor":"#fff","formInputBackgroundColor":"#eff1f2","formInputBorderTopColor":"#e3e7eb","formInputBorderLeftColor":"#7c7c7c","totalIconBackgroundRadianStart":"#0178bc","totalIconBackgroundRadianEnd":"#00bdda","formLabelTextColor":"#292b2c","alertDialogTextColor":"#333","alertDialogConfirmButtonBackgroundColor":"#0178bc","alertDialogConfirmButtonTextColor":"#fff"}');
            // $theme->pageBackgroundRadianStart = $this->color  ;
            //  $theme->pageBackgroundRadianEnd = $this->color2 ;
            //  $theme64 = base64_encode(json_encode($theme));
            //  return $query = "?t=".$theme64 ;
        }

        private function checkPaymentResponse()
        {
            global $woocommerce;


            if (isset($_GET['_cancel']) && $_GET['_cancel'] === '1') {
                $message_type = 'error';
                $message = 'La paiement a été annulée';
                $wc_order_id = WC()->session->get('paytech_wc_order_id');
                $order = new WC_Order($wc_order_id);


                $order->update_status('cancelled');
                $order->add_order_note($message);

                $rdi_url = $order->get_cancel_order_url();

                $notification_message = array(
                    'message' => $message,
                    'message_type' => $message_type
                );

                if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                    $hash = WC()->session->get('paytech_wc_hash_key');
                    add_post_meta($wc_order_id, '_paytech_hash', $hash, true);
                }
                update_post_meta($wc_order_id, '_paytech_wc_message', $notification_message);

                WC()->session->__unset('paytech_wc_hash_key');
                WC()->session->__unset('paytech_wc_order_id');

                wp_redirect($rdi_url);
                die;
            } else if (isset($_GET['_success']) && $_GET['_success'] === '1') {
                $wc_order_id = WC()->session->get('paytech_wc_order_id');
                $order = new WC_Order($wc_order_id);

                // Ne confirmer que si le paiement a bien été validé via IPN.
                if ($wc_order_id && $order->get_id() && in_array($order->get_status(), ['processing', 'completed'])) {
                    $woocommerce->cart->empty_cart();
                    $rdi_url = $order->get_checkout_order_received_url();
                } else {
                    // IPN pas encore reçu ou paiement non validé — retour au panier.
                    $rdi_url = wc_get_cart_url();
                }

                WC()->session->__unset('paytech_wc_hash_key');
                WC()->session->__unset('paytech_wc_order_id');

                wp_redirect($rdi_url);
                die;
            }
        }

    }


    $plugin = plugin_basename(__FILE__);

    add_filter("plugin_action_links_$plugin", array('WC_Paytech', 'woocommerce_add_paytech_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_Paytech', 'woocommerce_add_paytech_gateway'));


    function pay_tech_ipn_confirm()
    {

        if ($_SERVER['REQUEST_URI'] === ipn_path) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);

            $options = @get_option('woocommerce_paytech_settings', array());
            if (isset($_POST['type_event'])) {
                $res = $_POST['type_event'];
                if ($res === 'sale_complete' && hash('sha256', $options['api_key']) === $_POST['api_key_sha256'] && hash('sha256', $options['secret_key']) === $_POST['api_secret_sha256']) {
                    global $woocommerce;

                    $newStatus = $options['status_after_payment'];

                    $custom = json_decode(stripslashes($_POST['custom_field']), true);// see stripslashes

                    $order_id = $custom['order_id'];
                    $hash = $custom['hash'];

                    $paymentMethod = $_POST['payment_method'];
                    $message = "Le paiement a été effectué avec succès sur PayTech via $paymentMethod";
                    $message_type = "success";

                    $order = new WC_Order($order_id);

                    if(in_array($order->get_status(), ['completed', 'processing'])){
                        die('status already ok: ' . $order->get_status());
                    }

                    $order->payment_complete();
                    $order->update_status($newStatus);
                    $order->add_order_note($message);

                    $notification_message = array(
                        'message' => $message,
                        'message_type' => $message_type
                    );

                    if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                        add_post_meta($order_id, '_paytech_hash', $hash, true);
                    }

                    update_post_meta($order_id, '_paytech_wc_message', $notification_message);

                    WC()->session->__unset('paytech_wc_hash_key');
                    WC()->session->__unset('paytech_wc_order_id');

                    try {
                        $mailer = WC()->mailer();
                        $emails = $mailer ? $mailer->get_emails() : [];

                        if (!empty($emails['WC_Email_New_Order']) && is_callable([$emails['WC_Email_New_Order'], 'trigger'])) {
                            $emails['WC_Email_New_Order']->trigger($order_id, $order);
                        }

                        if (!empty($emails['WC_Email_Customer_Processing_Order']) && is_callable([$emails['WC_Email_Customer_Processing_Order'], 'trigger'])) {
                            $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
                        }

                    } catch (Exception $e) {
                        wc_get_logger()->error('PayTech email error: ' . $e->getMessage(), ['source' => 'paytech']);
                    }
                    die('OK');
                }
                else{
                    die('KO 1');
                }
            }

            die('NO OK');
        }

    }

    add_action('init', 'pay_tech_ipn_confirm', 10, 0);
}
