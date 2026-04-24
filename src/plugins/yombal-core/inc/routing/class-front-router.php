<?php

declare(strict_types=1);

namespace Yombal\Core\Routing;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Admin\Rollout_Controls;
use Yombal\Core\Customers\Workspace as Customer_Workspace;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Partners\Profile_Service;
use Yombal\Core\Support\Ticket_Center;

if (! defined('ABSPATH')) {
    exit;
}

final class Front_Router {
    public static function boot(): void {
        add_filter('woocommerce_account_menu_items', [self::class, 'inject_account_menu'], 60);
        add_filter('woocommerce_get_endpoint_url', [self::class, 'resolve_account_url'], 10, 2);
        add_action('woocommerce_before_account_navigation', [self::class, 'render_account_logout_button'], 5);
        add_action('woocommerce_before_cart', [self::class, 'render_cart_couture_entry']);
        add_action('template_redirect', [self::class, 'handle_progressive_redirects'], 5);
    }

    public static function inject_account_menu(array $items): array {
        if (! Rollout_Controls::is_enabled('account_menu_links')) {
            return $items;
        }

        $workspace_url = Page_Provisioner::get_page_url('espace-partenaire-yombal');
        $client_workspace_url = Customer_Workspace::get_page_url();
        $notifications_url = Page_Provisioner::get_page_url('notifications-yombal');
        $registration_url = Page_Provisioner::get_page_url('devenir-partenaire-yombal');
        $messages_url = Message_Center::get_page_url();
        $support_url = Ticket_Center::get_page_url();
        $is_logged_in = is_user_logged_in();
        $is_partner = $is_logged_in && Profile_Service::is_partner_user(get_current_user_id());

        $new_items = [];

        if ($is_partner && $workspace_url) {
            $new_items['yombal_partner_workspace'] = 'Mon espace partenaire';
        } elseif ($is_logged_in && $client_workspace_url) {
            $new_items['yombal_client_workspace'] = 'Mon espace';
        }

        if (isset($items['orders'])) {
            $new_items['orders'] = 'Mes commandes';
        }

        if ($messages_url && $is_logged_in) {
            $new_items['yombal_messages'] = 'Messages';
        }

        if ($support_url && $is_logged_in) {
            $new_items['yombal_support'] = 'Aide et litiges';
        }

        if (! $is_partner && $client_workspace_url) {
            $new_items['yombal_client_events'] = 'Mes evenements';
        }

        if ($notifications_url && $is_logged_in) {
            $new_items['yombal_notifications'] = 'Notifications';
        }

        if (isset($items['edit-account'])) {
            $new_items['edit-account'] = 'Mon profil';
        }

        if (! $is_partner && $registration_url) {
            $new_items['yombal_partner_apply'] = 'Devenir partenaire';
        }

        if (isset($items['customer-logout'])) {
            $new_items['customer-logout'] = 'Deconnexion';
        }

        return $new_items !== [] ? $new_items : $items;
    }

    public static function resolve_account_url(string $url, string $endpoint): string {
        return match ($endpoint) {
            'yombal_client_workspace' => Customer_Workspace::get_page_url() ?: $url,
            'yombal_client_events' => Customer_Workspace::tab_url('events'),
            'yombal_partner_workspace' => Page_Provisioner::get_page_url('espace-partenaire-yombal') ?: $url,
            'yombal_partner_apply' => Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: $url,
            'yombal_messages' => Message_Center::get_page_url() ?: $url,
            'yombal_support' => Ticket_Center::get_page_url() ?: $url,
            'yombal_notifications' => Page_Provisioner::get_page_url('notifications-yombal') ?: $url,
            default => $url,
        };
    }

