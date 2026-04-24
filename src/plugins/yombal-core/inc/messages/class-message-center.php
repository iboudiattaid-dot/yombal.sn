<?php

declare(strict_types=1);

namespace Yombal\Core\Messages;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Journeys\Fixtures;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Partners\Profile_Service;

if (! defined('ABSPATH')) {
    exit;
}

final class Message_Center {
    public static function boot(): void {
        add_shortcode('yombal_messages', [self::class, 'render_page']);
        add_action('init', [self::class, 'handle_form_submission']);
        add_action('wp_ajax_wcfm_ajax_submit_message', [self::class, 'block_legacy_contact_leaks'], 1);
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            ob_start();
            ?>
            <div class="yombal-ui yombal-shell yombal-message-center">
                <?php echo Public_Shell::render_identity_strip(); ?>
                <section class="yombal-hero">
                    <span class="yombal-eyebrow">Vos echanges</span>
                    <h1>Messages Yombal</h1>
                    <p>Connectez-vous pour retrouver vos conversations, vos reponses et vos demandes importantes au meme endroit.</p>
                </section>
                <div class="yombal-card yombal-card--soft yombal-empty-state">
                    Vous devez etre connecte pour consulter vos messages.
                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(self::login_url(self::current_request_url() ?: self::get_page_url())); ?>">Se connecter</a>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        $user_id = get_current_user_id();
        $thread_id = isset($_GET['thread']) ? absint($_GET['thread']) : 0;
        $threads = self::get_threads_for_user($user_id);
        $active_thread = $thread_id > 0 ? self::get_thread($thread_id, $user_id) : (isset($threads[0]) ? self::get_thread((int) $threads[0]['id'], $user_id) : null);

        ob_start();
        ?>
        <div class="yombal-ui yombal-shell yombal-message-center">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Vos echanges</span>
                <h1>Messages Yombal</h1>
                <p>Discutez simplement avec vos partenaires ou vos clients tout en gardant les echanges utiles au meme endroit.</p>
            </section>

            <?php if (isset($_GET['message_sent'])) : ?>
                <div class="yombal-card yombal-card--accent">Votre message a bien ete envoye.</div>
            <?php endif; ?>

            <div class="yombal-grid yombal-grid--two">
                <?php echo self::render_compose_panel($user_id); ?>
                <?php echo self::render_threads_panel($threads, $active_thread ? (int) $active_thread['id'] : 0); ?>
            </div>

            <?php if ($active_thread) : ?>
                <?php echo self::render_thread_panel($active_thread, $user_id); ?>
            <?php else : ?>
                <div class="yombal-empty-state">Aucune conversation pour le moment. Utilisez le formulaire pour contacter un partenaire ou repondre a un client.</div>
            <?php endif; ?>

        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function get_page_url(): string {
        return Page_Provisioner::get_page_url('messages-yombal') ?: home_url('/messages-yombal/');
    }

    private static function login_url(string $target): string {
        return add_query_arg('redirect_to', $target, home_url('/connexion/'));
    }

    private static function current_request_url(): string {
        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($request_uri === '') {
            return '';
        }

        return home_url($request_uri);
    }

    public static function compose_url(int $recipient_id = 0, array $context = []): string {
        $args = [];
        if ($recipient_id > 0) {
            $args['recipient'] = $recipient_id;
        }

        foreach (['product_id', 'order_id', 'couture_request_id'] as $key) {
            if (! empty($context[$key])) {
                $args[$key] = (int) $context[$key];
            }
        }

        return add_query_arg($args, self::get_page_url());
    }

    public static function count_unread_for_user(int $user_id): int {
        global $wpdb;

        if ($user_id <= 0) {
            return 0;
        }

        $custom = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Installer::table_name('yombal_message_entries') . ' WHERE recipient_user_id = %d AND status = %s',
                $user_id,
                'sent'
            )
        );

