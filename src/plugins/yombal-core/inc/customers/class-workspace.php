<?php

declare(strict_types=1);

namespace Yombal\Core\Customers;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Partners\Profile_Service;
use Yombal\Core\Support\Ticket_Center;
use Yombal\Core\UI\Dashboard_Shell;

if (! defined('ABSPATH')) {
    exit;
}

final class Workspace {
    public static function boot(): void {
        add_shortcode('yombal_client_workspace', [self::class, 'render_page']);
        add_action('admin_init', [self::class, 'ensure_page_exists']);
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            ob_start();
            ?>
            <div class="yombal-ui yombal-shell yombal-client-dashboard">
                <?php echo Public_Shell::render_identity_strip(); ?>
                <section class="yombal-hero">
                    <span class="yombal-eyebrow">Mon espace client</span>
                    <h1>Espace client Yombal</h1>
                    <p>Connectez-vous pour retrouver vos commandes, vos mesures, vos messages et l ensemble de vos suivis couture.</p>
                </section>
                <div class="yombal-card yombal-card--soft yombal-empty-state">
                    Vous devez etre connecte pour acceder a votre espace client.
                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(self::login_url(self::get_page_url() ?: home_url('/espace-client-yombal/'))); ?>">Se connecter</a>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        $user_id = get_current_user_id();
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';
        $tabs = self::tabs();

        if (! isset($tabs[$tab])) {
            $tab = 'overview';
        }

        $user = wp_get_current_user();

        return Dashboard_Shell::render_layout([
            'sidebar_title' => (string) ($user->display_name ?: 'Mon compte'),
            'sidebar_meta' => 'Retrouvez vos commandes, vos mesures et les informations utiles pour vos prochains achats.',
            'sidebar_items' => self::sidebar_items($tab),
            'content' => self::render_tab($tab, $user_id),
        ]);
    }

    public static function get_page_url(): string {
        return Page_Provisioner::get_page_url('espace-client-yombal');
    }

    private static function login_url(string $target): string {
        return add_query_arg('redirect_to', $target, home_url('/connexion/'));
    }

    public static function ensure_page_exists(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (get_page_by_path('espace-client-yombal') instanceof \WP_Post) {
            return;
        }

        wp_insert_post([
            'post_title' => 'Espace client Yombal',
            'post_name' => 'espace-client-yombal',
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => '<!-- wp:shortcode -->[yombal_client_workspace]<!-- /wp:shortcode -->',
        ]);
    }

    private static function render_tab(string $tab, int $user_id): string {
        return match ($tab) {
            'events' => Events::render_page(),
            'measurements' => Measurements::render_page(),
            'messages' => Message_Center::render_page(),
            'support' => Ticket_Center::render_page(),
            'notifications' => Notification_Center::render_page(),
            default => self::render_overview($user_id),
        };
    }

