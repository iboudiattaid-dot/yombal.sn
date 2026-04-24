<?php

declare(strict_types=1);

namespace Yombal\Core\Admin;

use Yombal\Core\Customers\Events;
use Yombal\Core\Database\Installer;
use Yombal\Core\Journeys\Fixtures;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Partners\Public_Pages;
use Yombal\Core\Partners\Roles;
use Yombal\Core\Support\Ticket_Center;
use Yombal\Core\Workflows\Couture_Requests;

if (! defined('ABSPATH')) {
    exit;
}

final class Journey_Lab {
    private const REPORT_OPTION = 'yombal_journey_lab_fixtures';
    // Fixture password read from constant or auto-generated — never hardcoded.
    // Define YOMBAL_FIXTURE_PASSWORD in wp-config.php for a stable test password.

    private const USER_FIXTURES = [
        'client' => [
            'login' => 'ytest_client',
            'email' => 'ytest.client@yombal.sn',
            'display_name' => 'Client Yombal Test',
            'role' => 'customer',
            'partner_type' => '',
            'profile_status' => '',
            'store_name' => '',
            'city' => 'Dakar',
            'phone' => '+221700000101',
        ],
        'tailor' => [
            'login' => 'ytest_tailor',
            'email' => 'ytest.tailor@yombal.sn',
            'display_name' => 'Tailleur Yombal Test',
            'role' => Roles::ROLE_TAILOR,
            'partner_type' => Roles::TYPE_TAILOR,
            'profile_status' => 'approved',
            'store_name' => 'Atelier Test Tailleur',
            'city' => 'Dakar',
            'phone' => '+221700000201',
        ],
        'fabric_vendor' => [
            'login' => 'ytest_fabric_vendor',
            'email' => 'ytest.fabric@yombal.sn',
            'display_name' => 'Tissus Yombal Test',
            'role' => Roles::ROLE_FABRIC_VENDOR,
            'partner_type' => Roles::TYPE_FABRIC_VENDOR,
            'profile_status' => 'approved',
            'store_name' => 'Maison Test Tissus',
            'city' => 'Thiès',
            'phone' => '+221700000301',
        ],
        'pending_partner' => [
            'login' => 'ytest_pending_partner',
            'email' => 'ytest.pending@yombal.sn',
            'display_name' => 'Partenaire En Revue Test',
            'role' => Roles::ROLE_TAILOR,
            'partner_type' => Roles::TYPE_TAILOR,
            'profile_status' => 'pending_review',
            'store_name' => 'Atelier En Revue Test',
            'city' => 'Saint-Louis',
            'phone' => '+221700000401',
        ],
    ];

    public static function boot(): void {
        add_action('admin_post_yombal_seed_journey_lab', [self::class, 'handle_seed']);
        add_action('admin_post_yombal_export_journey_report', [self::class, 'handle_export_report']);
    }

