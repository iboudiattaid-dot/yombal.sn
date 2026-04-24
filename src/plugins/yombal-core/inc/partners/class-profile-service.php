<?php

declare(strict_types=1);

namespace Yombal\Core\Partners;

use Yombal\Core\Database\Installer;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Support\Ticket_Center;
use Yombal\Core\UI\Dashboard_Shell;

if (! defined('ABSPATH')) {
    exit;
}

final class Profile_Service {
    private const PUBLIC_STATUSES = ['approved', 'legacy', 'legacy_imported'];
    private const WORKSPACE_STATUSES = ['approved', 'legacy', 'legacy_imported'];

    public static function boot(): void {
        add_shortcode('yombal_partner_dashboard', [self::class, 'render_partner_dashboard']);
    }

    public static function is_partner_user(int $user_id): bool {
        if ($user_id <= 0) {
            return false;
        }

        $type = Roles::detect_partner_type($user_id);
        if ($type !== '') {
            return true;
        }

        $profile = self::get_profile($user_id);

        return is_array($profile) && (string) ($profile['profile_status'] ?? '') !== 'rejected';
    }

    public static function public_statuses(): array {
        return self::PUBLIC_STATUSES;
    }

    public static function has_public_visibility(int $user_id): bool {
        $profile = self::get_profile($user_id);
        if (! is_array($profile)) {
            return false;
        }

        return in_array((string) ($profile['profile_status'] ?? ''), self::PUBLIC_STATUSES, true);
    }

    public static function has_workspace_access(int $user_id): bool {
        $profile = self::get_profile($user_id);
        if (! is_array($profile)) {
            return false;
        }

        return in_array((string) ($profile['profile_status'] ?? ''), self::WORKSPACE_STATUSES, true);
    }

    public static function access_state(int $user_id): string {
        if ($user_id <= 0) {
            return 'none';
        }

        $profile = self::get_profile($user_id);
        $type = Roles::detect_partner_type($user_id);
        if (! is_array($profile) && $type === '') {
            return 'none';
        }

        $status = (string) ($profile['profile_status'] ?? '');
        if (in_array($status, self::WORKSPACE_STATUSES, true)) {
            return 'active';
        }

        return match ($status) {
            'pending_review', '' => 'pending_review',
            'rejected' => 'rejected',
            default => 'pending_review',
        };
    }

