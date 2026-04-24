<?php

declare(strict_types=1);

namespace Yombal\Core\Orders;

use Yombal\Core\Workflows\Couture_Requests;

if (! defined('ABSPATH')) {
    exit;
}

final class Checkout_Guard {
    public static function boot(): void {
        add_action('woocommerce_check_cart_items', [self::class, 'validate_checkout_gate']);
        add_action('woocommerce_checkout_create_order', [self::class, 'attach_request_to_order'], 20, 2);
    }

    public static function validate_checkout_gate(): void {
        $request_id = self::session_request_id();
        if ($request_id <= 0) {
            return;
        }

        $request = Couture_Requests::get($request_id);
        if (! $request) {
            return;
        }

        if ($request['status'] !== Couture_Requests::STATUS_APPROVED || (int) $request['payment_unlocked'] !== 1) {
            wc_add_notice(
                'Le paiement sera disponible des que votre couturier aura confirme votre demande.',
                'error'
            );
            return;
        }

        if ((float) ($request['couture_price'] ?? 0) <= 0) {
            wc_add_notice(
                'Le montant de la confection n est pas encore disponible. Le paiement sera ouvert des que cette information sera confirmee.',
                'error'
            );
        }
    }

    public static function attach_request_to_order(\WC_Order $order, array $data): void {
        $request_id = self::session_request_id();
        if ($request_id <= 0) {
            return;
        }

        $request = Couture_Requests::get($request_id);
        if (! $request) {
            return;
        }

        $order->update_meta_data('_yombal_couture_request_id', $request_id);
        $order->update_meta_data('_yombal_couture_request_status', (string) $request['status']);
    }

    private static function session_request_id(): int {
        if (! function_exists('WC') || ! WC()->session) {
            return 0;
        }

        return (int) WC()->session->get('yombal_couture_request_id', 0);
    }
}
