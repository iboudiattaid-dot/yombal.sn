<?php

declare(strict_types=1);

namespace Yombal\Core\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class Rollout_Controls {
    private const OPTION_KEY = 'yombal_core_rollout';

    public static function boot(): void {
        add_action('admin_post_yombal_save_rollout_controls', [self::class, 'handle_save']);
    }

    public static function defaults(): array {
        return [
            'account_menu_links' => 1,
            'cart_couture_entry' => 1,
            'redirect_store_manager' => 0,
            'redirect_vendor_registration' => 1,
            'redirect_partner_dashboard' => 1,
            'redirect_partner_entrypoints' => 1,
            'redirect_store_manager_deep_links' => 0,
        ];
    }

    public static function get(): array {
        $values = get_option(self::OPTION_KEY, []);
        if (! is_array($values)) {
            $values = [];
        }

        return wp_parse_args($values, self::defaults());
    }

    public static function is_enabled(string $flag): bool {
        $values = self::get();

        return ! empty($values[$flag]);
    }

    public static function handle_save(): void {
        if (! current_user_can('yombal_manage_partners') && ! current_user_can('manage_woocommerce')) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('yombal_save_rollout_controls');

        $submitted = isset($_POST['rollout']) && is_array($_POST['rollout']) ? $_POST['rollout'] : [];
        $values = [];

        foreach (array_keys(self::defaults()) as $flag) {
            $values[$flag] = ! empty($submitted[$flag]) ? 1 : 0;
        }

        update_option(self::OPTION_KEY, $values, false);

        wp_safe_redirect(add_query_arg([
            'page' => 'yombal-core-rollout',
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }
}
