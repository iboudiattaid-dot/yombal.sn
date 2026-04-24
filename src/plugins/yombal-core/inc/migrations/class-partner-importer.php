<?php

declare(strict_types=1);

namespace Yombal\Core\Migrations;

use Yombal\Core\Database\Installer;
use Yombal\Core\Partners\Roles;

if (! defined('ABSPATH')) {
    exit;
}

final class Partner_Importer {
    public static function boot(): void {
        add_action('admin_post_yombal_import_legacy_partners', [self::class, 'handle_import']);
    }

    public static function import_legacy_profiles(): array {
        global $wpdb;

        $users = get_users([
            'role' => 'wcfm_vendor',
            'fields' => ['ID', 'display_name'],
        ]);

        $table = Installer::table_name('yombal_partner_profiles');
        $imported = 0;
        $updated = 0;

        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            $legacy_type = WCFM_Adapter::get_legacy_partner_type($user_id);
            self::assign_partner_role($user_id, $legacy_type ?: Roles::TYPE_TAILOR);
            $row = [
                'user_id' => $user_id,
                'partner_type' => $legacy_type ?: Roles::TYPE_TAILOR,
                'profile_status' => 'legacy_imported',
                'display_name' => (string) $user->display_name,
                'store_name' => WCFM_Adapter::get_store_name($user_id),
                'city' => (string) get_user_meta($user_id, 'billing_city', true),
                'phone' => (string) get_user_meta($user_id, 'billing_phone', true),
                'specialties' => wp_json_encode((array) get_user_meta($user_id, 'yombal_specialites', true)),
                'materials' => wp_json_encode((array) get_user_meta($user_id, 'yombal_matieres', true)),
                'legacy_vendor_type' => (string) get_user_meta($user_id, 'wcfm_vendor_tax', true),
            ];

            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id)
            );

            if ($existing) {
                $wpdb->update($table, $row, ['user_id' => $user_id]);
                $updated++;
                continue;
            }

            $wpdb->insert($table, $row);
            $imported++;
        }

        return [
            'total' => count($users),
            'imported' => $imported,
            'updated' => $updated,
        ];
    }

    public static function handle_import(): void {
        if (! current_user_can('yombal_manage_partners') && ! current_user_can('manage_woocommerce')) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('yombal_import_legacy_partners');

        $result = self::import_legacy_profiles();
        $redirect = add_query_arg(
            [
                'page' => 'yombal-core-migration',
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'total' => $result['total'],
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    private static function assign_partner_role(int $user_id, string $partner_type): void {
        $user = new \WP_User($user_id);
        $role = match ($partner_type) {
            Roles::TYPE_TAILOR => Roles::ROLE_TAILOR,
            Roles::TYPE_FABRIC_VENDOR => Roles::ROLE_FABRIC_VENDOR,
            Roles::TYPE_HYBRID => Roles::ROLE_HYBRID,
            default => '',
        };

        if ($role === '' || in_array($role, (array) $user->roles, true)) {
            return;
        }

        $user->add_role($role);
    }
}
