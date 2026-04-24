<?php

declare(strict_types=1);

namespace Yombal\Core\Admin;

use Yombal\Core\Database\Installer;
use Yombal\Core\Migrations\Partner_Importer;
use Yombal\Core\Migrations\WCFM_Adapter;
use Yombal\Core\Partners\Roles;
use Yombal\Core\Admin\Readiness_Center;
use Yombal\Core\Admin\Journey_Lab;

if (! defined('ABSPATH')) {
    exit;
}

final class Menu {
    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register_menu']);
        Partner_Importer::boot();
        Page_Provisioner::boot();
    }

    public static function register_menu(): void {
        if (! current_user_can('yombal_view_ops') && ! current_user_can('manage_woocommerce')) {
            return;
        }

        add_menu_page(
            'Yombal Ops',
            'Yombal Ops',
            'read',
            'yombal-core-ops',
            [self::class, 'render_overview'],
            'dashicons-store',
            56
        );

        add_submenu_page('yombal-core-ops', 'Vue generale', 'Vue generale', 'read', 'yombal-core-ops', [self::class, 'render_overview']);
        add_submenu_page('yombal-core-ops', 'Partenaires', 'Partenaires', 'read', 'yombal-core-partners', [self::class, 'render_partners']);
        add_submenu_page('yombal-core-ops', 'Demandes couture', 'Demandes couture', 'read', 'yombal-core-couture-requests', [self::class, 'render_requests']);
        add_submenu_page('yombal-core-ops', 'Pages custom', 'Pages custom', 'read', 'yombal-core-pages', [self::class, 'render_pages']);
        add_submenu_page('yombal-core-ops', 'Bascule progressive', 'Bascule progressive', 'read', 'yombal-core-rollout', [self::class, 'render_rollout']);
        add_submenu_page('yombal-core-ops', 'Migration WCFM', 'Migration WCFM', 'read', 'yombal-core-migration', [self::class, 'render_migration']);
        add_submenu_page('yombal-core-ops', 'Preparation sortie WCFM', 'Preparation sortie WCFM', 'read', 'yombal-core-exit-plan', [Readiness_Center::class, 'render_exit_plan']);
        add_submenu_page('yombal-core-ops', 'Recette flux couture', 'Recette flux couture', 'read', 'yombal-core-qa', [Readiness_Center::class, 'render_qa_console']);
        add_submenu_page('yombal-core-ops', 'Journey Lab', 'Journey Lab', 'read', 'yombal-core-journey-lab', [Journey_Lab::class, 'render_page']);
    }

    public static function render_overview(): void {
        global $wpdb;

        $profiles = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Installer::table_name('yombal_partner_profiles'));
        $measurements = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Installer::table_name('yombal_mesures'));
        $requests = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Installer::table_name('yombal_couture_requests'));
        $pending_profiles = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Installer::table_name('yombal_partner_profiles') . ' WHERE profile_status = %s',
                'pending_review'
            )
        );
        $approved_requests = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Installer::table_name('yombal_couture_requests') . ' WHERE status = %s',
                'approved'
            )
        );

        echo '<div class="wrap"><h1>Yombal Ops</h1>';
        echo '<p>Socle custom de migration progressive hors WCFM.</p>';
        echo '<ul>';
        echo '<li>Profils partenaires custom: <strong>' . esc_html((string) $profiles) . '</strong></li>';
        echo '<li>Profils partenaires en attente: <strong>' . esc_html((string) $pending_profiles) . '</strong></li>';
        echo '<li>Profils de mesures: <strong>' . esc_html((string) $measurements) . '</strong></li>';
        echo '<li>Demandes couture: <strong>' . esc_html((string) $requests) . '</strong></li>';
        echo '<li>Demandes couture validees: <strong>' . esc_html((string) $approved_requests) . '</strong></li>';
        echo '<li>WCFM actif: <strong>' . (WCFM_Adapter::is_wcfm_active() ? 'oui' : 'non') . '</strong></li>';
        echo '</ul></div>';
    }

    public static function render_partners(): void {
        global $wpdb;

        $table = Installer::table_name('yombal_partner_profiles');
        $profiles = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 50", ARRAY_A);

        echo '<div class="wrap"><h1>Partenaires</h1>';
        echo '<p>Les profils WCFM historiques ne sont pas encore importes automatiquement. Cette vue affiche les profils deja migres vers le socle custom.</p>';

        if (! $profiles) {
            echo '<p>Aucun profil partenaire custom pour le moment.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>User ID</th><th>Nom</th><th>Type</th><th>Statut</th><th>Ville</th><th>Legacy</th><th>Actions</th></tr></thead><tbody>';
        foreach ($profiles as $profile) {
            $approve_url = wp_nonce_url(
                admin_url('admin-post.php?action=yombal_update_partner_profile_status&profile_id=' . (int) $profile['id'] . '&status=approved'),
                'yombal_update_partner_profile_status_' . (int) $profile['id'] . '_approved'
            );
            $reject_url = wp_nonce_url(
                admin_url('admin-post.php?action=yombal_update_partner_profile_status&profile_id=' . (int) $profile['id'] . '&status=rejected'),
                'yombal_update_partner_profile_status_' . (int) $profile['id'] . '_rejected'
            );
            $pending_url = wp_nonce_url(
                admin_url('admin-post.php?action=yombal_update_partner_profile_status&profile_id=' . (int) $profile['id'] . '&status=pending_review'),
                'yombal_update_partner_profile_status_' . (int) $profile['id'] . '_pending_review'
            );
            echo '<tr>';
            echo '<td>' . esc_html((string) $profile['user_id']) . '</td>';
            echo '<td>' . esc_html((string) $profile['store_name']) . '</td>';
            echo '<td>' . esc_html((string) $profile['partner_type']) . '</td>';
            echo '<td>' . esc_html((string) $profile['profile_status']) . '</td>';
            echo '<td>' . esc_html((string) $profile['city']) . '</td>';
            echo '<td>' . esc_html((string) $profile['legacy_vendor_type']) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($approve_url) . '">Valider</a> | ';
            echo '<a href="' . esc_url($pending_url) . '">Repasser en revue</a> | ';
            echo '<a href="' . esc_url($reject_url) . '">Rejeter</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function render_requests(): void {
        global $wpdb;

        $table = Installer::table_name('yombal_couture_requests');
        $requests = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50", ARRAY_A);

        echo '<div class="wrap"><h1>Demandes couture</h1>';
        echo '<p>Base persistante du futur flux tissu + couture.</p>';

        if (! $requests) {
            echo '<p>Aucune demande couture n\'a encore ete creee.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Client</th><th>Tailleur</th><th>Statut</th><th>Paiement</th><th>Expire le</th></tr></thead><tbody>';
        foreach ($requests as $request) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $request['id']) . '</td>';
            echo '<td>' . esc_html((string) $request['customer_id']) . '</td>';
            echo '<td>' . esc_html((string) $request['tailor_user_id']) . '</td>';
            echo '<td>' . esc_html((string) $request['status']) . '</td>';
            echo '<td>' . ((int) $request['payment_unlocked'] === 1 ? 'debloque' : 'bloque') . '</td>';
            echo '<td>' . esc_html((string) $request['expires_at']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function render_pages(): void {
        $statuses = Page_Provisioner::page_statuses();

        echo '<div class="wrap"><h1>Pages custom Yombal</h1>';
        echo '<p>Ce module cree uniquement les pages manquantes. Aucune page existante n est ecrasee.</p>';

        if (isset($_GET['created'], $_GET['existing'])) {
            echo '<div class="notice notice-success"><p>Provision termine. Pages creees: <strong>' . esc_html((string) $_GET['created']) . '</strong>, deja presentes: <strong>' . esc_html((string) $_GET['existing']) . '</strong>.</p></div>';
        }

        echo '<table class="widefat striped"><thead><tr><th>Slug</th><th>Titre</th><th>Shortcode</th><th>Etat</th><th>URL</th></tr></thead><tbody>';
        foreach ($statuses as $status) {
            $definition = $status['definition'];
            $page = $status['page'];

            echo '<tr>';
            echo '<td>' . esc_html((string) $definition['slug']) . '</td>';
            echo '<td>' . esc_html((string) $definition['title']) . '</td>';
            echo '<td><code>' . esc_html((string) $definition['shortcode']) . '</code></td>';
            echo '<td>' . esc_html($page ? 'Existe deja' : 'A creer') . '</td>';
            echo '<td>';
            if ($page) {
                echo '<a href="' . esc_url(get_permalink($page)) . '" target="_blank" rel="noreferrer">Voir la page</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:20px;">';
        wp_nonce_field('yombal_provision_core_pages');
        echo '<input type="hidden" name="action" value="yombal_provision_core_pages">';
        echo '<p><button type="submit" class="button button-primary">Creer les pages custom manquantes</button></p>';
        echo '</form>';
        echo '</div>';
    }

    public static function render_rollout(): void {
        $flags = Rollout_Controls::get();

        echo '<div class="wrap"><h1>Bascule progressive Yombal</h1>';
        echo '<p>Ces options permettent d introduire les ecrans custom sans suppression brutale des ecrans legacy.</p>';

        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success"><p>Configuration de bascule mise a jour.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('yombal_save_rollout_controls');
        echo '<input type="hidden" name="action" value="yombal_save_rollout_controls">';
        echo '<table class="form-table"><tbody>';

        self::render_rollout_flag(
            'account_menu_links',
            'Liens custom dans Mon compte',
            'Ajoute les entrees Yombal custom dans le compte client / partenaire.',
            ! empty($flags['account_menu_links'])
        );
        self::render_rollout_flag(
            'cart_couture_entry',
            'Entree couture depuis le panier',
            'Affiche le lien vers l etape intermediaire Tissu seul / Tissu + Couture.',
            ! empty($flags['cart_couture_entry'])
        );
        self::render_rollout_flag(
            'redirect_store_manager',
            'Rediriger store-manager',
            'Redirige les partenaires depuis la page WCFM store-manager vers le portail partenaire custom.',
            ! empty($flags['redirect_store_manager'])
        );
        self::render_rollout_flag(
            'redirect_vendor_registration',
            'Rediriger devenir-partenaire',
            'Redirige la page legacy de candidature partenaire vers la page custom Yombal.',
            ! empty($flags['redirect_vendor_registration'])
        );
        self::render_rollout_flag(
            'redirect_partner_dashboard',
            'Rediriger dashboard partenaire',
            'Redirige la page dashboard-partenaire vers l espace partenaire custom.',
            ! empty($flags['redirect_partner_dashboard'])
        );
        self::render_rollout_flag(
            'redirect_partner_entrypoints',
            'Rediriger devenir-tailleur / vendeur',
            'Redirige les entrees partenaires publiques vers le formulaire custom avec type preselectionne.',
            ! empty($flags['redirect_partner_entrypoints'])
        );
        self::render_rollout_flag(
            'redirect_store_manager_deep_links',
            'Rediriger deep links WCFM produits',
            'Redirige les ecrans WCFM products-manage / product-manage vers l onglet Produits du workspace custom.',
            ! empty($flags['redirect_store_manager_deep_links'])
        );

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Enregistrer la bascule</button></p>';
        echo '</form>';
        echo '</div>';
    }

    private static function render_rollout_flag(string $key, string $label, string $description, bool $enabled): void {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="rollout[' . esc_attr($key) . ']" value="1" ' . checked($enabled, true, false) . '> ' . esc_html($description) . '</label>';
        echo '</td>';
        echo '</tr>';
    }

    public static function render_migration(): void {
        $legacy_counts = [
            'wcfm_vendor' => count(get_users(['role' => 'wcfm_vendor', 'fields' => 'ids'])),
            Roles::ROLE_TAILOR => count(get_users(['role' => Roles::ROLE_TAILOR, 'fields' => 'ids'])),
            Roles::ROLE_FABRIC_VENDOR => count(get_users(['role' => Roles::ROLE_FABRIC_VENDOR, 'fields' => 'ids'])),
            Roles::ROLE_HYBRID => count(get_users(['role' => Roles::ROLE_HYBRID, 'fields' => 'ids'])),
        ];

        echo '<div class="wrap"><h1>Migration WCFM</h1>';
        if (isset($_GET['imported'], $_GET['updated'], $_GET['total'])) {
            echo '<div class="notice notice-success"><p>Import legacy termine. Total: <strong>' . esc_html((string) $_GET['total']) . '</strong>, importes: <strong>' . esc_html((string) $_GET['imported']) . '</strong>, mis a jour: <strong>' . esc_html((string) $_GET['updated']) . '</strong>.</p></div>';
        }
        echo '<p>Ce module ne desactive pas WCFM. Il sert a preparer la bascule progressive.</p>';
        echo '<ul>';
        echo '<li>WCFM actif: <strong>' . (WCFM_Adapter::is_wcfm_active() ? 'oui' : 'non') . '</strong></li>';
        echo '<li>Vendeurs legacy WCFM: <strong>' . esc_html((string) $legacy_counts['wcfm_vendor']) . '</strong></li>';
        echo '<li>Yombal Tailor: <strong>' . esc_html((string) $legacy_counts[Roles::ROLE_TAILOR]) . '</strong></li>';
        echo '<li>Yombal Fabric Vendor: <strong>' . esc_html((string) $legacy_counts[Roles::ROLE_FABRIC_VENDOR]) . '</strong></li>';
        echo '<li>Yombal Hybrid Partner: <strong>' . esc_html((string) $legacy_counts[Roles::ROLE_HYBRID]) . '</strong></li>';
        echo '</ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('yombal_import_legacy_partners');
        echo '<input type="hidden" name="action" value="yombal_import_legacy_partners">';
        echo '<p><button type="submit" class="button button-primary">Importer les vendeurs WCFM vers les profils Yombal</button></p>';
        echo '</form>';
        echo '<p>Etapes prevues: import des profils partenaires, remplacement du dashboard, remplacement de l\'edition produit, puis workflow couture natif.</p>';
        echo '</div>';
    }
}