        return $custom;
    }

    public static function handle_form_submission(): void {
        if (! is_user_logged_in() || empty($_POST['yombal_message_action'])) {
            return;
        }

        $action = sanitize_key((string) $_POST['yombal_message_action']);
        if (! in_array($action, ['start_thread', 'reply_thread'], true)) {
            return;
        }

        check_admin_referer('yombal_message_action', 'yombal_message_nonce');

        $user_id = get_current_user_id();
        $message = wp_unslash((string) ($_POST['message_body'] ?? ''));
        if (self::contains_blocked_contact($message)) {
            wp_safe_redirect(add_query_arg('message_error', 'contact', self::get_page_url()));
            exit;
        }

        if ($action === 'start_thread') {
            $recipient_id = absint($_POST['recipient_id'] ?? 0);
            $thread_id = self::create_thread([
                'sender_user_id' => $user_id,
                'recipient_user_id' => $recipient_id,
                'subject' => sanitize_text_field((string) ($_POST['subject'] ?? 'Conversation Yombal')),
                'message' => $message,
                'product_id' => absint($_POST['product_id'] ?? 0),
                'order_id' => absint($_POST['order_id'] ?? 0),
                'couture_request_id' => absint($_POST['couture_request_id'] ?? 0),
            ]);

            $target = $thread_id > 0 ? add_query_arg(['thread' => $thread_id, 'message_sent' => 1], self::get_page_url()) : self::get_page_url();
            wp_safe_redirect($target);
            exit;
        }

        $thread_id = absint($_POST['thread_id'] ?? 0);
        $thread = self::get_thread($thread_id, $user_id);
        if ($thread) {
            self::add_message($thread_id, $user_id, self::thread_recipient_for_user($thread, $user_id), $message);
        }

        wp_safe_redirect(add_query_arg(['thread' => $thread_id, 'message_sent' => 1], self::get_page_url()));
        exit;
    }

    public static function create_thread(array $payload): int {
        global $wpdb;

        $sender_user_id = (int) ($payload['sender_user_id'] ?? 0);
        $recipient_user_id = (int) ($payload['recipient_user_id'] ?? 0);
        $message = trim((string) ($payload['message'] ?? ''));
        if ($sender_user_id <= 0 || $recipient_user_id <= 0 || $message === '') {
            return 0;
        }

        $customer_id = Profile_Service::is_partner_user($sender_user_id) ? $recipient_user_id : $sender_user_id;
        $partner_id = Profile_Service::is_partner_user($sender_user_id) ? $sender_user_id : $recipient_user_id;

        $wpdb->insert(
            Installer::table_name('yombal_message_threads'),
            [
                'subject' => sanitize_text_field((string) ($payload['subject'] ?? 'Conversation Yombal')),
                'customer_id' => $customer_id > 0 ? $customer_id : null,
                'partner_id' => $partner_id > 0 ? $partner_id : null,
                'order_id' => ! empty($payload['order_id']) ? (int) $payload['order_id'] : null,
                'product_id' => ! empty($payload['product_id']) ? (int) $payload['product_id'] : null,
                'couture_request_id' => ! empty($payload['couture_request_id']) ? (int) $payload['couture_request_id'] : null,
                'status' => 'open',
                'last_message_at' => current_time('mysql', true),
            ]
        );

        $thread_id = (int) $wpdb->insert_id;
        if ($thread_id <= 0) {
            return 0;
        }

        self::add_message($thread_id, $sender_user_id, $recipient_user_id, $message);

        return $thread_id;
    }

    public static function get_threads_for_user(int $user_id): array {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $table = Installer::table_name('yombal_message_threads');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d OR partner_id = %d ORDER BY last_message_at DESC",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        $threads = [];
        foreach ((array) $rows as $row) {
            $thread_id = (int) ($row['id'] ?? 0);
            if ($thread_id <= 0) {
                continue;
            }

            $other_user_id = self::thread_recipient_for_user($row, $user_id);
            $threads[] = [
                'id' => $thread_id,
                'subject' => (string) ($row['subject'] ?? 'Conversation Yombal'),
                'other_user_id' => $other_user_id,
                'other_label' => self::user_label($other_user_id),
                'last_message' => self::last_message_excerpt($thread_id),
                'last_message_at' => (string) ($row['last_message_at'] ?? $row['updated_at'] ?? ''),
                'unread' => self::thread_unread_count($thread_id, $user_id),
            ];
        }

        return $threads;
    }

    public static function get_thread(int $thread_id, int $user_id): ?array {
        global $wpdb;

        if ($thread_id <= 0 || $user_id <= 0) {
            return null;
        }

        $table = Installer::table_name('yombal_message_threads');
        $thread = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND (customer_id = %d OR partner_id = %d) LIMIT 1",
                $thread_id,
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        if (! is_array($thread)) {
            return null;
        }

        self::mark_thread_read($thread_id, $user_id);

        $thread['messages'] = self::get_thread_messages($thread_id);
        $thread['other_user_id'] = self::thread_recipient_for_user($thread, $user_id);
        $thread['other_label'] = self::user_label((int) $thread['other_user_id']);

        return $thread;
    }

    public static function block_legacy_contact_leaks(): void {
        if (! isset($_POST['message'])) {
            return;
        }

        $message = wp_unslash((string) $_POST['message']);
        if (! self::contains_blocked_contact($message)) {
            return;
        }

        wp_send_json_error([
            'message' => 'Les coordonnees personnelles ne sont pas autorisees. Gardez vos echanges directement sur Yombal.',
        ]);
        exit;
    }

    private static function render_compose_panel(int $user_id): string {
        $recipient_id = absint($_GET['recipient'] ?? $_GET['with'] ?? 0);
        $product_id = absint($_GET['product_id'] ?? 0);
        $order_id = absint($_GET['order_id'] ?? 0);
        $couture_request_id = absint($_GET['couture_request_id'] ?? 0);
        $recipient_options = self::recipient_options($user_id, $recipient_id);
        $subject = self::prefilled_subject($recipient_id, $product_id, $order_id, $couture_request_id);

        ob_start();
        ?>
        <section class="yombal-card">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title">Nouveau message</h2>
                    <div class="yombal-card__meta">Lancez une conversation claire et gardez tout sur la plateforme.</div>
                </div>
            </div>
            <?php if (isset($_GET['message_error']) && $_GET['message_error'] === 'contact') : ?>
                <div class="yombal-empty-state">Les coordonnees personnelles ne sont pas autorisees dans les messages.</div>
            <?php endif; ?>
            <form method="post" class="yombal-form">
                <?php wp_nonce_field('yombal_message_action', 'yombal_message_nonce'); ?>
                <input type="hidden" name="yombal_message_action" value="start_thread">
                <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>">
                <input type="hidden" name="order_id" value="<?php echo esc_attr((string) $order_id); ?>">
                <input type="hidden" name="couture_request_id" value="<?php echo esc_attr((string) $couture_request_id); ?>">
                <div>
                    <label for="yombal-message-recipient">Destinataire</label>
                    <select id="yombal-message-recipient" name="recipient_id" required>
                        <option value="">Choisir une personne</option>
                        <?php foreach ($recipient_options as $option) : ?>
                            <option value="<?php echo esc_attr((string) $option['user_id']); ?>" <?php selected($recipient_id, (int) $option['user_id']); ?>>
                                <?php echo esc_html((string) $option['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="yombal-message-subject">Sujet</label>
                    <input id="yombal-message-subject" type="text" name="subject" value="<?php echo esc_attr($subject); ?>" required>
                </div>
                <div>
                    <label for="yombal-message-body">Message</label>
                    <textarea id="yombal-message-body" name="message_body" rows="6" required placeholder="Expliquez votre besoin simplement."></textarea>
                </div>
                <div class="yombal-form__actions">
                    <button type="submit" class="yombal-button yombal-button--accent">Envoyer le message</button>
                </div>
            </form>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_threads_panel(array $threads, int $active_thread_id): string {
        ob_start();
        ?>
        <section class="yombal-card yombal-card--soft">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title">Conversations</h2>
                    <div class="yombal-card__meta">Retrouvez vos echanges recents et reprenez facilement une discussion.</div>
                </div>
            </div>
            <?php if ($threads === []) : ?>
                <div class="yombal-empty-state">Aucune conversation pour le moment.</div>
            <?php else : ?>
                <div class="yombal-list">
                    <?php foreach ($threads as $thread) : ?>
                        <a class="yombal-card yombal-card--soft<?php echo (int) $thread['id'] === $active_thread_id ? ' yombal-card--accent' : ''; ?>" href="<?php echo esc_url(add_query_arg('thread', (int) $thread['id'], self::get_page_url())); ?>">
                            <div class="yombal-card__header">
                                <div class="yombal-stack">
                                    <strong><?php echo esc_html((string) $thread['subject']); ?></strong>
                                    <div class="yombal-inline-meta">
                                        <span><?php echo esc_html((string) $thread['other_label']); ?></span>
                                        <span><?php echo esc_html((string) $thread['last_message_at']); ?></span>
                                    </div>
                                </div>
                                <?php if ((int) $thread['unread'] > 0) : ?>
                                    <span class="yombal-badge yombal-badge--accent"><?php echo esc_html((string) $thread['unread']); ?> nouveau(x)</span>
                                <?php endif; ?>
                            </div>
                            <p><?php echo esc_html((string) $thread['last_message']); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_thread_panel(array $thread, int $user_id): string {
        ob_start();
        ?>
        <section class="yombal-card">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title"><?php echo esc_html((string) ($thread['subject'] ?? 'Conversation Yombal')); ?></h2>
                    <div class="yombal-card__meta">Conversation avec <?php echo esc_html((string) ($thread['other_label'] ?? '')); ?></div>
                </div>
                <span class="yombal-badge yombal-badge--muted"><?php echo esc_html((string) ($thread['status'] ?? 'open')); ?></span>
            </div>
            <div class="yombal-card-stack">
                <?php foreach ((array) ($thread['messages'] ?? []) as $message) : ?>
                    <article class="yombal-card <?php echo (int) $message['sender_user_id'] === $user_id ? 'yombal-card--accent' : 'yombal-card--soft'; ?>">
                        <div class="yombal-card__header">
                            <strong><?php echo esc_html(self::user_label((int) $message['sender_user_id'])); ?></strong>
                            <div class="yombal-inline-meta">
                                <span><?php echo esc_html((string) $message['created_at']); ?></span>
                                <span><?php echo esc_html((int) $message['sender_user_id'] === $user_id ? 'Envoye' : 'Recu'); ?></span>
                            </div>
                        </div>
                        <div class="yombal-prose"><?php echo wp_kses_post(wpautop((string) $message['message'])); ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <form method="post" class="yombal-form yombal-card yombal-card--soft">
                <?php wp_nonce_field('yombal_message_action', 'yombal_message_nonce'); ?>
                <input type="hidden" name="yombal_message_action" value="reply_thread">
                <input type="hidden" name="thread_id" value="<?php echo esc_attr((string) $thread['id']); ?>">
                <div>
                    <label for="yombal-reply-body">Votre reponse</label>
                    <textarea id="yombal-reply-body" name="message_body" rows="5" required placeholder="Ecrivez votre reponse ici."></textarea>
                </div>
                <div class="yombal-form__actions">
                    <button type="submit" class="yombal-button yombal-button--accent">Envoyer la reponse</button>
                </div>
            </form>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function add_message(int $thread_id, int $sender_user_id, int $recipient_user_id, string $message): void {
        global $wpdb;

        $message = trim($message);
        if ($thread_id <= 0 || $sender_user_id <= 0 || $recipient_user_id <= 0 || $message === '') {
            return;
        }

        $wpdb->insert(
            Installer::table_name('yombal_message_entries'),
            [
                'thread_id' => $thread_id,
                'sender_user_id' => $sender_user_id,
                'recipient_user_id' => $recipient_user_id,
                'message' => wp_kses_post($message),
                'status' => 'sent',
            ]
        );

        $wpdb->update(
            Installer::table_name('yombal_message_threads'),
            ['last_message_at' => current_time('mysql', true)],
            ['id' => $thread_id]
        );

        Notification_Center::create(
            $recipient_user_id,
            'message_received',
            'Nouveau message',
            'Vous avez recu un nouveau message sur Yombal.',
            'message_thread',
            $thread_id
        );
    }

    private static function get_thread_messages(int $thread_id): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_message_entries') . ' WHERE thread_id = %d ORDER BY created_at ASC',
                $thread_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private static function mark_thread_read(int $thread_id, int $user_id): void {
        global $wpdb;

        $wpdb->update(
            Installer::table_name('yombal_message_entries'),
            [
                'status' => 'read',
                'read_at' => current_time('mysql', true),
            ],
            [
                'thread_id' => $thread_id,
                'recipient_user_id' => $user_id,
                'status' => 'sent',
            ]
        );
    }

    private static function thread_unread_count(int $thread_id, int $user_id): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Installer::table_name('yombal_message_entries') . ' WHERE thread_id = %d AND recipient_user_id = %d AND status = %s',
                $thread_id,
                $user_id,
                'sent'
            )
        );
    }

    private static function last_message_excerpt(int $thread_id): string {
        global $wpdb;

        $message = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT message FROM ' . Installer::table_name('yombal_message_entries') . ' WHERE thread_id = %d ORDER BY created_at DESC LIMIT 1',
                $thread_id
            )
        );

        return wp_trim_words(wp_strip_all_tags((string) $message), 12);
    }

    private static function thread_recipient_for_user(array $thread, int $user_id): int {
        $customer_id = (int) ($thread['customer_id'] ?? 0);
        $partner_id = (int) ($thread['partner_id'] ?? 0);

        return $customer_id === $user_id ? $partner_id : $customer_id;
    }

    private static function user_label(int $user_id): string {
        $user = get_userdata($user_id);
        if (! $user) {
            return 'Membre Yombal';
        }

        $profile = Profile_Service::get_profile($user_id);
        $name = (string) ($profile['store_name'] ?? $user->display_name);
        $type = Profile_Service::is_partner_user($user_id) ? 'Partenaire' : 'Client';

        return trim($name . ' - ' . $type);
    }

    private static function recipient_options(int $user_id, int $preferred_id): array {
        $options = [];

        if (! Profile_Service::is_partner_user($user_id)) {
            foreach (self::partner_recipient_rows() as $row) {
                $options[(int) $row['user_id']] = $row;
            }
        } else {
            foreach (self::customer_recipient_rows($user_id) as $row) {
                $options[(int) $row['user_id']] = $row;
            }
        }

        if ($preferred_id > 0 && ! isset($options[$preferred_id])) {
            $options[$preferred_id] = [
                'user_id' => $preferred_id,
                'label' => self::user_label($preferred_id),
            ];
        }

        return array_values($options);
    }

    private static function partner_recipient_rows(): array {
        global $wpdb;

        $statuses = implode("', '", array_map('esc_sql', Profile_Service::public_statuses()));
        $rows = $wpdb->get_results(
            'SELECT user_id, store_name, display_name, city FROM ' . Installer::table_name('yombal_partner_profiles') . " WHERE profile_status IN ('{$statuses}') ORDER BY updated_at DESC LIMIT 50",
            ARRAY_A
        );

        $options = [];
        foreach ((array) $rows as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0 || Fixtures::is_fixture_user($user_id)) {
                continue;
            }

            $label = trim((string) ($row['store_name'] ?? $row['display_name'] ?? 'Partenaire'));
            $city = trim((string) ($row['city'] ?? ''));
            $options[] = [
                'user_id' => $user_id,
                'label' => $city !== '' ? "{$label} - {$city}" : $label,
            ];
        }

        return $options;
    }

    private static function customer_recipient_rows(int $partner_user_id): array {
        global $wpdb;

        $customer_ids = [];

        $thread_rows = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT customer_id FROM ' . Installer::table_name('yombal_message_threads') . ' WHERE partner_id = %d',
                $partner_user_id
            )
        );
        $customer_ids = array_merge($customer_ids, array_map('intval', (array) $thread_rows));

        $request_rows = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT customer_id FROM ' . Installer::table_name('yombal_couture_requests') . ' WHERE tailor_user_id = %d',
                $partner_user_id
            )
        );
        $customer_ids = array_merge($customer_ids, array_map('intval', (array) $request_rows));

        $customer_ids = array_values(array_unique(array_filter($customer_ids)));
        $options = [];
        foreach ($customer_ids as $customer_id) {
            $options[] = [
                'user_id' => $customer_id,
                'label' => self::user_label($customer_id),
            ];
        }

        return $options;
    }

    private static function prefilled_subject(int $recipient_id, int $product_id, int $order_id, int $couture_request_id): string {
        if ($product_id > 0) {
            return 'Question sur ' . get_the_title($product_id);
        }

        if ($order_id > 0) {
            return 'Suivi de commande #' . $order_id;
        }

        if ($couture_request_id > 0) {
            return 'Demande sur mesure #' . $couture_request_id;
        }

        if ($recipient_id > 0) {
            return 'Bonjour';
        }

        return 'Conversation Yombal';
    }

    private static function contains_blocked_contact(string $message): bool {
        $patterns = [
            '/[7][0-9]{8}/',
            '/\\+?221[0-9]{8,9}/',
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}/',
            '/whatsapp/i',
            '/instagram/i',
            '/telegram/i',
            '/contactez.?moi/i',
            '/appelez.?moi/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }
}
