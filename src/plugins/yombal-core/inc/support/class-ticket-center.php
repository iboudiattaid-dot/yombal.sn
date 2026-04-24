<?php

declare(strict_types=1);

namespace Yombal\Core\Support;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Journeys\Fixtures;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Partners\Profile_Service;

if (! defined('ABSPATH')) {
    exit;
}

final class Ticket_Center {
    public const STATUS_OPEN = 'open';
    public const STATUS_WAITING_PARTNER = 'waiting_partner';
    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public static function boot(): void {
        add_shortcode('yombal_support_center', [self::class, 'render_page']);
        add_action('init', [self::class, 'handle_form_submission']);
        add_filter('woocommerce_my_account_my_orders_actions', [self::class, 'inject_order_action'], 25, 2);
        add_action('woocommerce_view_order', [self::class, 'render_order_help'], 4);
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            ob_start();
            ?>
            <div class="yombal-ui yombal-shell yombal-support-center">
                <?php echo Public_Shell::render_identity_strip(); ?>
                <section class="yombal-hero">
                    <span class="yombal-eyebrow">Aide et litiges</span>
                    <h1>Aide et litiges Yombal</h1>
                    <p>Connectez-vous pour ouvrir une demande, suivre une reponse et conserver un historique clair de vos echanges d assistance.</p>
                </section>
                <div class="yombal-card yombal-card--soft yombal-empty-state">
                    Vous devez etre connecte pour consulter vos demandes d aide.
                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(self::login_url(self::current_request_url() ?: self::get_page_url())); ?>">Se connecter</a>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        $user_id = get_current_user_id();
        $is_partner = Profile_Service::is_partner_user($user_id);
        $ticket_id = isset($_GET['ticket']) ? absint($_GET['ticket']) : 0;
        $tickets = self::get_tickets_for_user($user_id);
        $active_ticket = $ticket_id > 0 ? self::get_ticket($ticket_id, $user_id) : (isset($tickets[0]) ? self::get_ticket((int) $tickets[0]['id'], $user_id) : null);

        ob_start();
        ?>
        <div class="yombal-ui yombal-shell yombal-support-center">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Aide et litiges</span>
                <h1>Besoin d aide sur une commande ?</h1>
                <p>Ouvrez une demande claire, suivez les reponses et gardez un historique simple de ce qui a ete traite sur Yombal.</p>
            </section>

            <?php if (isset($_GET['ticket_saved'])) : ?>
                <div class="yombal-card yombal-card--accent">Votre demande a bien ete enregistree.</div>
            <?php endif; ?>

            <div class="yombal-grid yombal-grid--two">
                <?php if (! $is_partner) : ?>
                    <?php echo self::render_new_ticket_panel($user_id); ?>
                <?php else : ?>
                    <section class="yombal-card">
                        <div class="yombal-card__header">
                            <div class="yombal-stack">
                                <h2 class="yombal-section-title">Inbox litiges</h2>
                                <div class="yombal-card__meta">Retrouvez ici les tickets envoyes par les clients. Le partenaire repond et fait avancer le dossier, sans formulaire generique de creation.</div>
                            </div>
                        </div>
                        <div class="yombal-empty-state">Selectionnez un ticket a traiter dans la liste ci-contre pour lire le contexte, repondre ou mettre a jour son statut.</div>
                    </section>
                <?php endif; ?>
                <?php echo self::render_tickets_panel($tickets, $active_ticket ? (int) $active_ticket['id'] : 0); ?>
            </div>

            <?php if ($active_ticket) : ?>
                <?php echo self::render_ticket_panel($active_ticket); ?>
            <?php else : ?>
                <div class="yombal-empty-state">Aucune demande ouverte pour le moment.</div>
            <?php endif; ?>

        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function get_page_url(): string {
        return Page_Provisioner::get_page_url('litiges-yombal') ?: home_url('/litiges-yombal/');
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

    public static function create_url(array $context = []): string {
        return add_query_arg(array_filter([
            'order_id' => ! empty($context['order_id']) ? (int) $context['order_id'] : null,
            'partner_id' => ! empty($context['partner_id']) ? (int) $context['partner_id'] : null,
            'product_id' => ! empty($context['product_id']) ? (int) $context['product_id'] : null,
        ]), self::get_page_url());
    }

    public static function count_open_for_user(int $user_id): int {
        global $wpdb;

        if ($user_id <= 0) {
            return 0;
        }

        $custom = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Installer::table_name('yombal_support_tickets') . ' WHERE status != %s AND (customer_id = %d OR partner_user_id = %d)',
                self::STATUS_CLOSED,
                $user_id,
                $user_id
            )
        );

        return $custom;
    }

