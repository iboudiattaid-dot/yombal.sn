<?php

declare(strict_types=1);

namespace Yombal\Core\Migrations;

use Yombal\Core\Partners\Roles;

if (! defined('ABSPATH')) {
    exit;
}

final class WCFM_Adapter {
    public static function is_wcfm_active(): bool {
        return class_exists('WCFM') || function_exists('wcfm_is_vendor');
    }

    public static function is_legacy_vendor(int $user_id): bool {
        if ($user_id <= 0) {
            return false;
        }

        return user_can($user_id, 'wcfm_vendor') || in_array('wcfm_vendor', (array) get_userdata($user_id)?->roles, true);
    }

    public static function get_legacy_partner_type(int $user_id): string {
        $legacy = (string) get_user_meta($user_id, 'wcfm_vendor_tax', true);
        $legacy = strtolower(trim($legacy));

        if ($legacy === '') {
            return '';
        }

        if (str_contains($legacy, 'hybride')) {
            return Roles::TYPE_HYBRID;
        }

        if (str_contains($legacy, 'tissu')) {
            return Roles::TYPE_FABRIC_VENDOR;
        }

        return Roles::TYPE_TAILOR;
    }

    public static function get_store_name(int $user_id): string {
        $store_name = (string) get_user_meta($user_id, 'wcfm_store_name', true);

        if ($store_name !== '') {
            return $store_name;
        }

        return (string) get_userdata($user_id)?->display_name;
    }

    public static function get_marketplace_order_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'wcfm_marketplace_orders';
    }

    public static function get_messages_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'wcfm_messages';
    }

    public static function get_vendor_ratings_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'wcfm_marketplace_vendor_ratings';
    }
}