    public static function get_profile(int $user_id): ?array {
        global $wpdb;

        if ($user_id <= 0) {
            return null;
        }

        $table = Installer::table_name('yombal_partner_profiles');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id),
            ARRAY_A
        );

        if ($row) {
            return $row;
        }

        $user = get_userdata($user_id);
        if (! $user) {
            return null;
        }

        $partner_type = Roles::detect_partner_type($user_id);
        if ($partner_type === '') {
            return null;
        }

        return [
            'user_id' => $user_id,
            'partner_type' => $partner_type,
            'profile_status' => 'pending_review',
            'display_name' => (string) $user->display_name,
            'store_name' => (string) $user->display_name,
            'city' => '',
            'phone' => '',
            'specialties' => '',
            'materials' => '',
            'biography' => '',
            'legacy_vendor_type' => null,
        ];
    }

    public static function render_partner_dashboard(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour acceder a votre espace partenaire.</div></div>';
        }

        $user_id = get_current_user_id();
        $profile = self::get_profile($user_id);

        if (! $profile) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Aucun profil partenaire n a ete trouve pour ce compte.</div></div>';
        }

        $products = self::count_products($user_id);
        $requests = self::count_couture_requests($user_id);
        $orders = self::count_orders($user_id);
        $messages = Message_Center::count_unread_for_user($user_id);
        $notifications = Notification_Center::count_user_notifications($user_id, 'pending');
        $support = Ticket_Center::count_open_for_user($user_id);
        $recent_orders = self::recent_orders($user_id);
        $dashboard_url = get_permalink() ?: home_url('/');
        $new_product_url = add_query_arg('new_product', '1', $dashboard_url);
        $products_url = add_query_arg('tab', 'products', $dashboard_url);
        $messages_url = add_query_arg('tab', 'messages', $dashboard_url);
        $support_url = add_query_arg('tab', 'support', $dashboard_url);
        $notifications_url = add_query_arg('tab', 'notifications', $dashboard_url);
        $tailor_requests_url = add_query_arg('tab', 'tailor-requests', $dashboard_url);
        $profile_url = add_query_arg('tab', 'profile', $dashboard_url);
        $partner_type = (string) ($profile['partner_type'] ?? '');
        $display_name = (string) ($profile['display_name'] ?? wp_get_current_user()->display_name ?? 'Partenaire');
        $completion = self::profile_completion($profile);

        ob_start();
        ?>
        <div class="yombal-ui yombal-partner-dashboard yombal-shell">
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Mon espace partenaire</span>
                <h1>Bonjour, <?php echo esc_html($display_name); ?></h1>
                <div class="yombal-inline-meta">
                    <span><?php echo esc_html(wp_date('l j F Y', current_time('timestamp'))); ?></span>
                    <span>Activite: <strong><?php echo esc_html(self::partner_type_label((string) ($profile['partner_type'] ?? ''))); ?></strong></span>
                </div>
                <p>Suivez votre activite, mettez votre profil a jour et accedez rapidement aux prochaines actions utiles pour votre boutique ou votre atelier.</p>
                <div class="yombal-dashboard-progress">
                    <div class="yombal-dashboard-progress__header">
                        <strong>Profil complete a <?php echo esc_html((string) $completion); ?>%</strong>
                        <span><?php echo esc_html(self::profile_status_label((string) ($profile['profile_status'] ?? 'pending_review'))); ?></span>
                    </div>
                    <div class="yombal-dashboard-progress__bar">
                        <span style="width:<?php echo esc_attr((string) $completion); ?>%"></span>
                    </div>
                    <p>Ajoutez vos informations de contact, votre presentation et vos specialites pour inspirer confiance aux clients.</p>
                </div>
            </section>

            <?php
            echo Dashboard_Shell::render_metrics([
                ['value' => (string) $products, 'label' => 'Produits en ligne'],
                ['value' => (string) $orders, 'label' => 'Commandes'],
                ['value' => (string) $messages, 'label' => 'Messages non lus'],
                ['value' => (string) $requests, 'label' => in_array($partner_type, [Roles::TYPE_TAILOR, Roles::TYPE_HYBRID], true) ? 'Demandes clients' : 'Demandes couture'],
            ]);
            ?>

            <?php
            $actions = [
                [
                    'label' => 'Ajouter un produit',
                    'description' => 'Mettre une nouvelle creation ou un nouveau tissu en ligne.',
                    'url' => $new_product_url,
                    'tone' => 'accent',
                ],
                [
                    'label' => 'Gerer mes produits',
                    'description' => 'Modifier les prix, statuts et informations de votre catalogue.',
                    'url' => $products_url,
                    'tone' => 'secondary',
                ],
                [
                    'label' => 'Completer mon profil',
                    'description' => 'Mettre a jour votre presentation, votre ville et vos specialites.',
                    'url' => $profile_url,
                    'tone' => 'secondary',
                ],
                [
                    'label' => 'Voir mes messages',
                    'description' => 'Repondre aux clients et garder vos echanges sur Yombal.',
                    'url' => $messages_url,
                    'tone' => 'secondary',
                ],
                [
                    'label' => 'Aide et demandes',
                    'description' => 'Suivre les demandes d aide et les situations a clarifier.',
                    'url' => $support_url,
                    'tone' => 'secondary',
                ],
                [
                    'label' => 'Voir mes notifications',
                    'description' => 'Retrouver les dernieres demandes et les messages importants.',
                    'url' => $notifications_url,
                    'tone' => 'secondary',
                ],
            ];

            if (in_array($partner_type, [Roles::TYPE_TAILOR, Roles::TYPE_HYBRID], true)) {
                $actions[] = [
                    'label' => 'Traiter les demandes clients',
                    'description' => 'Consulter les demandes couture en attente et y repondre rapidement.',
                    'url' => $tailor_requests_url,
                    'tone' => 'secondary',
                ];
            }

            echo Dashboard_Shell::render_section(
                'Actions rapides',
                'Accedez rapidement aux principales actions pour gerer votre activite sur Yombal.',
                Dashboard_Shell::render_action_cards($actions),
                'soft'
            );
            ?>

            <div class="yombal-grid yombal-grid--two">
                <?php
                ob_start();
                if (! $recent_orders) {
                    echo '<div class="yombal-empty-state">Aucune commande recente.</div>';
                } else {
                    echo '<ul class="yombal-list">';
                    foreach ($recent_orders as $order) {
                        echo '<li>';
                        echo '<strong>Commande #' . esc_html((string) $order['order_id']) . '</strong>';
                        echo '<div class="yombal-inline-meta">';
                        echo '<span>' . esc_html((string) $order['order_status']) . '</span>';
                        echo '<span>' . esc_html((string) $order['modified']) . '</span>';
                        echo '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                $orders_content = (string) ob_get_clean();
                echo Dashboard_Shell::render_section(
                    'Activite recente',
                    'Suivez vos commandes les plus recentes en un coup d oeil.',
                    $orders_content
                );

                ob_start();
                echo '<div class="yombal-stack">';
                echo '<div class="yombal-inline-meta"><span>Statut du compte</span><strong>' . esc_html(self::profile_status_label((string) ($profile['profile_status'] ?? 'pending_review'))) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Ville</span><strong>' . esc_html((string) ($profile['city'] ?? 'A preciser')) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Telephone</span><strong>' . esc_html((string) ($profile['phone'] ?? 'A preciser')) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Specialites</span><strong>' . esc_html(self::compact_list((string) ($profile['specialties'] ?? ''))) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Demandes d aide ouvertes</span><strong>' . esc_html((string) $support) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Notifications</span><strong>' . esc_html((string) $notifications) . '</strong></div>';
                echo '</div>';
                $profile_content = (string) ob_get_clean();
                echo Dashboard_Shell::render_section(
                    'Profil et assistance',
                    'Gardez un profil complet pour rassurer les clients et faciliter les echanges.',
                    $profile_content,
                    'soft'
                );
                ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function count_products(int $user_id): int {
        $query = new \WP_Query([
            'post_type' => 'product',
            'author' => $user_id,
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return (int) $query->found_posts;
    }

    private static function count_couture_requests(int $user_id): int {
        global $wpdb;

        $table = Installer::table_name('yombal_couture_requests');

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE tailor_user_id = %d", $user_id)
        );
    }

    private static function count_orders(int $user_id): int {
        return Partner_Stats::count_orders($user_id);
    }
    private static function recent_orders(int $user_id): array {
        return Partner_Stats::recent_orders($user_id, 5);
    }

    private static function partner_type_label(string $type): string {
        return match ($type) {
            Roles::TYPE_TAILOR => 'Couturier',
            Roles::TYPE_FABRIC_VENDOR => 'Vendeur de tissus',
            Roles::TYPE_HYBRID => 'Partenaire hybride',
            default => 'A preciser',
        };
    }

    private static function profile_status_label(string $status): string {
        return match ($status) {
            'approved' => 'Valide',
            'rejected' => 'Non valide',
            'legacy_imported' => 'A completer',
            'legacy' => 'A completer',
            'pending_review' => 'En verification',
            default => 'En attente',
        };
    }

    private static function profile_completion(array $profile): int {
        $fields = [
            (string) ($profile['display_name'] ?? ''),
            (string) ($profile['store_name'] ?? ''),
            (string) ($profile['partner_type'] ?? ''),
            (string) ($profile['city'] ?? ''),
            (string) ($profile['phone'] ?? ''),
            self::compact_list((string) ($profile['specialties'] ?? '')),
            self::compact_list((string) ($profile['materials'] ?? '')),
            (string) ($profile['biography'] ?? ''),
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (trim($field) !== '') {
                $completed++;
            }
        }

        return (int) round(($completed / max(count($fields), 1)) * 100);
    }

    private static function compact_list(string $value): string {
        $decoded = json_decode($value, true);
        if (is_array($decoded) && $decoded !== []) {
            return implode(', ', array_slice(array_map('strval', $decoded), 0, 3));
        }

        return $value !== '' ? $value : 'A preciser';
    }
}