    public static function handle_form_submission(): void {
        if (! is_user_logged_in() || empty($_POST['yombal_ticket_action'])) {
            return;
        }

        $action = sanitize_key((string) $_POST['yombal_ticket_action']);
        if (! in_array($action, ['create_ticket', 'reply_ticket', 'resolve_ticket', 'close_ticket', 'reopen_ticket'], true)) {
            return;
        }

        check_admin_referer('yombal_ticket_action', 'yombal_ticket_nonce');

        $user_id = get_current_user_id();
        if ($action === 'create_ticket') {
            if (Profile_Service::is_partner_user($user_id)) {
                wp_safe_redirect(self::get_page_url());
                exit;
            }

            $ticket_id = self::create_ticket([
                'customer_id' => $user_id,
                'partner_user_id' => absint($_POST['partner_user_id'] ?? 0),
                'order_id' => absint($_POST['order_id'] ?? 0),
                'product_id' => absint($_POST['product_id'] ?? 0),
                'category' => sanitize_key((string) ($_POST['category'] ?? 'general')),
                'priority' => sanitize_key((string) ($_POST['priority'] ?? 'normal')),
                'subject' => sanitize_text_field((string) ($_POST['subject'] ?? 'Demande d aide Yombal')),
                'message' => wp_unslash((string) ($_POST['message_body'] ?? '')),
            ]);

            $target = $ticket_id > 0 ? add_query_arg(['ticket' => $ticket_id, 'ticket_saved' => 1], self::get_page_url()) : self::get_page_url();
            wp_safe_redirect($target);
            exit;
        }

        $ticket_id = absint($_POST['ticket_id'] ?? 0);
        if ($action === 'reply_ticket') {
            self::add_reply($ticket_id, $user_id, wp_unslash((string) ($_POST['message_body'] ?? '')));
            wp_safe_redirect(add_query_arg(['ticket' => $ticket_id, 'ticket_saved' => 1], self::get_page_url()));
            exit;
        }

        $transition = match ($action) {
            'resolve_ticket' => 'resolve',
            'close_ticket' => 'close',
            'reopen_ticket' => 'reopen',
            default => '',
        };

        if ($transition !== '') {
            self::transition_ticket($ticket_id, $user_id, $transition);
        }

        wp_safe_redirect(add_query_arg(['ticket' => $ticket_id, 'ticket_saved' => 1], self::get_page_url()));
        exit;
    }

    public static function inject_order_action(array $actions, $order): array {
        if (! is_user_logged_in() || ! $order instanceof \WC_Order) {
            return $actions;
        }

        $url = self::create_url(['order_id' => $order->get_id()]);
        $actions = ['yombal-support' => [
            'url' => $url,
            'name' => 'Besoin d aide',
        ]] + $actions;

        return $actions;
    }

    public static function render_order_help(int $order_id): void {
        if ($order_id <= 0 || ! is_user_logged_in()) {
            return;
        }

        echo '<div class="yombal-ui"><div class="yombal-card yombal-card--soft">';
        echo '<div class="yombal-card__header"><div class="yombal-stack"><h2 class="yombal-section-title">Besoin d aide sur cette commande ?</h2><div class="yombal-card__meta">Ouvrez une demande claire et suivez sa resolution depuis votre espace Yombal.</div></div></div>';
        echo '<div class="yombal-actions"><a class="yombal-button yombal-button--accent" href="' . esc_url(self::create_url(['order_id' => $order_id])) . '">Ouvrir une demande</a></div>';
        echo '</div></div>';
    }

