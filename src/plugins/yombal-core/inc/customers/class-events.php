<?php

declare(strict_types=1);

namespace Yombal\Core\Customers;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\UI\Dashboard_Shell;

if (! defined('ABSPATH')) {
    exit;
}

final class Events {
    public static function boot(): void {
        add_action('init', [self::class, 'register_account_endpoint'], 20);
        add_action('admin_init', [self::class, 'maybe_flush_rewrite_rules']);
        add_action('init', [self::class, 'maybe_create_tables'], 5);
        add_action('woocommerce_account_mes-evenements_endpoint', [self::class, 'render_account_endpoint']);
        add_shortcode('yombal_customer_events', [self::class, 'render_page']);
        add_shortcode('yombal_join_event', [self::class, 'render_join_page']);
    }

    public static function register_account_endpoint(): void {
        add_rewrite_endpoint('mes-evenements', EP_ROOT | EP_PAGES);
    }

    public static function maybe_flush_rewrite_rules(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (get_option('yombal_core_events_endpoint_version') === '1') {
            return;
        }

        self::register_account_endpoint();
        flush_rewrite_rules(false);
        update_option('yombal_core_events_endpoint_version', '1', false);
    }

    public static function render_account_endpoint(): void {
        echo self::render_page();
    }

    public static function maybe_create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(
            "CREATE TABLE {$wpdb->prefix}yombal_evenements (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                organisateur_id bigint(20) NOT NULL,
                nom varchar(200) NOT NULL,
                date_evenement date DEFAULT NULL,
                nb_personnes int DEFAULT 1,
                description text DEFAULT NULL,
                code_invitation varchar(20) NOT NULL,
                mode_paiement varchar(20) DEFAULT 'separe',
                statut varchar(20) DEFAULT 'actif',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY code_invitation (code_invitation)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$wpdb->prefix}yombal_evenement_participants (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                evenement_id bigint(20) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                nom_participant varchar(200) NOT NULL,
                email varchar(200) DEFAULT NULL,
                statut varchar(20) DEFAULT 'invite',
                order_id bigint(20) DEFAULT NULL,
                joined_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY evenement_id (evenement_id)
            ) {$charset_collate};"
        );
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour gerer vos evenements.</div></div>';
        }

        $user_id = get_current_user_id();
        $notice = self::handle_create_submission($user_id);
        $events = self::get_events_for_user($user_id);

        ob_start();
        ?>
        <div class="yombal-ui yombal-events yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Mes evenements</span>
                <h1>Organiser un achat a plusieurs</h1>
                <p>Invitez vos proches, partagez un code simple et suivez les participants dans un seul espace.</p>
            </section>

            <?php if ($notice !== '') : ?>
                <div class="woocommerce-message"><?php echo wp_kses_post($notice); ?></div>
            <?php endif; ?>

            <?php
            ob_start();
            ?>
            <form method="post" class="yombal-form">
                <?php wp_nonce_field('yombal_create_event_' . $user_id); ?>
                <input type="hidden" name="yombal_create_event" value="1">
                <div class="yombal-field-grid">
                    <p>
                        <label for="yombal_event_name">Nom de l evenement</label>
                        <input id="yombal_event_name" name="nom" type="text" required>
                    </p>
                    <p>
                        <label for="yombal_event_date">Date</label>
                        <input id="yombal_event_date" name="date_evt" type="date">
                    </p>
                </div>
                <div class="yombal-field-grid">
                    <p>
                        <label for="yombal_event_people">Nombre de participants</label>
                        <input id="yombal_event_people" name="nb_personnes" type="number" min="1" value="2">
                    </p>
                    <p>
                        <label for="yombal_event_payment">Paiement</label>
                        <select id="yombal_event_payment" name="mode_paiement">
                            <option value="separe">Chacun paie sa part</option>
                            <option value="groupe">Une seule personne paie pour le groupe</option>
                        </select>
                    </p>
                </div>
                <p>
                    <label for="yombal_event_description">Description</label>
                    <textarea id="yombal_event_description" name="description" rows="3" placeholder="Theme, date limite ou informations utiles"></textarea>
                </p>
                <div class="yombal-form__actions">
                    <button type="submit" class="yombal-button yombal-button--accent">Creer mon evenement</button>
                </div>
            </form>
            <?php
            echo Dashboard_Shell::render_section(
                'Creer un evenement',
                'Preparez un achat commun en quelques informations simples.',
                (string) ob_get_clean(),
                'soft'
            );

            ob_start();
            if ($events === []) {
                echo '<div class="yombal-empty-state">Vous n avez pas encore cree d evenement.</div>';
            } else {
                echo '<div class="yombal-grid yombal-grid--two">';
                foreach ($events as $event) {
                    $join_url = add_query_arg('code', (string) $event['code_invitation'], self::get_join_page_url());
                    echo '<article class="yombal-card yombal-card--soft">';
                    echo '<div class="yombal-card__header">';
                    echo '<div class="yombal-stack">';
                    echo '<h3 class="yombal-section-title">' . esc_html((string) $event['nom']) . '</h3>';
                    echo '<div class="yombal-card__meta">' . esc_html(self::format_event_meta($event)) . '</div>';
                    echo '</div>';
                    echo '</div>';
                    if (! empty($event['description'])) {
                        echo '<p>' . esc_html((string) $event['description']) . '</p>';
                    }
                    echo '<div class="yombal-inline-meta"><span>Code invitation</span><strong>' . esc_html((string) $event['code_invitation']) . '</strong></div>';
                    echo '<div class="yombal-inline-meta"><span>Participants</span><strong>' . esc_html((string) $event['participants_count']) . '</strong></div>';
                    echo '<div class="yombal-inline-meta"><span>Paiement</span><strong>' . esc_html(self::payment_mode_label((string) $event['mode_paiement'])) . '</strong></div>';
                    echo '<div class="yombal-actions">';
                    echo '<a href="' . esc_url($join_url) . '">Partager le lien invitation</a>';
                    echo '</div>';
                    echo '</article>';
                }
                echo '</div>';
            }

            echo Dashboard_Shell::render_section(
                'Mes evenements',
                'Retrouvez vos groupes, leurs codes d invitation et l avancement des participants.',
                (string) ob_get_clean()
            );
            ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_join_page(): string {
        $notice = self::handle_join_submission();
        $code = isset($_GET['code']) ? strtoupper(sanitize_text_field((string) $_GET['code'])) : '';
        $event = $code !== '' ? self::find_active_event_by_code($code) : null;
        $current_user = wp_get_current_user();

        ob_start();
        ?>
        <div class="yombal-ui yombal-events yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Invitation Yombal</span>
                <h1>Rejoindre un evenement</h1>
                <p>Entrez votre code ou utilisez le lien recu pour participer facilement a un achat de groupe.</p>
            </section>

            <?php if ($notice !== '') : ?>
                <div class="woocommerce-message"><?php echo wp_kses_post($notice); ?></div>
            <?php endif; ?>

            <section class="yombal-card yombal-card--soft">
                <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title">Participer</h2>
                        <div class="yombal-card__meta">Renseignez simplement vos informations pour confirmer votre participation.</div>
                    </div>
                </div>
                <form method="post" class="yombal-form">
                    <?php wp_nonce_field('yombal_join_event'); ?>
                    <input type="hidden" name="yombal_join_event" value="1">
                    <div class="yombal-field-grid">
                        <p>
                            <label for="yombal_join_code">Code</label>
                            <input id="yombal_join_code" name="code" type="text" value="<?php echo esc_attr($code); ?>" required>
                        </p>
                        <p>
                            <label for="yombal_join_name">Votre nom</label>
                            <input id="yombal_join_name" name="nom_participant" type="text" value="<?php echo esc_attr((string) ($current_user->display_name ?: '')); ?>" required>
                        </p>
                    </div>
                    <p>
                        <label for="yombal_join_email">Votre email</label>
                        <input id="yombal_join_email" name="email" type="email" value="<?php echo esc_attr((string) ($current_user->user_email ?? '')); ?>">
                    </p>
                    <div class="yombal-form__actions">
                        <button type="submit" class="yombal-button yombal-button--accent">Rejoindre l evenement</button>
                    </div>
                </form>
            </section>

            <?php
            if ($event) {
                ob_start();
                echo '<div class="yombal-stack">';
                echo '<div class="yombal-inline-meta"><span>Evenement</span><strong>' . esc_html((string) $event['nom']) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Date</span><strong>' . esc_html(self::format_date((string) $event['date_evenement'])) . '</strong></div>';
                echo '<div class="yombal-inline-meta"><span>Paiement</span><strong>' . esc_html(self::payment_mode_label((string) $event['mode_paiement'])) . '</strong></div>';
                if (! empty($event['description'])) {
                    echo '<p>' . esc_html((string) $event['description']) . '</p>';
                }
                echo '</div>';

                echo Dashboard_Shell::render_section(
                    'A propos de cet evenement',
                    'Voici les informations partagees par l organisateur.',
                    (string) ob_get_clean()
                );
            }
            ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function count_for_user(int $user_id): int {
        global $wpdb;

        if ($user_id <= 0 || ! self::events_table_exists()) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . self::events_table() . ' WHERE organisateur_id = %d', $user_id)
        );
    }

    public static function get_join_page_url(): string {
        return Page_Provisioner::get_page_url('rejoindre-evenement-yombal') ?: home_url('/rejoindre-evenement/');
    }

    public static function get_events_for_user(int $user_id): array {
        global $wpdb;

        if ($user_id <= 0 || ! self::events_table_exists()) {
            return [];
        }

        $events = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::events_table() . ' WHERE organisateur_id = %d ORDER BY created_at DESC',
                $user_id
            ),
            ARRAY_A
        );

        if (! is_array($events)) {
            return [];
        }

        foreach ($events as &$event) {
            $event['participants_count'] = self::participants_count((int) ($event['id'] ?? 0));
        }
        unset($event);

        return $events;
    }

    private static function handle_create_submission(int $user_id): string {
        if (! isset($_POST['yombal_create_event'])) {
            return '';
        }

        check_admin_referer('yombal_create_event_' . $user_id);

        global $wpdb;

        $code = strtoupper(substr(wp_hash((string) microtime(true) . '-' . (string) wp_rand()), 0, 8));
        $inserted = $wpdb->insert(
            self::events_table(),
            [
                'organisateur_id' => $user_id,
                'nom' => sanitize_text_field((string) ($_POST['nom'] ?? '')),
                'date_evenement' => self::sanitize_date((string) ($_POST['date_evt'] ?? '')),
                'nb_personnes' => max(1, (int) ($_POST['nb_personnes'] ?? 1)),
                'description' => sanitize_textarea_field((string) ($_POST['description'] ?? '')),
                'code_invitation' => $code,
                'mode_paiement' => in_array((string) ($_POST['mode_paiement'] ?? 'separe'), ['separe', 'groupe'], true) ? (string) $_POST['mode_paiement'] : 'separe',
                'statut' => 'actif',
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if (! $inserted) {
            return 'Nous n avons pas pu creer cet evenement pour le moment.';
        }

        $join_url = add_query_arg('code', $code, self::get_join_page_url());

        return 'Votre evenement est pret. Code invitation : <strong>' . esc_html($code) . '</strong>. <a href="' . esc_url($join_url) . '">Partager le lien</a>.';
    }

    private static function handle_join_submission(): string {
        if (! isset($_POST['yombal_join_event'])) {
            return '';
        }

        check_admin_referer('yombal_join_event');

        global $wpdb;

        $code = strtoupper(sanitize_text_field((string) ($_POST['code'] ?? '')));
        $event = self::find_active_event_by_code($code);

        if (! $event) {
            return 'Ce code ne correspond pas a un evenement actif.';
        }

        $name = sanitize_text_field((string) ($_POST['nom_participant'] ?? ''));
        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $user_id = is_user_logged_in() ? get_current_user_id() : null;

        if ($name === '') {
            return 'Veuillez indiquer votre nom pour rejoindre cet evenement.';
        }

        $already_joined = false;

        if ($user_id) {
            $already_joined = (bool) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM ' . self::participants_table() . ' WHERE evenement_id = %d AND user_id = %d LIMIT 1',
                    (int) $event['id'],
                    $user_id
                )
            );
        } elseif ($email !== '') {
            $already_joined = (bool) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM ' . self::participants_table() . ' WHERE evenement_id = %d AND email = %s LIMIT 1',
                    (int) $event['id'],
                    $email
                )
            );
        }

        if ($already_joined) {
            return 'Vous participez deja a cet evenement.';
        }

        $inserted = $wpdb->insert(
            self::participants_table(),
            [
                'evenement_id' => (int) $event['id'],
                'user_id' => $user_id,
                'nom_participant' => $name,
                'email' => $email !== '' ? $email : null,
                'statut' => 'confirme',
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        if (! $inserted) {
            return 'Nous n avons pas pu enregistrer votre participation pour le moment.';
        }

        return 'Votre participation a bien ete enregistree pour <strong>' . esc_html((string) $event['nom']) . '</strong>.';
    }

    private static function find_active_event_by_code(string $code): ?array {
        global $wpdb;

        if ($code === '' || ! self::events_table_exists()) {
            return null;
        }

        $event = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::events_table() . ' WHERE code_invitation = %s AND statut = %s LIMIT 1',
                $code,
                'actif'
            ),
            ARRAY_A
        );

        return is_array($event) ? $event : null;
    }

    private static function participants_count(int $event_id): int {
        global $wpdb;

        if ($event_id <= 0 || ! self::participants_table_exists()) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . self::participants_table() . ' WHERE evenement_id = %d', $event_id)
        );
    }

    private static function format_event_meta(array $event): string {
        $parts = [];

        if (! empty($event['date_evenement'])) {
            $parts[] = self::format_date((string) $event['date_evenement']);
        }

        $parts[] = self::payment_mode_label((string) ($event['mode_paiement'] ?? 'separe'));

        return implode(' | ', array_filter($parts));
    }

    private static function format_date(string $date): string {
        if ($date === '') {
            return 'Date a definir';
        }

        $timestamp = strtotime($date);

        return $timestamp ? wp_date('d/m/Y', $timestamp) : 'Date a definir';
    }

    private static function payment_mode_label(string $mode): string {
        return $mode === 'groupe' ? 'Paiement groupe' : 'Paiement separe';
    }

    private static function sanitize_date(string $date): ?string {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    private static function events_table_exists(): bool {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::events_table())) === self::events_table();
    }

    private static function participants_table_exists(): bool {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::participants_table())) === self::participants_table();
    }

    private static function events_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'yombal_evenements';
    }

    private static function participants_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'yombal_evenement_participants';
    }
}
