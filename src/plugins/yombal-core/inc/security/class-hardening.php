<?php

declare(strict_types=1);

namespace Yombal\Core\Security;

if (! defined('ABSPATH')) {
    exit;
}

final class Hardening {
    public static function boot(): void {
        add_action('admin_menu', [self::class, 'remove_sensitive_menus'], 999);
        add_action('admin_init', [self::class, 'block_sensitive_editors']);
        add_action('admin_notices', [self::class, 'render_notices']);
    }

    public static function remove_sensitive_menus(): void {
        remove_submenu_page('themes.php', 'theme-editor.php');
        remove_submenu_page('plugins.php', 'plugin-editor.php');
    }

    public static function block_sensitive_editors(): void {
        global $pagenow;

        if (! in_array((string) $pagenow, ['theme-editor.php', 'plugin-editor.php'], true)) {
            return;
        }

        wp_safe_redirect(admin_url());
        exit;
    }

    public static function render_notices(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $warnings = [];

        if (self::is_plugin_active('wp-file-manager/file_folder_manager.php')) {
            $warnings[] = 'WP File Manager est actif en production. Risque eleve: desactivation recommandee hors usage exceptionnel.';
        }

        if (! defined('DISALLOW_FILE_EDIT') || ! DISALLOW_FILE_EDIT) {
            $warnings[] = 'DISALLOW_FILE_EDIT n est pas force par la configuration. Les ecrans d edition WordPress sont bloques par yombal-core, mais le durcissement serveur reste recommande.';
        }

        if (self::is_plugin_active('code-snippets/code-snippets.php')) {
            $warnings[] = 'Code Snippets est actif. Verifier que les snippets legacy ne dupliquent pas la logique de yombal-core.';
        }

        foreach ($warnings as $warning) {
            echo '<div class="notice notice-warning"><p>' . esc_html($warning) . '</p></div>';
        }
    }

    private static function is_plugin_active(string $plugin_file): bool {
        $active = (array) get_option('active_plugins', []);

        return in_array($plugin_file, $active, true);
    }
}
