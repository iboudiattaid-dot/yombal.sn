<?php

declare(strict_types=1);

namespace Yombal\Core\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class Assets {
    public static function boot(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_action('admin_bar_menu', [self::class, 'cleanup_front_admin_bar'], 999);
    }

    public static function enqueue(): void {
        wp_register_style(
            'yombal-core-frontend',
            YOMBAL_CORE_URL . 'assets/css/frontend.css',
            [],
            YOMBAL_CORE_VERSION
        );

        wp_register_script(
            'yombal-core-frontend',
            YOMBAL_CORE_URL . 'assets/js/frontend.js',
            [],
            YOMBAL_CORE_VERSION,
            true
        );

        wp_enqueue_style('yombal-core-frontend');
        wp_enqueue_script('yombal-core-frontend');
    }

    public static function cleanup_front_admin_bar(\WP_Admin_Bar $admin_bar): void {
        if (is_admin()) {
            return;
        }

        foreach ((array) $admin_bar->get_nodes() as $node) {
            $title = strtolower(wp_strip_all_tags((string) ($node->title ?? '')));
            $href = strtolower((string) ($node->href ?? ''));

            if (str_contains($title, 'wcfm') || str_contains($href, 'wcfm') || str_contains($href, 'store-manager')) {
                $admin_bar->remove_node((string) $node->id);
            }
        }
    }
}