    private static function render_overview(int $user_id): string {
        $user = wp_get_current_user();
        $orders = self::customer_order_count($user_id);
        $measurements = count(Measurements::get_user_measurements($user_id));
        $notifications = Notification_Center::count_user_notifications($user_id, 'pending');
        $messages = Message_Center::count_unread_for_user($user_id);
        $support = Ticket_Center::count_open_for_user($user_id);
        $events = self::customer_event_count($user_id);
        $recent_orders = self::recent_orders($user_id);
        $completion = self::profile_completion($user);

        ob_start();
        ?>
        <div class="yombal-ui yombal-client-dashboard yombal-shell">
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Mon espace client</span>
                <h1>Bonjour, <?php echo esc_html((string) ($user->display_name ?: 'Client')); ?></h1>
                <div class="yombal-inline-meta">
                    <span><?php echo esc_html(wp_date('l j F Y', current_time('timestamp'))); ?></span>
                    <span>Compte Yombal</span>
                </div>
                <p>Suivez vos commandes, retrouvez vos mesures et preparez plus simplement vos prochains achats sur Yombal.</p>
                <div class="yombal-dashboard-progress">
                    <div class="yombal-dashboard-progress__header">
                        <strong>Profil complete a <?php echo esc_html((string) $completion); ?>%</strong>
                        <span><?php echo esc_html($measurements > 0 ? 'Mesures disponibles' : 'Profil a completer'); ?></span>
                    </div>
                    <div class="yombal-dashboard-progress__bar">
                        <span style="width:<?php echo esc_attr((string) $completion); ?>%"></span>
                    </div>
                    <p>Ajoutez vos mesures et completez vos coordonnees pour gagner du temps lors de vos prochaines commandes.</p>
                </div>
            </section>

            <?php
            echo Dashboard_Shell::render_metrics([
                ['value' => (string) $orders, 'label' => 'Commandes'],
                ['value' => (string) $measurements, 'label' => 'Profils de mesures'],
                ['value' => (string) $messages, 'label' => 'Messages'],
                ['value' => (string) $events, 'label' => 'Evenements'],
            ]);

            echo Dashboard_Shell::render_section(
                'Actions rapides',
                'Accedez directement aux sections les plus utiles de votre espace client.',
                Dashboard_Shell::render_action_cards([
                    [
                        'label' => 'Suivre mes commandes',
                        'description' => 'Voir vos commandes en cours et vos achats recents.',
                        'url' => self::orders_url(),
                        'tone' => 'accent',
                    ],
                    [
                        'label' => 'Gerer mes mesures',
                        'description' => 'Enregistrer ou mettre a jour vos profils de mesures.',
                        'url' => self::tab_url('measurements'),
                        'tone' => 'secondary',
                    ],
                    [
                        'label' => 'Mes evenements',
                        'description' => 'Creer un groupe et inviter d autres participants.',
                        'url' => self::tab_url('events'),
                        'tone' => 'secondary',
                    ],
                    [
                        'label' => 'Ouvrir mes messages',
                        'description' => 'Retrouver vos echanges avec les partenaires Yombal.',
                        'url' => self::tab_url('messages'),
                        'tone' => 'secondary',
                    ],
                    [
                        'label' => 'Aide et litiges',
                        'description' => 'Poser une question ou suivre une demande deja ouverte.',
                        'url' => self::tab_url('support'),
                        'tone' => 'secondary',
                    ],
                    [
                        'label' => 'Voir mes notifications',
                        'description' => 'Retrouver les informations et suivis importants.',
                        'url' => self::tab_url('notifications'),
                        'tone' => 'secondary',
                    ],
                    [
                        'label' => 'Completer mon compte',
                        'description' => 'Mettre a jour vos informations personnelles.',
                        'url' => self::account_details_url(),
                        'tone' => 'secondary',
                    ],
                    [
                        'label' => 'Devenir partenaire',
                        'description' => 'Ouvrir une boutique ou proposer vos services sur Yombal.',
                        'url' => Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire/'),
                        'tone' => 'secondary',
                    ],
                ]),
                'soft'
            );
            ?>

            <div class="yombal-grid yombal-grid--two">
                <?php
                ob_start();
                if ($recent_orders === []) {
                    echo '<div class="yombal-empty-state">Aucune commande recente pour le moment.</div>';
                } else {
                    echo '<ul class="yombal-list">';
                    foreach ($recent_orders as $order) {
                        echo '<li>';
                        echo '<strong>Commande #' . esc_html((string) $order['number']) . '</strong>';
                        echo '<div class="yombal-inline-meta">';
                        echo '<span>' . esc_html((string) $order['status']) . '</span>';
                        echo '<span>' . esc_html((string) $order['date']) . '</span>';
                        echo '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                echo Dashboard_Shell::render_section(
                    'Activite recente',
                    'Retrouvez vos dernieres commandes en un coup d oeil.',
                    (string) ob_get_clean()
                );

                ob_start();
                echo '<div class="yombal-stack">';
                echo '<div class="yombal-inline-meta"><span>Email</span><strong>' . esc_html((string) $user->user_email) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Mesures enregistrees</span><strong>' . esc_html((string) $measurements) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Evenements</span><strong>' . esc_html((string) $events) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Messages a lire</span><strong>' . esc_html((string) $messages) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Demandes ouvertes</span><strong>' . esc_html((string) $support) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Notifications a lire</span><strong>' . esc_html((string) $notifications) . '</strong></div>';
                echo '</div>';
                echo Dashboard_Shell::render_section(
                    'Mon compte',
                    'Gardez vos informations a jour pour rendre vos prochaines commandes plus fluides.',
                    (string) ob_get_clean(),
                    'soft'
                );
                ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function tabs(): array {
        return [
            'overview' => 'Mon espace',
            'events' => 'Mes evenements',
            'measurements' => 'Mes mesures',
            'messages' => 'Messages',
            'support' => 'Aide et litiges',
            'notifications' => 'Notifications',
        ];
    }

    private static function sidebar_items(string $active_tab): array {
        $items = [];
        foreach (self::tabs() as $key => $label) {
            $items[] = [
                'label' => $label,
                'url' => self::tab_url($key),
                'active' => $active_tab === $key,
            ];
        }

        $items[] = [
            'label' => 'Mes commandes',
            'url' => self::orders_url(),
            'active' => false,
        ];
        $items[] = [
            'label' => 'Mon profil',
            'url' => self::account_details_url(),
            'active' => false,
        ];
        $items[] = [
            'label' => 'Devenir partenaire',
            'url' => self::partner_application_url(),
            'active' => false,
        ];
        $items[] = [
            'label' => 'Deconnexion',
            'url' => wp_logout_url(home_url('/')),
            'active' => false,
            'modifier' => 'logout',
        ];

        return $items;
    }

    public static function tab_url(string $tab): string {
        return add_query_arg('tab', $tab, self::get_page_url() ?: home_url('/espace-client-yombal/'));
    }

    private static function orders_url(): string {
        return function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('orders')
            : home_url('/mon-compte/orders/');
    }

    private static function account_details_url(): string {
        return function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('edit-account')
            : home_url('/mon-compte/edit-account/');
    }

    private static function partner_application_url(): string {
        return Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire-yombal/');
    }

    private static function customer_order_count(int $user_id): int {
        if (! function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'return' => 'ids',
            'limit' => -1,
        ]);

        return is_array($orders) ? count($orders) : 0;
    }

    private static function recent_orders(int $user_id): array {
        if (! function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $rows = [];
        foreach ((array) $orders as $order) {
            if (! $order instanceof \WC_Order) {
                continue;
            }

            $rows[] = [
                'number' => $order->get_order_number(),
                'status' => wc_get_order_status_name($order->get_status()),
                'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y') : '',
            ];
        }

        return $rows;
    }

    private static function customer_event_count(int $user_id): int {
        return Events::count_for_user($user_id);
    }

    private static function profile_completion(\WP_User $user): int {
        $phone = (string) get_user_meta($user->ID, 'billing_phone', true);
        $first_name = (string) get_user_meta($user->ID, 'first_name', true);
        $last_name = (string) get_user_meta($user->ID, 'last_name', true);
        $measurements = Measurements::get_user_measurements($user->ID);

        $fields = [
            (string) $user->display_name,
            (string) $user->user_email,
            $first_name,
            $last_name,
            $phone,
            $measurements !== [] ? 'yes' : '',
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (trim($field) !== '') {
                $completed++;
            }
        }

        return (int) round(($completed / max(count($fields), 1)) * 100);
    }
}