    private static function render_new_ticket_panel(int $user_id): string {
        $partner_id = absint($_GET['partner_id'] ?? 0);
        $order_id = absint($_GET['order_id'] ?? 0);
        $product_id = absint($_GET['product_id'] ?? 0);

        ob_start();
        ?>
        <section class="yombal-card">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title">Nouvelle demande</h2>
                    <div class="yombal-card__meta">Expliquez le sujet simplement pour accelerer le traitement.</div>
                </div>
            </div>
            <form method="post" class="yombal-form">
                <?php wp_nonce_field('yombal_ticket_action', 'yombal_ticket_nonce'); ?>
                <input type="hidden" name="yombal_ticket_action" value="create_ticket">
                <input type="hidden" name="order_id" value="<?php echo esc_attr((string) $order_id); ?>">
                <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>">
                <div class="yombal-field-grid">
                    <?php if (! Profile_Service::is_partner_user($user_id)) : ?>
                        <div>
                            <label for="yombal-ticket-partner">Partenaire concerne</label>
                            <select id="yombal-ticket-partner" name="partner_user_id" required>
                                <option value="">Choisir un partenaire</option>
                                <?php foreach (self::partner_options($partner_id) as $option) : ?>
                                    <option value="<?php echo esc_attr((string) $option['user_id']); ?>" <?php selected($partner_id, (int) $option['user_id']); ?>>
                                        <?php echo esc_html((string) $option['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else : ?>
                        <input type="hidden" name="partner_user_id" value="<?php echo esc_attr((string) $user_id); ?>">
                    <?php endif; ?>
                    <div>
                        <label for="yombal-ticket-category">Sujet</label>
                        <select id="yombal-ticket-category" name="category">
                            <option value="general">Question generale</option>
                            <option value="order_issue">Commande</option>
                            <option value="delivery_issue">Livraison</option>
                            <option value="refund_request">Remboursement</option>
                            <option value="alteration_request">Retouche</option>
                        </select>
                    </div>
                    <div>
                        <label for="yombal-ticket-priority">Priorite</label>
                        <select id="yombal-ticket-priority" name="priority">
                            <option value="normal">Normale</option>
                            <option value="high">Importante</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="yombal-ticket-subject">Titre de la demande</label>
                    <input id="yombal-ticket-subject" type="text" name="subject" value="<?php echo esc_attr(self::prefilled_subject($order_id, $product_id)); ?>" required>
                </div>
                <div>
                    <label for="yombal-ticket-body">Expliquez votre demande</label>
                    <textarea id="yombal-ticket-body" name="message_body" rows="6" required placeholder="Precisez ce qui pose probleme ou ce dont vous avez besoin."></textarea>
                </div>
                <div class="yombal-form__actions">
                    <button type="submit" class="yombal-button yombal-button--accent">Envoyer la demande</button>
                </div>
            </form>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_tickets_panel(array $tickets, int $active_ticket_id): string {
        ob_start();
        ?>
        <section class="yombal-card yombal-card--soft">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title">Demandes en cours</h2>
                    <div class="yombal-card__meta">Retrouvez le statut de vos demandes et reprenez un echange si besoin.</div>
                </div>
            </div>
            <?php if ($tickets === []) : ?>
                <div class="yombal-empty-state">Aucune demande ouverte actuellement.</div>
            <?php else : ?>
                <div class="yombal-list">
                    <?php foreach ($tickets as $ticket) : ?>
                        <a class="yombal-card yombal-card--soft<?php echo (int) $ticket['id'] === $active_ticket_id ? ' yombal-card--accent' : ''; ?>" href="<?php echo esc_url(add_query_arg('ticket', (int) $ticket['id'], self::get_page_url())); ?>">
                            <div class="yombal-card__header">
                                <div class="yombal-stack">
                                    <strong><?php echo esc_html((string) $ticket['subject']); ?></strong>
                                    <div class="yombal-inline-meta">
                                        <span><?php echo esc_html((string) $ticket['meta_label']); ?></span>
                                        <span><?php echo esc_html((string) $ticket['updated_at']); ?></span>
                                    </div>
                                </div>
                                <span class="yombal-badge <?php echo esc_attr(self::status_badge_class((string) $ticket['status'])); ?>">
                                    <?php echo esc_html(self::status_label((string) $ticket['status'])); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_ticket_panel(array $ticket): string {
        $current_user_id = get_current_user_id();
        $is_partner = Profile_Service::is_partner_user($current_user_id);
        $context_rows = [];
        if (! empty($ticket['order_id'])) {
            $context_rows[] = ['Commande', '#' . (int) $ticket['order_id']];
        }
        if (! empty($ticket['product_id'])) {
            $context_rows[] = ['Produit', get_the_title((int) $ticket['product_id']) ?: ('#' . (int) $ticket['product_id'])];
        }
        if (! empty($ticket['partner_user_id'])) {
            $context_rows[] = ['Partenaire', self::user_label((int) $ticket['partner_user_id'])];
        }

        ob_start();
        ?>
        <section class="yombal-card">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title"><?php echo esc_html((string) ($ticket['subject'] ?? 'Demande Yombal')); ?></h2>
                    <div class="yombal-inline-meta">
                        <span><?php echo esc_html((string) ($ticket['category_label'] ?? 'Demande')); ?></span>
                        <span><?php echo esc_html((string) ($ticket['last_reply_at'] ?? '')); ?></span>
                    </div>
                </div>
                <span class="yombal-badge <?php echo esc_attr(self::status_badge_class((string) ($ticket['status'] ?? 'open'))); ?>">
                    <?php echo esc_html(self::status_label((string) ($ticket['status'] ?? 'open'))); ?>
                </span>
            </div>
            <article class="yombal-card yombal-card--soft">
                <div class="yombal-prose"><?php echo wp_kses_post(wpautop((string) ($ticket['message'] ?? ''))); ?></div>
            </article>
            <?php if ($context_rows !== []) : ?>
                <section class="yombal-card yombal-card--soft">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h3 class="yombal-section-title">Contexte du dossier</h3>
                            <div class="yombal-card__meta">Les informations utiles sont conservees dans le ticket pour faciliter la mediation.</div>
                        </div>
                    </div>
                    <div class="yombal-stack">
                        <?php foreach ($context_rows as [$label, $value]) : ?>
                            <div class="yombal-inline-meta"><span><?php echo esc_html((string) $label); ?></span><strong><?php echo esc_html((string) $value); ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <div class="yombal-card-stack">
                <?php foreach ((array) ($ticket['replies'] ?? []) as $reply) : ?>
                    <article class="yombal-card">
                        <div class="yombal-card__header">
                            <strong><?php echo esc_html(self::user_label((int) $reply['author_user_id'])); ?></strong>
                            <div class="yombal-inline-meta"><span><?php echo esc_html((string) $reply['created_at']); ?></span></div>
                        </div>
                        <div class="yombal-prose"><?php echo wp_kses_post(wpautop((string) $reply['message'])); ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="yombal-actions">
                <?php if ((string) ($ticket['status'] ?? self::STATUS_OPEN) !== self::STATUS_CLOSED) : ?>
                    <form method="post">
                        <?php wp_nonce_field('yombal_ticket_action', 'yombal_ticket_nonce'); ?>
                        <input type="hidden" name="yombal_ticket_action" value="resolve_ticket">
                        <input type="hidden" name="ticket_id" value="<?php echo esc_attr((string) $ticket['id']); ?>">
                        <button type="submit" class="yombal-button yombal-button--secondary">Marquer comme resolu</button>
                    </form>
                <?php endif; ?>
                <?php if (! $is_partner && (string) ($ticket['status'] ?? self::STATUS_OPEN) !== self::STATUS_CLOSED) : ?>
                    <form method="post">
                        <?php wp_nonce_field('yombal_ticket_action', 'yombal_ticket_nonce'); ?>
                        <input type="hidden" name="yombal_ticket_action" value="close_ticket">
                        <input type="hidden" name="ticket_id" value="<?php echo esc_attr((string) $ticket['id']); ?>">
                        <button type="submit" class="yombal-button yombal-button--secondary">Fermer le ticket</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array((string) ($ticket['status'] ?? self::STATUS_OPEN), [self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) : ?>
                    <form method="post">
                        <?php wp_nonce_field('yombal_ticket_action', 'yombal_ticket_nonce'); ?>
                        <input type="hidden" name="yombal_ticket_action" value="reopen_ticket">
                        <input type="hidden" name="ticket_id" value="<?php echo esc_attr((string) $ticket['id']); ?>">
                        <button type="submit" class="yombal-button yombal-button--accent">Reouvrir le ticket</button>
                    </form>
                <?php endif; ?>
            </div>
            <form method="post" class="yombal-form yombal-card yombal-card--soft">
                <?php wp_nonce_field('yombal_ticket_action', 'yombal_ticket_nonce'); ?>
                <input type="hidden" name="yombal_ticket_action" value="reply_ticket">
                <input type="hidden" name="ticket_id" value="<?php echo esc_attr((string) $ticket['id']); ?>">
                <div>
                    <label for="yombal-ticket-reply">Ajouter une reponse</label>
                    <textarea id="yombal-ticket-reply" name="message_body" rows="5" required placeholder="Ajoutez un complement d information ou votre reponse."></textarea>
                </div>
                <div class="yombal-form__actions">
                    <button type="submit" class="yombal-button yombal-button--accent">Envoyer la reponse</button>
                </div>
            </form>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function create_ticket(array $payload): int {
        global $wpdb;

        $message = trim((string) ($payload['message'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $partner_user_id = (int) ($payload['partner_user_id'] ?? 0);
        $customer_id = (int) ($payload['customer_id'] ?? 0);

        if ($subject === '' || $message === '' || $partner_user_id <= 0) {
            return 0;
        }

        $wpdb->insert(
            Installer::table_name('yombal_support_tickets'),
            [
                'order_id' => ! empty($payload['order_id']) ? (int) $payload['order_id'] : null,
                'product_id' => ! empty($payload['product_id']) ? (int) $payload['product_id'] : null,
                'customer_id' => $customer_id > 0 ? $customer_id : null,
                'partner_user_id' => $partner_user_id,
                'category' => sanitize_key((string) ($payload['category'] ?? 'general')),
                'priority' => sanitize_key((string) ($payload['priority'] ?? 'normal')),
                'status' => self::STATUS_WAITING_PARTNER,
                'subject' => sanitize_text_field($subject),
                'message' => wp_kses_post($message),
                'last_reply_at' => current_time('mysql', true),
            ]
        );

        $ticket_id = (int) $wpdb->insert_id;
        if ($ticket_id <= 0) {
            return 0;
        }

        Notification_Center::create(
            $partner_user_id,
            'support_ticket_created',
            'Nouvelle demande d aide',
            'Une nouvelle demande vous attend dans votre espace Yombal.',
            'support_ticket',
            $ticket_id
        );

        if ($customer_id > 0) {
            Notification_Center::create(
                $customer_id,
                'support_ticket_created',
                'Demande d aide enregistree',
                'Votre demande a bien ete transmise au partenaire. Vous serez notifie des qu une reponse sera publiee.',
                'support_ticket',
                $ticket_id
            );
        }

        return $ticket_id;
    }

    public static function add_reply(int $ticket_id, int $author_user_id, string $message): bool {
        global $wpdb;

        $message = trim($message);
        if ($ticket_id <= 0 || $author_user_id <= 0 || $message === '') {
            return false;
        }

        $ticket = self::get_ticket($ticket_id, $author_user_id);
        if (! $ticket) {
            return false;
        }

        $wpdb->insert(
            Installer::table_name('yombal_support_replies'),
            [
                'ticket_id' => $ticket_id,
                'author_user_id' => $author_user_id,
                'message' => wp_kses_post($message),
            ]
        );

        $wpdb->update(
            Installer::table_name('yombal_support_tickets'),
            [
                'last_reply_at' => current_time('mysql', true),
                'status' => self::status_after_reply($ticket, $author_user_id),
                'closed_at' => null,
            ],
            ['id' => $ticket_id]
        );

        $recipient_id = (int) ($ticket['customer_id'] ?? 0) === $author_user_id
            ? (int) ($ticket['partner_user_id'] ?? 0)
            : (int) ($ticket['customer_id'] ?? 0);

        if ($recipient_id > 0) {
            Notification_Center::create(
                $recipient_id,
                'support_ticket_reply',
                'Nouvelle reponse sur une demande',
                'Une demande d aide a recu une nouvelle reponse.',
                'support_ticket',
                $ticket_id
            );
        }

        return true;
    }

    public static function transition_ticket(int $ticket_id, int $actor_user_id, string $transition): bool {
        global $wpdb;

        $ticket = self::get_ticket($ticket_id, $actor_user_id);
        if (! $ticket) {
            return false;
        }

        $is_partner = self::is_partner_actor($ticket, $actor_user_id);
        $next_status = match ($transition) {
            'resolve' => self::STATUS_RESOLVED,
            'close' => $is_partner ? '' : self::STATUS_CLOSED,
            'reopen' => $is_partner ? self::STATUS_WAITING_CUSTOMER : self::STATUS_WAITING_PARTNER,
            default => '',
        };

        if ($next_status === '') {
            return false;
        }

        $updated = $wpdb->update(
            Installer::table_name('yombal_support_tickets'),
            [
                'status' => $next_status,
                'last_reply_at' => current_time('mysql', true),
                'closed_at' => in_array($next_status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true) ? current_time('mysql', true) : null,
            ],
            ['id' => $ticket_id]
        );

        if ($updated === false) {
            return false;
        }

        $recipient_id = $is_partner
            ? (int) ($ticket['customer_id'] ?? 0)
            : (int) ($ticket['partner_user_id'] ?? 0);

        if ($recipient_id > 0) {
            Notification_Center::create(
                $recipient_id,
                'support_ticket_reply',
                'Statut du ticket mis a jour',
                'Le statut d une demande d aide a evolue dans votre espace Yombal.',
                'support_ticket',
                $ticket_id
            );
        }

        return true;
    }

    private static function get_tickets_for_user(int $user_id): array {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_support_tickets') . ' WHERE customer_id = %d OR partner_user_id = %d ORDER BY last_reply_at DESC',
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        $tickets = [];
        foreach ((array) $rows as $row) {
            $tickets[] = [
                'id' => (int) $row['id'],
                'subject' => (string) $row['subject'],
                'status' => (string) $row['status'],
                'updated_at' => (string) ($row['last_reply_at'] ?? $row['updated_at'] ?? ''),
                'meta_label' => self::category_label((string) $row['category']),
            ];
        }

        return $tickets;
    }

    private static function get_ticket(int $ticket_id, int $user_id): ?array {
        global $wpdb;

        if ($ticket_id <= 0 || $user_id <= 0) {
            return null;
        }

        $ticket = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_support_tickets') . ' WHERE id = %d AND (customer_id = %d OR partner_user_id = %d) LIMIT 1',
                $ticket_id,
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        if (! is_array($ticket)) {
            return null;
        }

        $ticket['category_label'] = self::category_label((string) ($ticket['category'] ?? 'general'));
        $ticket['replies'] = self::ticket_replies((int) $ticket['id']);

        return $ticket;
    }

    private static function ticket_replies(int $ticket_id): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_support_replies') . ' WHERE ticket_id = %d ORDER BY created_at ASC',
                $ticket_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private static function partner_options(int $preferred_id): array {
        global $wpdb;

        $statuses = implode("', '", array_map('esc_sql', Profile_Service::public_statuses()));
        $rows = $wpdb->get_results(
            'SELECT user_id, store_name, display_name, city FROM ' . Installer::table_name('yombal_partner_profiles') . " WHERE profile_status IN ('{$statuses}') ORDER BY updated_at DESC",
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
            $options[$user_id] = [
                'user_id' => $user_id,
                'label' => $city !== '' ? "{$label} - {$city}" : $label,
            ];
        }

        if ($preferred_id > 0 && ! isset($options[$preferred_id])) {
            $options[$preferred_id] = [
                'user_id' => $preferred_id,
                'label' => self::user_label($preferred_id),
            ];
        }

        return array_values($options);
    }

    private static function prefilled_subject(int $order_id, int $product_id): string {
        if ($order_id > 0) {
            return 'Besoin d aide sur la commande #' . $order_id;
        }

        if ($product_id > 0) {
            return 'Question sur ' . get_the_title($product_id);
        }

        return 'Demande d aide Yombal';
    }

    private static function category_label(string $category): string {
        return match ($category) {
            'order_issue' => 'Commande',
            'delivery_issue' => 'Livraison',
            'refund_request' => 'Remboursement',
            'alteration_request' => 'Retouche',
            default => 'Question generale',
        };
    }

    private static function status_label(string $status): string {
        return match ($status) {
            self::STATUS_RESOLVED => 'Resolue',
            self::STATUS_WAITING_CUSTOMER => 'En attente du client',
            self::STATUS_WAITING_PARTNER => 'En attente du partenaire',
            self::STATUS_CLOSED => 'Fermee',
            default => 'Ouverte',
        };
    }

    private static function status_badge_class(string $status): string {
        return match ($status) {
            self::STATUS_RESOLVED, self::STATUS_CLOSED => 'yombal-badge--success',
            self::STATUS_WAITING_CUSTOMER, self::STATUS_WAITING_PARTNER => 'yombal-badge--accent',
            default => 'yombal-badge--muted',
        };
    }

    private static function status_after_reply(array $ticket, int $author_user_id): string {
        if (self::is_partner_actor($ticket, $author_user_id)) {
            return self::STATUS_WAITING_CUSTOMER;
        }

        return self::STATUS_WAITING_PARTNER;
    }

    private static function is_partner_actor(array $ticket, int $user_id): bool {
        return (int) ($ticket['partner_user_id'] ?? 0) === $user_id;
    }

    private static function user_label(int $user_id): string {
        $user = get_userdata($user_id);
        if (! $user) {
            return 'Membre Yombal';
        }

        $profile = Profile_Service::get_profile($user_id);
        return (string) ($profile['store_name'] ?? $user->display_name);
    }
}
