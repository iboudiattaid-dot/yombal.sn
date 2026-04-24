<?php

declare(strict_types=1);

namespace Yombal\Core\Orders;

use Yombal\Core\Workflows\Couture_Requests;

if (! defined('ABSPATH')) {
    exit;
}

final class Grouped_Payment {
    public static function boot(): void {
        add_action('woocommerce_cart_calculate_fees', [self::class, 'add_couture_fee']);
        add_action('woocommerce_review_order_before_payment', [self::class, 'render_checkout_summary']);
        add_action('woocommerce_checkout_create_order', [self::class, 'sync_order_context'], 30, 2);
        add_action('woocommerce_payment_complete', [self::class, 'handle_payment_complete']);
        add_action('woocommerce_order_status_processing', [self::class, 'handle_paid_status']);
        add_action('woocommerce_order_status_completed', [self::class, 'handle_paid_status']);
        add_action('woocommerce_thankyou', [self::class, 'clear_session_after_order'], 20);
        add_action('woocommerce_admin_order_data_after_order_details', [self::class, 'render_admin_context']);
    }

    public static function add_couture_fee(\WC_Cart $cart): void {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        $request = self::approved_request();
        if (! $request || $cart->is_empty()) {
            return;
        }

        $couture_price = (float) ($request['couture_price'] ?? 0);
        if ($couture_price <= 0) {
            return;
        }

        $tailor_name = self::tailor_display_name((int) $request['tailor_user_id']);
        $label = 'Confection sur mesure';
        if ($tailor_name !== '') {
            $label .= ' - ' . $tailor_name;
        }

        $cart->add_fee($label, $couture_price, false);
    }

    public static function render_checkout_summary(): void {
        $request = self::approved_request();
        if (! $request) {
            return;
        }

        $tailor_name = self::tailor_display_name((int) $request['tailor_user_id']);
        $couture_price = (float) ($request['couture_price'] ?? 0);
        ?>
        <div class="yombal-checkout-couture-summary" style="margin:0 0 20px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc;">
            <strong>Paiement tissu et confection</strong>
            <p style="margin:8px 0 0;">
                Couturier: <?php echo esc_html($tailor_name !== '' ? $tailor_name : '#' . (string) $request['tailor_user_id']); ?>
                |
                Demande #<?php echo esc_html((string) $request['id']); ?>
                |
                Confection: <?php echo wp_kses_post(wc_price($couture_price)); ?>
            </p>
            <?php if (! empty($request['model_reference'])) : ?>
                <p style="margin:8px 0 0;">Modele: <?php echo esc_html((string) $request['model_reference']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function sync_order_context(\WC_Order $order, array $data): void {
        $request = self::approved_request();
        if (! $request) {
            return;
        }

        $request_id = (int) $request['id'];
        $order_id = $order->get_id();
        $couture_price = (float) ($request['couture_price'] ?? 0);
        $tailor_user_id = (int) ($request['tailor_user_id'] ?? 0);

        $order->update_meta_data('_yombal_checkout_mode', 'fabric_plus_couture');
        $order->update_meta_data('_yombal_couture_price', $couture_price);
        $order->update_meta_data('_yombal_tailor_user_id', $tailor_user_id);
        $order->update_meta_data('_yombal_tailor_name', self::tailor_display_name($tailor_user_id));
        $order->update_meta_data('_yombal_grouped_transaction', 'yes');

        Couture_Requests::link_order($request_id, $order_id);
    }

    public static function handle_payment_complete(int $order_id): void {
        self::mark_order_as_paid($order_id);
    }

    public static function handle_paid_status(int $order_id): void {
        self::mark_order_as_paid($order_id);
    }

    public static function clear_session_after_order(int $order_id): void {
        if (! function_exists('WC') || ! WC()->session) {
            return;
        }

        $request_id = self::session_request_id();
        if ($request_id <= 0) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        if ((int) $order->get_meta('_yombal_couture_request_id') !== $request_id) {
            return;
        }

        WC()->session->__unset('yombal_couture_request_id');
        WC()->session->__unset('yombal_checkout_mode');
    }

    public static function render_admin_context(\WC_Order $order): void {
        $request_id = (int) $order->get_meta('_yombal_couture_request_id');
        if ($request_id <= 0) {
            return;
        }

        $couture_price = (float) $order->get_meta('_yombal_couture_price');
        $tailor_name = (string) $order->get_meta('_yombal_tailor_name');
        $status = (string) $order->get_meta('_yombal_couture_request_status');

        echo '<div class="order_data_column">';
        echo '<h4>Flux Yombal tissu + couture</h4>';
        echo '<p><strong>Demande couture:</strong> #' . esc_html((string) $request_id) . '</p>';
        echo '<p><strong>Statut demande:</strong> ' . esc_html(Couture_Requests::get_status_label($status)) . '</p>';
        echo '<p><strong>Couturier:</strong> ' . esc_html($tailor_name !== '' ? $tailor_name : '-') . '</p>';
        echo '<p><strong>Montant couture:</strong> ' . wp_kses_post(wc_price($couture_price)) . '</p>';
        echo '<p><strong>Transaction groupee:</strong> oui</p>';
        echo '</div>';
    }

    private static function approved_request(): ?array {
        $request_id = self::session_request_id();
        if ($request_id <= 0) {
            return null;
        }

        $request = Couture_Requests::get($request_id);
        if (! $request) {
            return null;
        }

        if ((string) $request['status'] !== Couture_Requests::STATUS_APPROVED || (int) $request['payment_unlocked'] !== 1) {
            return null;
        }

        if ((float) ($request['couture_price'] ?? 0) <= 0) {
            return null;
        }

        return $request;
    }

    private static function mark_order_as_paid(int $order_id): void {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $request_id = (int) $order->get_meta('_yombal_couture_request_id');
        if ($request_id <= 0) {
            return;
        }

        if ((string) $order->get_meta('_yombal_couture_payment_marked') === 'yes') {
            return;
        }

        if (Couture_Requests::mark_payment_completed($request_id, $order_id)) {
            $order->update_meta_data('_yombal_couture_request_status', Couture_Requests::STATUS_PAYMENT_COMPLETED);
            $order->update_meta_data('_yombal_couture_payment_marked', 'yes');
            $order->save();
        }
    }

    private static function session_request_id(): int {
        if (! function_exists('WC') || ! WC()->session) {
            return 0;
        }

        return (int) WC()->session->get('yombal_couture_request_id', 0);
    }

    private static function tailor_display_name(int $user_id): string {
        if ($user_id <= 0) {
            return '';
        }

        $user = get_userdata($user_id);
        if (! $user) {
            return '';
        }

        return (string) $user->display_name;
    }
}