    public static function render_cart_couture_entry(): void {
        if (! Rollout_Controls::is_enabled('cart_couture_entry')) {
            return;
        }

        if (! function_exists('WC') || ! WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $page_url = Page_Provisioner::get_page_url('demande-couture-yombal');
        if (! $page_url) {
            return;
        }

        echo '<div class="woocommerce-info yombal-cart-couture-entry">';
        echo 'Vous pouvez finaliser ce panier en mode <strong>Tissu seul</strong> ou <strong>Tissu + Couture</strong>. ';
        echo '<a href="' . esc_url($page_url) . '">Ouvrir l etape intermediaire Yombal</a>';
        echo '</div>';
    }

    public static function render_account_logout_button(): void {
        if (! is_user_logged_in() || ! is_account_page()) {
            return;
        }

        echo '<div class="yombal-account-topbar yombal-ui">';
        echo '<div class="yombal-actions">';
        echo '<a href="' . esc_url(wp_logout_url(home_url('/'))) . '" class="yombal-button yombal-button--secondary">Deconnexion</a>';
        echo '</div>';
        echo '</div>';
    }

    public static function handle_progressive_redirects(): void {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        self::redirect_legacy_public_paths();
        self::redirect_partner_entrypoints();

        if (Rollout_Controls::is_enabled('redirect_vendor_registration')) {
            self::redirect_legacy_vendor_registration();
        }

        if (Rollout_Controls::is_enabled('redirect_partner_dashboard')) {
            self::redirect_partner_dashboard();
        }

        self::redirect_client_dashboard();

        if (! is_user_logged_in()) {
            return;
        }

        if (Rollout_Controls::is_enabled('redirect_store_manager')) {
            self::redirect_legacy_store_manager();
        }

        if (Rollout_Controls::is_enabled('redirect_store_manager_deep_links')) {
            self::redirect_legacy_store_manager_deep_links();
        }
    }

    private static function redirect_legacy_store_manager(): void {
        if (! is_page('store-manager')) {
            return;
        }

        if (! Profile_Service::is_partner_user(get_current_user_id())) {
            return;
        }

        $target = Page_Provisioner::get_page_url('espace-partenaire-yombal');
        if (! $target) {
            return;
        }

        wp_safe_redirect($target);
        exit;
    }

    private static function redirect_legacy_vendor_registration(): void {
        if (! is_page('devenir-partenaire')) {
            return;
        }

        $target = Page_Provisioner::get_page_url('devenir-partenaire-yombal');
        if (! $target) {
            return;
        }

        wp_safe_redirect($target);
        exit;
    }

    private static function redirect_partner_dashboard(): void {
        if (! is_page('dashboard-partenaire')) {
            return;
        }

        $target = Page_Provisioner::get_page_url('espace-partenaire-yombal');
        if (! $target) {
            return;
        }

        wp_safe_redirect($target);
        exit;
    }

    private static function redirect_client_dashboard(): void {
        if (! is_user_logged_in() || ! function_exists('is_account_page') || ! is_account_page()) {
            return;
        }

        if (Profile_Service::is_partner_user(get_current_user_id())) {
            return;
        }

        $request_uri = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (is_string($request_uri) && str_contains(untrailingslashit($request_uri), '/mon-compte/mes-evenements')) {
            $target = Customer_Workspace::tab_url('events');
            if ($target) {
                wp_safe_redirect($target);
                exit;
            }
        }

        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('mes-evenements')) {
            $target = Customer_Workspace::tab_url('events');
            if ($target) {
                wp_safe_redirect($target);
                exit;
            }
        }

        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
            return;
        }

        $target = Customer_Workspace::get_page_url();
        if (! $target || untrailingslashit($target) === untrailingslashit(home_url(add_query_arg([], (string) ($_SERVER['REQUEST_URI'] ?? ''))))) {
            return;
        }