    public static function render_page(): void {
        self::require_ops_access();

        $report = self::fixture_report();
        $matrix = self::load_matrix();
        $scenario_count = count((array) ($matrix['scenarios'] ?? []));

        echo '<div class="wrap"><h1>Journey Lab</h1>';
        echo '<p>Seed idempotent des fixtures client/partenaire/litiges et export JSON admin-only pour la recette automatisee.</p>';

        if (isset($_GET['journey_seeded'])) {
            echo '<div class="notice notice-success"><p>Fixtures Journey Lab semees ou reparees.</p></div>';
        }

        echo '<div class="card" style="max-width: 980px; padding: 16px;">';
        echo '<h2>Actions</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('yombal_seed_journey_lab');
        echo '<input type="hidden" name="action" value="yombal_seed_journey_lab">';
        echo '<p><button type="submit" class="button button-primary">Semer / reparer les fixtures</button></p>';
        echo '</form>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin-post.php?action=yombal_export_journey_report')) . '" target="_blank" rel="noreferrer">Exporter le rapport JSON</a></p>';
        echo '</div>';

        echo '<h2>Comptes de test</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Role</th><th>Login</th><th>Mot de passe</th><th>Etat</th></tr></thead><tbody>';
        foreach (self::USER_FIXTURES as $key => $definition) {
            $user = get_user_by('login', $definition['login']);
            echo '<tr>';
            echo '<td>' . esc_html($key) . '</td>';
            echo '<td>' . esc_html($definition['login']) . '</td>';
            echo '<td><code>' . esc_html(self::fixture_password()) . '</code></td>';
            echo '<td>' . esc_html($user ? 'Disponible' : 'A creer') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Source de verite des parcours</h2>';
        echo '<p>Scenarios charges: <strong>' . esc_html((string) $scenario_count) . '</strong></p>';
        echo '<ul>';
        echo '<li><code>' . esc_html(str_replace(ABSPATH, '', self::matrix_json_path())) . '</code></li>';
        echo '<li><code>' . esc_html(str_replace(ABSPATH, '', self::matrix_markdown_path())) . '</code></li>';
        echo '</ul>';

        echo '<h2>Fixtures disponibles</h2>';
        echo '<pre style="max-height:420px; overflow:auto;">' . esc_html((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        echo '</div>';
    }

    public static function handle_seed(): void {
        self::require_ops_access();
        check_admin_referer('yombal_seed_journey_lab');

        self::seed_or_repair();

        wp_safe_redirect(add_query_arg('journey_seeded', '1', admin_url('admin.php?page=yombal-core-journey-lab')));
        exit;
    }

    public static function handle_export_report(): void {
        self::require_ops_access();

        wp_send_json(self::fixture_report());
    }

    public static function fixture_report(): array {
        $stored = get_option(self::REPORT_OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $users = [];
        foreach (self::USER_FIXTURES as $key => $definition) {
            $user = get_user_by('login', $definition['login']);
            if (! $user instanceof \WP_User) {
                continue;
            }

            $users[$key] = [
                'id' => (int) $user->ID,
                'login' => $definition['login'],
                'password' => self::fixture_password(),
                'email' => (string) $user->user_email,
                'display_name' => (string) $user->display_name,
                'fixture_key' => Fixtures::fixture_key_for_user((int) $user->ID),
                'public_profile_url' => in_array($key, ['tailor', 'fabric_vendor', 'pending_partner'], true)
                    ? Public_Pages::get_profile_url((int) $user->ID)
                    : '',
            ];
        }

        $products = [];
        foreach ((array) ($stored['products'] ?? []) as $key => $product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0 || get_post_status($product_id) === false) {
                continue;
            }

            $products[$key] = [
                'id' => $product_id,
                'status' => (string) get_post_status($product_id),
                'url' => (string) get_permalink($product_id),
                'title' => (string) get_the_title($product_id),
                'fixture_key' => Fixtures::fixture_key_for_post($product_id),
            ];
        }

        $tickets = [];
        foreach ((array) ($stored['tickets'] ?? []) as $key => $ticket_id) {
            $ticket = self::ticket_row((int) $ticket_id);
            if (! $ticket) {
                continue;
            }

            $tickets[$key] = [
                'id' => (int) $ticket['id'],
                'status' => (string) $ticket['status'],
                'customer_id' => (int) ($ticket['customer_id'] ?? 0),
                'partner_user_id' => (int) ($ticket['partner_user_id'] ?? 0),
                'subject' => (string) $ticket['subject'],
                'client_url' => add_query_arg('ticket', (int) $ticket['id'], home_url('/litiges-yombal/')),
                'partner_url' => add_query_arg('ticket', (int) $ticket['id'], home_url('/espace-partenaire-yombal/?tab=support')),
            ];
        }

        $requests = [];
        foreach ((array) ($stored['couture_requests'] ?? []) as $key => $request_id) {
            $request = Couture_Requests::get((int) $request_id);
            if (! is_array($request)) {
                continue;
            }

            $requests[$key] = [
                'id' => (int) $request['id'],
                'status' => (string) $request['status'],
                'customer_id' => (int) ($request['customer_id'] ?? 0),
                'tailor_user_id' => (int) ($request['tailor_user_id'] ?? 0),
                'payment_unlocked' => (int) ($request['payment_unlocked'] ?? 0),
                'customer_url' => add_query_arg('request_id', (int) $request['id'], home_url('/demande-couture-yombal/')),
                'partner_url' => home_url('/espace-partenaire-yombal/?tab=tailor-requests'),
            ];
        }

        $event = [];
        if (! empty($stored['event']['code'])) {
            $event = [
                'code' => (string) $stored['event']['code'],
                'join_url' => add_query_arg('code', (string) $stored['event']['code'], Page_Provisioner::get_page_url('rejoindre-evenement-yombal') ?: home_url('/rejoindre-evenement-yombal/')),
            ];
        }

        return [
            'generated_at' => gmdate('c'),
            'users' => $users,
            'products' => $products,
            'tickets' => $tickets,
            'couture_requests' => $requests,
            'event' => $event,
            'report_url' => admin_url('admin-post.php?action=yombal_export_journey_report'),
            'matrix' => [
                'json' => self::matrix_json_path(),
                'markdown' => self::matrix_markdown_path(),
            ],
        ];
    }

    public static function fixture_password(): string {
        if (defined('YOMBAL_FIXTURE_PASSWORD') && YOMBAL_FIXTURE_PASSWORD !== '') {
            return YOMBAL_FIXTURE_PASSWORD;
        }
        return wp_generate_password(16, true, false);
    }

    public static function matrix_json_path(): string {
        return YOMBAL_CORE_DIR . 'resources/journeys/journey-matrix.json';
    }

    public static function matrix_markdown_path(): string {
        return YOMBAL_CORE_DIR . 'resources/journeys/journey-matrix.md';
    }

    private static function load_matrix(): array {
        $path = self::matrix_json_path();
        if (! file_exists($path)) {
            return ['scenarios' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['scenarios' => []];
    }

    private static function seed_or_repair(): void {
        Events::maybe_create_tables();

        $users = [];
        foreach (self::USER_FIXTURES as $key => $definition) {
            $user_id = self::upsert_user($key, $definition);
            $users[$key] = $user_id;

            if ($definition['partner_type'] !== '') {
                self::upsert_partner_profile($user_id, $definition);
                self::attach_partner_media($user_id, $definition['partner_type'], $key);
            }
        }

        $products = [
            'tailor_public' => self::upsert_product($users['tailor'], 'ytest_tailor_public', [
                'title' => '[YTEST] Kaftan Prestige Test',
                'content' => 'Produit de verification pour le parcours partenaire tailleur.',
                'excerpt' => 'Kaftan de test pour les parcours Yombal.',
                'status' => 'publish',
                'price' => '45000',
                'variant' => 'model',
                'thumbnail_code' => 'YMB-MDL-01',
            ]),
            'tailor_draft' => self::upsert_product($users['tailor'], 'ytest_tailor_draft', [
                'title' => '[YTEST] Modele Atelier Brouillon',
                'content' => 'Brouillon produit pour verifier le workspace partenaire.',
                'excerpt' => 'Produit brouillon de test.',
                'status' => 'draft',
                'price' => '39000',
                'variant' => 'model',
                'thumbnail_code' => 'YMB-MDL-02',
            ]),
            'fabric_public' => self::upsert_product($users['fabric_vendor'], 'ytest_fabric_public', [
                'title' => '[YTEST] Bazin Royal Test',
                'content' => 'Tissu de verification pour le parcours vendeur tissus.',
                'excerpt' => 'Tissu public de test.',
                'status' => 'publish',
                'price' => '18000',
                'variant' => 'fabric',
                'thumbnail_code' => 'YMB-FAB-01',
            ]),
            'fabric_draft' => self::upsert_product($users['fabric_vendor'], 'ytest_fabric_draft', [
                'title' => '[YTEST] Tissu Brouillon Test',
                'content' => 'Produit brouillon pour le parcours vendeur tissus.',
                'excerpt' => 'Brouillon tissu de test.',
                'status' => 'draft',
                'price' => '15000',
                'variant' => 'fabric',
                'thumbnail_code' => 'YMB-FAB-02',
            ]),
            'pending_draft' => self::upsert_product($users['pending_partner'], 'ytest_pending_draft', [
                'title' => '[YTEST] Modele En Revue',
                'content' => 'Produit brouillon d un partenaire en revue.',
                'excerpt' => 'Doit rester hors front public.',
                'status' => 'draft',
                'price' => '30000',
                'variant' => 'model',
                'thumbnail_code' => 'YMB-MDL-03',
            ]),
        ];

        $measurement_id = self::upsert_measurement($users['client']);
        $event = self::upsert_event($users['client']);

        $base_order_id = self::upsert_order($users['client'], [$products['fabric_public'], $products['tailor_public']], 'ytest_order_base');
        $paid_order_id = self::upsert_order($users['client'], [$products['fabric_public']], 'ytest_order_paid');

        $thread_id = self::upsert_thread($users['client'], $users['tailor'], $products['tailor_public']);
        $tickets = self::upsert_tickets($users['client'], $users['tailor'], $products['tailor_public'], $base_order_id);
        $requests = self::upsert_couture_requests($users['client'], $users['tailor'], $measurement_id, $products['tailor_public'], $paid_order_id);

        update_option(self::REPORT_OPTION, [
            'users' => $users,
            'products' => $products,
            'measurement_id' => $measurement_id,
            'thread_id' => $thread_id,
            'tickets' => $tickets,
            'couture_requests' => $requests,
            'event' => $event,
            'orders' => [
                'base' => $base_order_id,
                'paid' => $paid_order_id,
            ],
        ], false);
    }

    private static function upsert_user(string $fixture_key, array $definition): int {
        $user = get_user_by('login', (string) $definition['login']);
        $role = (string) ($definition['role'] ?? 'customer');

        if (! $user instanceof \WP_User) {
            $user_id = wp_create_user(
                (string) $definition['login'],
                self::fixture_password(),
                (string) $definition['email']
            );

            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
            }

            $user = get_user_by('id', (int) $user_id);
        }

        if (! $user instanceof \WP_User) {
            wp_die('Impossible de creer le compte fixture ' . esc_html($fixture_key));
        }

        wp_update_user([
            'ID' => (int) $user->ID,
            'user_email' => (string) $definition['email'],
            'display_name' => (string) $definition['display_name'],
            'user_pass' => self::fixture_password(),
        ]);

        $wp_user = new \WP_User((int) $user->ID);
        $wp_user->set_role($role);

        Fixtures::mark_user((int) $user->ID, $fixture_key);
        update_user_meta((int) $user->ID, 'billing_city', (string) ($definition['city'] ?? ''));
        update_user_meta((int) $user->ID, 'billing_phone', (string) ($definition['phone'] ?? ''));

        return (int) $user->ID;
    }

    private static function upsert_partner_profile(int $user_id, array $definition): void {
        global $wpdb;

        $table = Installer::table_name('yombal_partner_profiles');
        $payload = [
            'user_id' => $user_id,
            'partner_type' => (string) $definition['partner_type'],
            'profile_status' => (string) $definition['profile_status'],
            'display_name' => (string) $definition['display_name'],
            'store_name' => (string) $definition['store_name'],
            'city' => (string) $definition['city'],
            'phone' => (string) $definition['phone'],
            'specialties' => wp_json_encode((string) $definition['partner_type'] === Roles::TYPE_FABRIC_VENDOR ? ['wax', 'bazin'] : ['boubou', 'retouches', 'sur mesure']),
            'materials' => wp_json_encode((string) $definition['partner_type'] === Roles::TYPE_FABRIC_VENDOR ? ['coton', 'bazin', 'soie'] : ['bazin', 'lin', 'brocard']),
            'biography' => '[YTEST] Profil de verification pour les parcours Yombal.',
            'legacy_vendor_type' => null,
        ];

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id)
        );

        if ($existing_id > 0) {
            $wpdb->update($table, $payload, ['id' => $existing_id]);
            return;
        }

        $wpdb->insert($table, $payload);
    }

    private static function attach_partner_media(int $user_id, string $partner_type, string $fixture_key): void {
        $logo_code = match ($fixture_key) {
            'tailor' => 'YMB-LGO-01',
            'fabric_vendor' => 'YMB-LGO-02',
            default => 'YMB-LGO-03',
        };

        $cover_code = $partner_type === Roles::TYPE_FABRIC_VENDOR ? 'YMB-FAB-03' : 'YMB-MDL-04';
        $logo_id = self::attachment_id_for_code($logo_code);
        $cover_id = self::attachment_id_for_code($cover_code);

        if ($logo_id > 0) {
            update_user_meta($user_id, 'yombal_partner_logo_id', $logo_id);
        }
        if ($cover_id > 0) {
            update_user_meta($user_id, 'yombal_partner_cover_id', $cover_id);
        }
    }

    private static function upsert_product(int $author_id, string $fixture_key, array $data): int {
        $existing = self::find_post_by_fixture_key($fixture_key, 'product');
        $product_id = $existing > 0 ? $existing : 0;

        $post_id = wp_insert_post([
            'ID' => $product_id,
            'post_type' => 'product',
            'post_status' => (string) $data['status'],
            'post_title' => (string) $data['title'],
            'post_content' => (string) $data['content'],
            'post_excerpt' => (string) $data['excerpt'],
            'post_author' => $author_id,
        ], true);

        if (is_wp_error($post_id)) {
            wp_die($post_id->get_error_message());
        }

        $post_id = (int) $post_id;
        Fixtures::mark_post($post_id, $fixture_key);
        wp_set_object_terms($post_id, 'simple', 'product_type', false);
        update_post_meta($post_id, '_regular_price', (string) $data['price']);
        update_post_meta($post_id, '_price', (string) $data['price']);
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, '_manage_stock', 'no');
        update_post_meta($post_id, 'ville', $author_id === 0 ? 'Dakar' : ((string) get_user_meta($author_id, 'billing_city', true) ?: 'Dakar'));

        $thumbnail_id = self::attachment_id_for_code((string) $data['thumbnail_code']);
        if ($thumbnail_id > 0) {
            set_post_thumbnail($post_id, $thumbnail_id);
        }

        return $post_id;
    }

    private static function upsert_measurement(int $user_id): int {
        global $wpdb;

        $table = Installer::table_name('yombal_mesures');
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d AND profil_nom = %s LIMIT 1", $user_id, 'YTEST Profil principal')
        );

        $payload = [
            'user_id' => $user_id,
            'profil_nom' => 'YTEST Profil principal',
            'occasion' => 'Ceremonie',
            'poitrine' => 104.0,
            'taille' => 88.0,
            'hanches' => 106.0,
            'epaules' => 46.0,
            'longueur_buste' => 48.0,
            'longueur_robe' => 148.0,
            'longueur_manche' => 64.0,
            'tour_bras' => 34.0,
            'encolure' => 42.0,
            'notes' => '[YTEST] Profil de mesures pour la recette automatisee.',
        ];

        if ($existing_id > 0) {
            $wpdb->update($table, $payload, ['id' => $existing_id]);
            return $existing_id;
        }

        $wpdb->insert($table, $payload);

        return (int) $wpdb->insert_id;
    }

    private static function upsert_event(int $user_id): array {
        global $wpdb;

        $events_table = $wpdb->prefix . 'yombal_evenements';
        $participants_table = $wpdb->prefix . 'yombal_evenement_participants';
        $code = 'YTESTEVT';
        $event_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$events_table} WHERE code_invitation = %s LIMIT 1", $code)
        );

        $payload = [
            'organisateur_id' => $user_id,
            'nom' => '[YTEST] Evenement Couture',
            'date_evenement' => gmdate('Y-m-d', strtotime('+10 days')),
            'nb_personnes' => 3,
            'description' => '[YTEST] Groupe de verification des parcours evenement.',
            'code_invitation' => $code,
            'mode_paiement' => 'groupe',
            'statut' => 'actif',
        ];

        if ($event_id > 0) {
            $wpdb->update($events_table, $payload, ['id' => $event_id]);
        } else {
            $wpdb->insert($events_table, $payload);
            $event_id = (int) $wpdb->insert_id;
        }

        $participant_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$participants_table} WHERE evenement_id = %d AND email = %s LIMIT 1", $event_id, 'ytest.invite@yombal.sn')
        );

        $participant_payload = [
            'evenement_id' => $event_id,
            'user_id' => null,
            'nom_participant' => '[YTEST] Invite',
            'email' => 'ytest.invite@yombal.sn',
            'statut' => 'invite',
            'order_id' => null,
        ];

        if ($participant_id > 0) {
            $wpdb->update($participants_table, $participant_payload, ['id' => $participant_id]);
        } else {
            $wpdb->insert($participants_table, $participant_payload);
        }

        return [
            'id' => $event_id,
            'code' => $code,
        ];
    }

    private static function upsert_order(int $customer_id, array $product_ids, string $fixture_key): int {
        $existing = self::find_order_by_fixture_key($fixture_key);
        if ($existing instanceof \WC_Order) {
            self::reset_order_items($existing, $product_ids);
            $existing->update_meta_data(Fixtures::FLAG_META_KEY, '1');
            $existing->update_meta_data(Fixtures::KEY_META_KEY, $fixture_key);
            $existing->save();
            return (int) $existing->get_id();
        }

        $order = wc_create_order(['customer_id' => $customer_id]);
        if (! $order instanceof \WC_Order) {
            wp_die('Impossible de creer la commande fixture.');
        }

        self::reset_order_items($order, $product_ids);
        $order->update_meta_data(Fixtures::FLAG_META_KEY, '1');
        $order->update_meta_data(Fixtures::KEY_META_KEY, $fixture_key);
        $order->save();

        return (int) $order->get_id();
    }

    private static function upsert_thread(int $customer_id, int $partner_id, int $product_id): int {
        global $wpdb;

        $table = Installer::table_name('yombal_message_threads');
        $thread_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE subject = %s LIMIT 1", '[YTEST] Conversation couture')
        );

        if ($thread_id > 0) {
            return $thread_id;
        }

        return Message_Center::create_thread([
            'sender_user_id' => $customer_id,
            'recipient_user_id' => $partner_id,
            'subject' => '[YTEST] Conversation couture',
            'message' => 'Bonjour, ceci est un fil de test pour la recette Yombal.',
            'product_id' => $product_id,
        ]);
    }

    private static function upsert_tickets(int $customer_id, int $partner_id, int $product_id, int $order_id): array {
        $tickets = [];

        $tickets['waiting_partner'] = self::upsert_ticket([
            'fixture_key' => 'ytest_waiting_partner',
            'customer_id' => $customer_id,
            'partner_user_id' => $partner_id,
            'order_id' => $order_id,
            'product_id' => $product_id,
            'subject' => '[YTEST] Ticket attente partenaire',
            'message' => 'Le client attend une reponse du partenaire.',
            'status' => Ticket_Center::STATUS_WAITING_PARTNER,
        ]);

        $tickets['waiting_customer'] = self::upsert_ticket([
            'fixture_key' => 'ytest_waiting_customer',
            'customer_id' => $customer_id,
            'partner_user_id' => $partner_id,
            'order_id' => $order_id,
            'product_id' => $product_id,
            'subject' => '[YTEST] Ticket attente client',
            'message' => 'Le partenaire a deja repondu, le client doit completer.',
            'status' => Ticket_Center::STATUS_WAITING_CUSTOMER,
            'replies' => [
                ['author_user_id' => $partner_id, 'message' => 'Merci de confirmer la mesure souhaitee.'],
            ],
        ]);

        $tickets['resolved'] = self::upsert_ticket([
            'fixture_key' => 'ytest_resolved',
            'customer_id' => $customer_id,
            'partner_user_id' => $partner_id,
            'order_id' => $order_id,
            'product_id' => $product_id,
            'subject' => '[YTEST] Ticket resolu',
            'message' => 'Litige resolu pour la recette.',
            'status' => Ticket_Center::STATUS_RESOLVED,
            'replies' => [
                ['author_user_id' => $partner_id, 'message' => 'Nous avons propose une solution.'],
                ['author_user_id' => $customer_id, 'message' => 'Solution acceptee, merci.'],
            ],
        ]);

        return $tickets;
    }

    private static function upsert_ticket(array $payload): int {
        global $wpdb;

        $table = Installer::table_name('yombal_support_tickets');
        $reply_table = Installer::table_name('yombal_support_replies');
        $subject = (string) $payload['subject'];
        $ticket_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE subject = %s LIMIT 1", $subject)
        );

        $ticket_payload = [
            'order_id' => (int) ($payload['order_id'] ?? 0) ?: null,
            'product_id' => (int) ($payload['product_id'] ?? 0) ?: null,
            'customer_id' => (int) ($payload['customer_id'] ?? 0) ?: null,
            'partner_user_id' => (int) ($payload['partner_user_id'] ?? 0) ?: null,
            'category' => 'order_issue',
            'priority' => 'high',
            'status' => (string) ($payload['status'] ?? Ticket_Center::STATUS_WAITING_PARTNER),
            'subject' => $subject,
            'message' => (string) ($payload['message'] ?? ''),
            'last_reply_at' => current_time('mysql', true),
            'closed_at' => in_array((string) ($payload['status'] ?? ''), [Ticket_Center::STATUS_RESOLVED, Ticket_Center::STATUS_CLOSED], true) ? current_time('mysql', true) : null,
        ];

        if ($ticket_id > 0) {
            $wpdb->update($table, $ticket_payload, ['id' => $ticket_id]);
            $wpdb->delete($reply_table, ['ticket_id' => $ticket_id]);
        } else {
            $wpdb->insert($table, $ticket_payload);
            $ticket_id = (int) $wpdb->insert_id;
        }

        foreach ((array) ($payload['replies'] ?? []) as $reply) {
            $wpdb->insert($reply_table, [
                'ticket_id' => $ticket_id,
                'author_user_id' => (int) $reply['author_user_id'],
                'message' => (string) $reply['message'],
            ]);
        }

        return $ticket_id;
    }

    private static function upsert_couture_requests(int $customer_id, int $tailor_id, int $measurement_id, int $product_id, int $paid_order_id): array {
        $requests = [];
        $cart_snapshot = [
            [
                'product_id' => $product_id,
                'name' => get_the_title($product_id),
                'quantity' => 1,
            ],
        ];

        $requests['pending'] = self::upsert_couture_request('YTEST-CR-PENDING', [
            'customer_id' => $customer_id,
            'tailor_user_id' => $tailor_id,
            'measurement_profile_id' => $measurement_id,
            'model_source_type' => 'reference',
            'model_reference' => 'YTEST-CR-PENDING',
            'cart_snapshot' => wp_json_encode($cart_snapshot),
            'fabric_requirements' => wp_json_encode([['product_id' => $product_id, 'quantity' => 1]]),
            'customer_notes' => '[YTEST] Demande couture en attente.',
            'status' => Couture_Requests::STATUS_PENDING_TAILOR_REVIEW,
            'payment_unlocked' => 0,
        ]);

        $approved_id = self::upsert_couture_request('YTEST-CR-APPROVED', [
            'customer_id' => $customer_id,
            'tailor_user_id' => $tailor_id,
            'measurement_profile_id' => $measurement_id,
            'model_source_type' => 'reference',
            'model_reference' => 'YTEST-CR-APPROVED',
            'cart_snapshot' => wp_json_encode($cart_snapshot),
            'fabric_requirements' => wp_json_encode([['product_id' => $product_id, 'quantity' => 1]]),
            'customer_notes' => '[YTEST] Demande couture approuvee.',
            'status' => Couture_Requests::STATUS_APPROVED,
            'payment_unlocked' => 1,
            'couture_price' => 12000,
            'tailor_response' => 'Validation du couturier pour la recette.',
        ]);
        $requests['approved'] = $approved_id;

        $needs_more_id = self::upsert_couture_request('YTEST-CR-MORE-FABRIC', [
            'customer_id' => $customer_id,
            'tailor_user_id' => $tailor_id,
            'measurement_profile_id' => $measurement_id,
            'model_source_type' => 'reference',
            'model_reference' => 'YTEST-CR-MORE-FABRIC',
            'cart_snapshot' => wp_json_encode($cart_snapshot),
            'fabric_requirements' => wp_json_encode([['product_id' => $product_id, 'quantity' => 1]]),
            'customer_notes' => '[YTEST] Demande couture avec tissu supplementaire.',
            'status' => Couture_Requests::STATUS_NEEDS_MORE_FABRIC,
            'payment_unlocked' => 0,
            'required_fabric_qty' => 2.5,
            'tailor_response' => 'Ajoutez 2,5 metres pour finaliser la tenue.',
        ]);
        $requests['needs_more_fabric'] = $needs_more_id;

        $paid_id = self::upsert_couture_request('YTEST-CR-PAID', [
            'customer_id' => $customer_id,
            'tailor_user_id' => $tailor_id,
            'measurement_profile_id' => $measurement_id,
            'model_source_type' => 'reference',
            'model_reference' => 'YTEST-CR-PAID',
            'cart_snapshot' => wp_json_encode($cart_snapshot),
            'fabric_requirements' => wp_json_encode([['product_id' => $product_id, 'quantity' => 1]]),
            'customer_notes' => '[YTEST] Demande couture deja payee.',
            'status' => Couture_Requests::STATUS_PAYMENT_COMPLETED,
            'payment_unlocked' => 1,
            'couture_price' => 15000,
            'tailor_response' => 'Commande confirmee et payee.',
            'wc_order_id' => $paid_order_id,
        ]);
        $requests['payment_completed'] = $paid_id;

        return $requests;
    }

    private static function upsert_couture_request(string $model_reference, array $payload): int {
        global $wpdb;

        $table = Installer::table_name('yombal_couture_requests');
        $request_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE model_reference = %s LIMIT 1", $model_reference)
        );

        $data = array_merge([
            'customer_id' => 0,
            'tailor_user_id' => 0,
            'measurement_profile_id' => null,
            'wc_order_id' => null,
            'model_source_type' => 'reference',
            'model_reference' => $model_reference,
            'model_attachment_id' => null,
            'cart_snapshot' => null,
            'fabric_requirements' => null,
            'customer_notes' => null,
            'tailor_response' => null,
            'required_fabric_qty' => null,
            'couture_price' => null,
            'status' => Couture_Requests::STATUS_PENDING_TAILOR_REVIEW,
            'payment_unlocked' => 0,
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day')),
            'validated_at' => null,
            'cancelled_at' => null,
        ], $payload);

        if (in_array((string) $data['status'], [Couture_Requests::STATUS_APPROVED, Couture_Requests::STATUS_PAYMENT_COMPLETED], true) && empty($data['validated_at'])) {
            $data['validated_at'] = current_time('mysql', true);
        }

        if ($request_id > 0) {
            $wpdb->update($table, $data, ['id' => $request_id]);
            return $request_id;
        }

        $wpdb->insert($table, $data);

        return (int) $wpdb->insert_id;
    }

    private static function attachment_id_for_code(string $code): int {
        global $wpdb;

        $code = trim($code);
        if ($code === '') {
            return 0;
        }

        $like = '%' . $wpdb->esc_like(strtolower($code)) . '%';
        $attachment_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                FROM {$wpdb->posts} AS p
                LEFT JOIN {$wpdb->postmeta} AS pm
                    ON pm.post_id = p.ID
                    AND pm.meta_key = '_wp_attached_file'
                WHERE p.post_type = 'attachment'
                AND p.post_status = 'inherit'
                AND (
                    LOWER(p.post_title) LIKE %s
                    OR LOWER(pm.meta_value) LIKE %s
                )
                ORDER BY p.ID ASC
                LIMIT 1",
                $like,
                $like
            )
        );

        return $attachment_id > 0 ? $attachment_id : 0;
    }

    private static function find_post_by_fixture_key(string $fixture_key, string $post_type): int {
        $query = new \WP_Query([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => Fixtures::KEY_META_KEY,
                    'value' => sanitize_key($fixture_key),
                ],
            ],
        ]);

        return ! empty($query->posts[0]) ? (int) $query->posts[0] : 0;
    }

    private static function find_order_by_fixture_key(string $fixture_key): ?\WC_Order {
        $orders = wc_get_orders([
            'limit' => 1,
            'type' => 'shop_order',
            'meta_key' => Fixtures::KEY_META_KEY,
            'meta_value' => sanitize_key($fixture_key),
        ]);

        return isset($orders[0]) && $orders[0] instanceof \WC_Order ? $orders[0] : null;
    }

    private static function reset_order_items(\WC_Order $order, array $product_ids): void {
        foreach ($order->get_items() as $item_id => $item) {
            $order->remove_item($item_id);
        }

        foreach ($product_ids as $product_id) {
            $product = wc_get_product((int) $product_id);
            if (! $product instanceof \WC_Product) {
                continue;
            }

            $order->add_product($product, 1);
        }

        $order->calculate_totals();
        $order->set_status('processing');
    }

    private static function ticket_row(int $ticket_id): ?array {
        global $wpdb;

        if ($ticket_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . Installer::table_name('yombal_support_tickets') . ' WHERE id = %d LIMIT 1', $ticket_id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private static function require_ops_access(): void {
        if (! current_user_can('yombal_view_ops') && ! current_user_can('manage_woocommerce')) {
            wp_die('Acces refuse.');
        }
    }
}
