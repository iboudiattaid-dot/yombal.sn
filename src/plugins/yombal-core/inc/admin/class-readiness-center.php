<?php

declare(strict_types=1);

namespace Yombal\Core\Admin;

use Yombal\Core\Database\Installer;
use Yombal\Core\Migrations\WCFM_Adapter;
use Yombal\Core\Partners\Roles;
use Yombal\Core\Workflows\Couture_Requests;

if (! defined('ABSPATH')) {
    exit;
}

final class Readiness_Center {
    public static function boot(): void {
        add_action('admin_post_yombal_run_expiry_sweep', [self::class, 'handle_expiry_sweep']);
    }

    public static function render_exit_plan(): void {
        echo '<div class="wrap"><h1>Preparation sortie WCFM</h1>';
        echo '<p>Ce tableau montre ce qui est deja couvert par le custom, ce qui reste partiel, et ce qui bloque encore une desactivation controlee de WCFM.</p>';

        echo '<h2>Matrice de couverture</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Bloc</th><th>Etat</th><th>Custom</th><th>Dependance WCFM restante</th></tr></thead><tbody>';
        foreach (self::feature_matrix() as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['feature']) . '</td>';
            echo '<td>' . esc_html($row['status']) . '</td>';
            echo '<td>' . esc_html($row['custom']) . '</td>';
            echo '<td>' . esc_html($row['dependency']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Dependances WCFM detectees</h2>';
        echo '<ul>';
        foreach (self::wcfm_dependency_rows() as $row) {
            echo '<li>' . esc_html($row) . '</li>';
        }
        echo '</ul>';

        echo '<h2>Go / No-Go desactivation</h2>';
        $checklist = self::disable_checklist();
        echo '<table class="widefat striped"><thead><tr><th>Controle</th><th>Etat</th><th>Preuve</th></tr></thead><tbody>';
        foreach ($checklist as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item['label']) . '</td>';
            echo '<td>' . esc_html($item['status']) . '</td>';
            echo '<td>' . esc_html($item['proof']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Plan de bascule</h2>';
        echo '<ol>';
        echo '<li>Conserver WCFM actif tant que les points marques "Partiel" ou "Non couvert" ne sont pas traites.</li>';
        echo '<li>Activer uniquement les redirections custom deja prouvees.</li>';
        echo '<li>Recetter les demandes couture en live avec comptes reels.</li>';
        echo '<li>Neutraliser les entrees legacy partner-side, puis desactiver WCFM module par module.</li>';
        echo '</ol>';
        echo '</div>';
    }

    public static function render_qa_console(): void {
        global $wpdb;

        $status_counts = Couture_Requests::get_status_counts();
        $events_table = Installer::table_name('yombal_couture_request_events');
        $events = $wpdb->get_results("SELECT * FROM {$events_table} ORDER BY created_at DESC LIMIT 20", ARRAY_A);
        $grouped_orders = self::count_grouped_orders();

        echo '<div class="wrap"><h1>Recette flux couture</h1>';
        echo '<p>Console de verification pour le workflow tissu + couture et sa migration hors WCFM.</p>';

        if (isset($_GET['swept'])) {
            echo '<div class="notice notice-success"><p>Sweep des expirations execute. Demandes expirees traitees: <strong>' . esc_html((string) $_GET['swept']) . '</strong>.</p></div>';
        }

        echo '<h2>Scenarios couverts par les donnees</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Scenario</th><th>Etat</th><th>Preuve</th></tr></thead><tbody>';
        foreach (self::qa_scenarios($status_counts, $grouped_orders) as $scenario) {
            echo '<tr>';
            echo '<td>' . esc_html($scenario['label']) . '</td>';
            echo '<td>' . esc_html($scenario['status']) . '</td>';
            echo '<td>' . esc_html($scenario['proof']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Sweep manuel des expirations</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('yombal_run_expiry_sweep');
        echo '<input type="hidden" name="action" value="yombal_run_expiry_sweep">';
        echo '<p><button type="submit" class="button button-primary">Executer le sweep maintenant</button></p>';
        echo '</form>';

        echo '<h2>Evenements couture recents</h2>';
        if (! $events) {
            echo '<p>Aucun evenement couture enregistre pour le moment.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Demande</th><th>Acteur</th><th>Evenement</th><th>Payload</th></tr></thead><tbody>';
            foreach ($events as $event) {
                echo '<tr>';
                echo '<td>' . esc_html((string) $event['created_at']) . '</td>';
                echo '<td>#' . esc_html((string) $event['request_id']) . '</td>';
                echo '<td>' . esc_html((string) ($event['actor_user_id'] ?: '-')) . '</td>';
                echo '<td>' . esc_html((string) $event['event_type']) . '</td>';
                echo '<td><code>' . esc_html((string) $event['payload']) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public static function handle_expiry_sweep(): void {
        if (! current_user_can('yombal_manage_partners') && ! current_user_can('manage_woocommerce')) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('yombal_run_expiry_sweep');

        $swept = Couture_Requests::sweep_expired_requests();
        wp_safe_redirect(add_query_arg([
            'page' => 'yombal-core-qa',
            'swept' => $swept,
        ], admin_url('admin.php')));
        exit;
    }

    private static function feature_matrix(): array {
        return [
            [
                'feature' => 'Inscription partenaire',
                'status' => 'Couverte',
                'custom' => 'Shortcode yombal_partner_registration + profil custom + moderation admin',
                'dependency' => 'Aucune pour le front principal',
            ],
            [
                'feature' => 'Dashboard partenaire',
                'status' => 'Couverte',
                'custom' => 'Workspace custom + tabs overview/products/notifications/profile',
                'dependency' => 'Compteurs commandes/messages encore remontes de tables WCFM',
            ],
            [
                'feature' => 'Edition produit partenaire',
                'status' => 'Couverte',
                'custom' => 'Shortcode yombal_partner_products + sauvegarde WooCommerce',
                'dependency' => 'Aucune pour le MVP partner-side',
            ],
            [
                'feature' => 'Workflow tissu + couture',
                'status' => 'Couverte',
                'custom' => 'Demande persistante + validation tailleur + paiement groupe',
                'dependency' => 'Recette live complete encore requise',
            ],
            [
                'feature' => 'Expiration 24h',
                'status' => 'Couverte',
                'custom' => 'Single event + sweep de secours',
                'dependency' => 'Aucune',
            ],
            [
                'feature' => 'Notifications',
                'status' => 'Couverte',
                'custom' => 'Table yombal_notifications + vue client',
                'dependency' => 'Pas de transport email/SMS custom branche ici',
            ],
            [
                'feature' => 'Tickets / litiges',
                'status' => 'Partielle',
                'custom' => 'Microcopy front + notifications + help bloc',
                'dependency' => 'Flux de ticket structure encore legacy / WCFM',
            ],
            [
                'feature' => 'Payouts / commissions avances',
                'status' => 'Non couverte',
                'custom' => 'Aucune reprise specifique dans ce lot',
                'dependency' => 'WCFM reste source',
            ],
        ];
    }

    private static function wcfm_dependency_rows(): array {
        $rows = [];
        $plugins = [
            'WCFM core' => 'wc-frontend-manager/wc_frontend_manager.php',
            'WCFM Ultimate' => 'wc-frontend-manager-ultimate/wcfm-ultimate.php',
            'WCFM Marketplace' => 'wc-multivendor-marketplace/wc-multivendor-marketplace.php',
            'WCFM Membership' => 'wc-multivendor-membership/wc-multivendor-membership.php',
        ];

        foreach ($plugins as $label => $plugin) {
            $rows[] = $label . ': ' . (self::is_plugin_active($plugin) ? 'actif' : 'inactif');
        }

        global $wpdb;
        foreach ([
            'wcfm_marketplace_orders' => WCFM_Adapter::get_marketplace_order_table(),
            'wcfm_messages' => WCFM_Adapter::get_messages_table(),
            'wcfm_vendor_ratings' => WCFM_Adapter::get_vendor_ratings_table(),
        ] as $label => $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                $rows[] = $label . ': table absente';
                continue;
            }

            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $rows[] = $label . ': ' . $count . ' lignes';
        }

        return $rows;
    }

    private static function disable_checklist(): array {
        global $wpdb;

        $legacy_vendors = count(get_users(['role' => 'wcfm_vendor', 'fields' => 'ids']));
        $custom_profiles = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Installer::table_name('yombal_partner_profiles'));
        $approved_requests = (int) ($wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Installer::table_name('yombal_couture_requests') . ' WHERE status = %s',
            Couture_Requests::STATUS_APPROVED
        )) ?: 0);

        return [
            [
                'label' => 'Profils partners importes',
                'status' => $custom_profiles >= $legacy_vendors && $legacy_vendors > 0 ? 'OK' : 'A completer',
                'proof' => $custom_profiles . ' profils custom / ' . $legacy_vendors . ' vendeurs WCFM',
            ],
            [
                'label' => 'Redirections custom activees',
                'status' => Rollout_Controls::is_enabled('redirect_store_manager') ? 'OK' : 'A completer',
                'proof' => 'redirect_store_manager=' . (Rollout_Controls::is_enabled('redirect_store_manager') ? '1' : '0'),
            ],
            [
                'label' => 'Demande couture validee sur donnees reelles',
                'status' => $approved_requests > 0 ? 'OK' : 'A completer',
                'proof' => $approved_requests . ' demandes approuvees',
            ],
            [
                'label' => 'Tickets / litiges hors WCFM',
                'status' => 'Bloquant',
                'proof' => 'Couverture encore partielle',
            ],
            [
                'label' => 'Payouts / commissions hors WCFM',
                'status' => 'Bloquant',
                'proof' => 'Non couverts dans yombal-core',
            ],
        ];
    }

    private static function qa_scenarios(array $status_counts, int $grouped_orders): array {
        return [
            [
                'label' => 'Demande creee',
                'status' => (($status_counts['total'] ?? 0) > 0) ? 'Vu en donnees' : 'A tester',
                'proof' => ($status_counts['total'] ?? 0) . ' demandes',
            ],
            [
                'label' => 'Validation tailleur',
                'status' => (($status_counts[Couture_Requests::STATUS_APPROVED] ?? 0) > 0) ? 'Vu en donnees' : 'A tester',
                'proof' => ($status_counts[Couture_Requests::STATUS_APPROVED] ?? 0) . ' approuvees',
            ],
            [
                'label' => 'Tissu insuffisant',
                'status' => (($status_counts[Couture_Requests::STATUS_NEEDS_MORE_FABRIC] ?? 0) > 0) ? 'Vu en donnees' : 'A tester',
                'proof' => ($status_counts[Couture_Requests::STATUS_NEEDS_MORE_FABRIC] ?? 0) . ' cas',
            ],
            [
                'label' => 'Expiration 24h',
                'status' => (($status_counts[Couture_Requests::STATUS_EXPIRED] ?? 0) > 0) ? 'Vu en donnees' : 'A tester',
                'proof' => ($status_counts[Couture_Requests::STATUS_EXPIRED] ?? 0) . ' cas',
            ],
            [
                'label' => 'Paiement groupe',
                'status' => $grouped_orders > 0 ? 'Vu en donnees' : 'A tester',
                'proof' => $grouped_orders . ' commandes groupees',
            ],
        ];
    }

    private static function count_grouped_orders(): int {
        global $wpdb;

        $order_meta = $wpdb->prefix . 'postmeta';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$order_meta} WHERE meta_key = %s AND meta_value = %s",
                '_yombal_grouped_transaction',
                'yes'
            )
        );
    }

    private static function is_plugin_active(string $plugin_file): bool {
        $active = (array) get_option('active_plugins', []);

        return in_array($plugin_file, $active, true);
    }
}