        wp_safe_redirect($target);
        exit;
    }

    private static function redirect_partner_entrypoints(): void {
        if (! Rollout_Controls::is_enabled('redirect_partner_entrypoints')) {
            return;
        }

        $target = Page_Provisioner::get_page_url('devenir-partenaire-yombal');
        if (! $target) {
            return;
        }

        if (is_page('devenir-tailleur')) {
            wp_safe_redirect(add_query_arg('partner_type', 'tailor', $target));
            exit;
        }

        if (is_page('devenir-vendeur-tissus')) {
            wp_safe_redirect(add_query_arg('partner_type', 'fabric_vendor', $target));
            exit;
        }

        if (is_page('vendor-register')) {
            wp_safe_redirect($target);
            exit;
        }

        if (is_page('vendor-registration')) {
            wp_safe_redirect($target);
            exit;
        }
    }

    private static function redirect_legacy_store_manager_deep_links(): void {
        $request_uri = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (! is_string($request_uri) || $request_uri === '') {
            return;
        }

        $workspace_url = Page_Provisioner::get_page_url('espace-partenaire-yombal');
        if (! $workspace_url) {
            return;
        }

        $map = [
            '/store-manager/products-manage' => 'products',
            '/store-manager/product-manage' => 'products',
        ];

        foreach ($map as $path => $tab) {
            if (str_contains(untrailingslashit($request_uri), $path)) {
                wp_safe_redirect(add_query_arg('tab', $tab, $workspace_url));
                exit;
            }
        }
    }

    private static function redirect_legacy_public_paths(): void {
        $request_uri = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (! is_string($request_uri) || $request_uri === '') {
            return;
        }

        $normalized = untrailingslashit($request_uri);
        $request_args = [];
        foreach ((array) $_GET as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $request_args[sanitize_key((string) $key)] = sanitize_text_field((string) $value);
        }

        if (in_array($normalized, ['/connexion-2', '/connexion-3'], true)) {
            wp_safe_redirect(home_url('/connexion/'));
            exit;
        }

        if ($normalized === '/modeles') {
            wp_safe_redirect(home_url('/catalogue-modeles/'));
            exit;
        }

        if (in_array($normalized, ['/mes-mesures', '/mesures', '/index.php/mesures'], true)) {
            $target = Customer_Workspace::tab_url('measurements');
            if ($target) {
                wp_safe_redirect($target);
                exit;
            }
        }

        if (! in_array($normalized, ['/commander', '/checkout'], true)) {
            if ($normalized === '/rejoindre-evenement') {
                $target = Page_Provisioner::get_page_url('rejoindre-evenement-yombal');
                if ($target) {
                    if (isset($_GET['code']) && is_string($_GET['code'])) {
                        $target = add_query_arg('code', sanitize_text_field($_GET['code']), $target);
                    }
                    wp_safe_redirect($target);
                    exit;
                }
            }

            if (in_array($normalized, ['/mes-messages', '/messages'], true) || str_contains($normalized, '/store-manager/messages')) {
                $message_args = [];
                if (! empty($request_args['with'])) {
                    $message_args['recipient'] = (int) $request_args['with'];
                }
                foreach (['recipient', 'product_id', 'order_id', 'couture_request_id', 'thread'] as $key) {
                    if (! empty($request_args[$key])) {
                        $message_args[$key] = (int) $request_args[$key];
                    }
                }

                $target = Message_Center::get_page_url();
                if ($message_args !== []) {
                    $target = add_query_arg($message_args, $target);
                }

                wp_safe_redirect($target);
                exit;
            }

            if (
                in_array($normalized, ['/aide-litige', '/support-tickets', '/view-support-ticket', '/litige'], true)
                || str_contains($normalized, '/view-support-ticket')
                || str_contains($normalized, '/store-manager/support')
                || str_contains($normalized, '/support-tickets')
            ) {
                $support_args = [];
                foreach (['ticket', 'order_id', 'partner_id', 'product_id'] as $key) {
                    if (! empty($request_args[$key])) {
                        $support_args[$key] = (int) $request_args[$key];
                    }
                }

                $target = Ticket_Center::get_page_url();
                if ($support_args !== []) {
                    $target = add_query_arg($support_args, $target);
                }

                wp_safe_redirect($target);
                exit;
            }

            return;
        }

        $target = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/commande/');
        wp_safe_redirect($target);
        exit;
    }
}
