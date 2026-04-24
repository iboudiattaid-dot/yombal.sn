<?php

declare(strict_types=1);

namespace Yombal\Core\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class Page_Provisioner {
    public static function boot(): void {
        add_action('admin_post_yombal_provision_core_pages', [self::class, 'handle_provision']);
    }

    public static function definitions(): array {
        return [
            [
                'slug' => 'espace-client-yombal',
                'title' => 'Espace client Yombal',
                'shortcode' => '[yombal_client_workspace]',
                'description' => 'Portail client Yombal.',
            ],
            [
                'slug' => 'espace-partenaire-yombal',
                'title' => 'Espace partenaire Yombal',
                'shortcode' => '[yombal_partner_workspace]',
                'description' => 'Portail partenaire custom.',
            ],
            [
                'slug' => 'partenaire-yombal',
                'title' => 'Profil partenaire Yombal',
                'shortcode' => '[yombal_public_partner_profile]',
                'description' => 'Profil public partenaire Yombal.',
            ],
            [
                'slug' => 'devenir-partenaire-yombal',
                'title' => 'Devenir partenaire Yombal',
                'shortcode' => '[yombal_partner_registration]',
                'description' => 'Inscription partenaire hors WCFM.',
            ],
            [
                'slug' => 'demande-couture-yombal',
                'title' => 'Demande couture Yombal',
                'shortcode' => '[yombal_couture_request_form]',
                'description' => 'Etape intermediaire tissu seul / tissu + couture.',
            ],
            [
                'slug' => 'notifications-yombal',
                'title' => 'Notifications Yombal',
                'shortcode' => '[yombal_notifications]',
                'description' => 'Centre de notifications internes.',
            ],
            [
                'slug' => 'messages-yombal',
                'title' => 'Messages Yombal',
                'shortcode' => '[yombal_messages]',
                'description' => 'Boite de messages simplifiee.',
            ],
            [
                'slug' => 'litiges-yombal',
                'title' => 'Aide et litiges Yombal',
                'shortcode' => '[yombal_support_center]',
                'description' => 'Centre d aide et suivi des demandes.',
            ],
            [
                'slug' => 'rejoindre-evenement-yombal',
                'title' => 'Rejoindre un evenement Yombal',
                'shortcode' => '[yombal_join_event]',
                'description' => 'Page publique d invitation pour les achats de groupe.',
            ],
            [
                'slug' => 'demandes-couture-couturier',
                'title' => 'Demandes couture couturier',
                'shortcode' => '[yombal_tailor_requests]',
                'description' => 'File de traitement cote couturier.',
            ],
        ];
    }

    public static function page_statuses(): array {
        $statuses = [];

        foreach (self::definitions() as $definition) {
            $page = get_page_by_path($definition['slug']);
            $statuses[] = [
                'definition' => $definition,
                'page' => $page instanceof \WP_Post ? $page : null,
            ];
        }

        return $statuses;
    }

    public static function ensure_core_pages(): void {
        foreach (self::definitions() as $definition) {
            $page = get_page_by_path($definition['slug']);
            if ($page instanceof \WP_Post) {
                if (trim((string) $page->post_content) === '') {
                    wp_update_post([
                        'ID' => $page->ID,
                        'post_content' => '<!-- wp:shortcode -->' . $definition['shortcode'] . '<!-- /wp:shortcode -->',
                    ]);
                }
                continue;
            }

            wp_insert_post([
                'post_title' => $definition['title'],
                'post_name' => $definition['slug'],
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_content' => '<!-- wp:shortcode -->' . $definition['shortcode'] . '<!-- /wp:shortcode -->',
            ]);
        }
    }

    public static function get_page_url(string $slug): string {
        $page = get_page_by_path($slug);
        if (! $page instanceof \WP_Post) {
            return '';
        }

        $url = get_permalink($page);

        return is_string($url) ? $url : '';
    }

    public static function handle_provision(): void {
        if (! current_user_can('yombal_manage_partners') && ! current_user_can('manage_woocommerce')) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('yombal_provision_core_pages');

        $created = 0;
        $existing = 0;

        foreach (self::definitions() as $definition) {
            $page = get_page_by_path($definition['slug']);
            if ($page instanceof \WP_Post) {
                $existing++;
                continue;
            }

            $result = wp_insert_post([
                'post_title' => $definition['title'],
                'post_name' => $definition['slug'],
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_content' => '<!-- wp:shortcode -->' . $definition['shortcode'] . '<!-- /wp:shortcode -->',
            ], true);

            if (! is_wp_error($result)) {
                $created++;
            }
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'yombal-core-pages',
            'created' => $created,
            'existing' => $existing,
        ], admin_url('admin.php')));
        exit;
    }
}
