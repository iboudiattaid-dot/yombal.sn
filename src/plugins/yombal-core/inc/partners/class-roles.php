<?php

declare(strict_types=1);

namespace Yombal\Core\Partners;

if (! defined('ABSPATH')) {
    exit;
}

final class Roles {
    public const ROLE_TAILOR = 'yombal_tailor';
    public const ROLE_FABRIC_VENDOR = 'yombal_fabric_vendor';
    public const ROLE_HYBRID = 'yombal_hybrid_partner';
    public const ROLE_OPS = 'yombal_ops_manager';

    public const TYPE_TAILOR = 'tailor';
    public const TYPE_FABRIC_VENDOR = 'fabric_vendor';
    public const TYPE_HYBRID = 'hybrid';

    public static function boot(): void {
        add_action('admin_init', [self::class, 'ensure_caps']);
    }

    public static function activate(): void {
        add_role(self::ROLE_TAILOR, 'Yombal Tailor', self::partner_caps());
        add_role(self::ROLE_FABRIC_VENDOR, 'Yombal Fabric Vendor', self::partner_caps());
        add_role(self::ROLE_HYBRID, 'Yombal Hybrid Partner', self::partner_caps());
        add_role(self::ROLE_OPS, 'Yombal Ops Manager', self::ops_caps());

        self::ensure_caps();
    }

    public static function ensure_caps(): void {
        $admin = get_role('administrator');
        if ($admin) {
            foreach (array_keys(self::ops_caps()) as $cap) {
                $admin->add_cap($cap);
            }
        }

        $shop_manager = get_role('shop_manager');
        if ($shop_manager) {
            foreach (['yombal_view_ops', 'yombal_manage_couture_requests', 'yombal_manage_partners'] as $cap) {
                $shop_manager->add_cap($cap);
            }
        }
    }

    public static function detect_partner_type(int $user_id): string {
        $user = get_userdata($user_id);
        $roles = (array) ($user?->roles ?? []);

        if (in_array(self::ROLE_HYBRID, $roles, true)) {
            return self::TYPE_HYBRID;
        }

        if (in_array(self::ROLE_FABRIC_VENDOR, $roles, true)) {
            return self::TYPE_FABRIC_VENDOR;
        }

        if (in_array(self::ROLE_TAILOR, $roles, true)) {
            return self::TYPE_TAILOR;
        }

        return '';
    }

    private static function partner_caps(): array {
        return [
            'read' => true,
            'upload_files' => true,
            'edit_products' => true,
            'edit_published_products' => true,
            'publish_products' => true,
            'delete_products' => false,
            'yombal_manage_own_profile' => true,
            'yombal_manage_own_products' => true,
            'yombal_view_partner_dashboard' => true,
            'yombal_manage_own_couture_jobs' => true,
        ];
    }

    private static function ops_caps(): array {
        return [
            'read' => true,
            'yombal_view_ops' => true,
            'yombal_manage_partners' => true,
            'yombal_manage_couture_requests' => true,
            'yombal_manage_disputes' => true,
            'yombal_manage_reports' => true,
        ];
    }
}
