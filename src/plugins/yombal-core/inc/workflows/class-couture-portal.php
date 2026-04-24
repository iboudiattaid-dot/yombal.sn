<?php

declare(strict_types=1);

namespace Yombal\Core\Workflows;

use Yombal\Core\Customers\Measurements;
use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Journeys\Fixtures;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Partners\Profile_Service;
use Yombal\Core\Partners\Roles;

if (! defined('ABSPATH')) {
    exit;
}

final class Couture_Portal {
    public static function boot(): void {
        add_shortcode('yombal_couture_request_form', [self::class, 'render_customer_page']);
        add_shortcode('yombal_tailor_requests', [self::class, 'render_tailor_requests']);

        add_action('admin_post_yombal_create_couture_request', [self::class, 'handle_create_request']);
        add_action('admin_post_yombal_approve_couture_request', [self::class, 'handle_approve_request']);
        add_action('admin_post_yombal_require_more_fabric', [self::class, 'handle_require_more_fabric']);
        add_action('admin_post_yombal_choose_fabric_only', [self::class, 'handle_choose_fabric_only']);
    }

    public static function render_customer_page(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour preparer une demande tissu + couture.</div></div>';
        }

        $tailors = self::get_available_tailors();
        $measurements = Measurements::get_user_measurements(get_current_user_id());
        $request_id = isset($_GET['request_id']) ? (int) $_GET['request_id'] : self::current_request_id();
        $request = $request_id > 0 ? Couture_Requests::get($request_id) : null;
        $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');

        ob_start();
        ?>
        <div class="yombal-ui yombal-couture-portal yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Tissu et confection</span>
                <h1>Finaliser votre panier tissu</h1>
                <p>Choisissez entre un achat de tissu simple ou une confection sur mesure avec un couturier. Le paiement de la couture ne s ouvre qu apres validation du couturier.</p>
            </section>

            <?php if ($request) : ?>
                <?php echo self::render_request_summary($request, $checkout_url); ?>
            <?php endif; ?>

            <div class="yombal-grid yombal-grid--two">
                <section class="yombal-card yombal-card--soft">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Tissu seul</h2>
                            <div class="yombal-card__meta">Vous commandez uniquement le tissu et poursuivez normalement votre achat.</div>
                        </div>
                        <span class="yombal-badge yombal-badge--muted">Paiement direct</span>
                    </div>
                    <p>Ideal si vous souhaitez uniquement recevoir votre tissu.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('yombal_choose_fabric_only'); ?>
                        <input type="hidden" name="action" value="yombal_choose_fabric_only">
                        <div class="yombal-form__actions">
                            <button type="submit" class="yombal-button yombal-button--secondary">Continuer vers le paiement</button>
                        </div>
                    </form>
                </section>

                <section class="yombal-card yombal-card--accent">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Tissu + Couture</h2>
                            <div class="yombal-card__meta">Choisissez votre couturier, partagez votre modele et attendez sa confirmation avant de payer.</div>
                        </div>
                        <span class="yombal-badge yombal-badge--accent">Validation 24h</span>
                    </div>
                    <?php if (! self::cart_has_items()) : ?>
                        <div class="yombal-empty-state">Le panier est vide. Ajoutez du tissu avant de creer une demande.</div>
                    <?php elseif (! $tailors) : ?>
                        <div class="yombal-empty-state">Aucun couturier n est disponible pour le moment. Revenez un peu plus tard ou contactez notre equipe.</div>
                    <?php else : ?>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="yombal-form">
                            <?php wp_nonce_field('yombal_create_couture_request'); ?>
                            <input type="hidden" name="action" value="yombal_create_couture_request">

                            <div class="yombal-field-grid">
                                <p>
                                    <label for="tailor_user_id">Choisir un couturier</label>
                                    <select id="tailor_user_id" name="tailor_user_id" required>
                                        <option value="">Selectionner</option>
                                        <?php foreach ($tailors as $tailor) : ?>
                                            <option value="<?php echo esc_attr((string) $tailor['user_id']); ?>">
                                                <?php echo esc_html($tailor['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label for="measurement_profile_id">Profil de mesures</label>
                                    <select id="measurement_profile_id" name="measurement_profile_id">
                                        <option value="">Je renseignerai mes mesures plus tard</option>
                                        <?php foreach ($measurements as $measurement) : ?>
                                            <option value="<?php echo esc_attr((string) $measurement['id']); ?>">
                                                <?php echo esc_html((string) $measurement['profil_nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label for="model_source_type">Modele souhaite</label>
                                    <select id="model_source_type" name="model_source_type">
                                        <option value="site_model">Modele vu sur le site</option>
                                        <option value="upload">Photo / modele externe</option>
                                        <option value="reference">Description libre</option>
                                    </select>
                                </p>

                                <p>
                                    <label for="model_reference">Reference ou URL du modele</label>
                                    <input id="model_reference" name="model_reference" type="text" placeholder="Lien, nom du modele ou courte description">
                                </p>
                            </div>

                            <p>
                                <label for="model_image">Photo du modele</label>
                                <input id="model_image" name="model_image" type="file" accept="image/*">
                            </p>

                            <p>
                                <label for="customer_notes">Precisions pour le couturier</label>
                                <textarea id="customer_notes" name="customer_notes" rows="5" placeholder="coupe souhaitee, occasion, delai espere, details utiles"></textarea>
                            </p>

                            <div class="yombal-form__actions">
                                <button type="submit" class="yombal-button yombal-button--accent">Envoyer au couturier</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_tailor_requests(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour consulter vos demandes couture.</div></div>';
        }

        $user_id = get_current_user_id();
        $type = Roles::detect_partner_type($user_id);
        if (! in_array($type, [Roles::TYPE_TAILOR, Roles::TYPE_HYBRID], true)) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Cet espace est reserve aux partenaires couturiers.</div></div>';
        }

        $requests = self::get_tailor_requests($user_id);

        ob_start();
        ?>
        <div class="yombal-ui yombal-tailor-requests yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Atelier couture</span>
                <h1>Demandes couture a traiter</h1>
                <p>Consultez les demandes en attente, confirmez celles que vous acceptez ou indiquez la quantite de tissu supplementaire necessaire.</p>
            </section>
            <?php if (! $requests) : ?>
                <div class="yombal-empty-state">Aucune demande couture assignee pour le moment.</div>
            <?php else : ?>
                <?php foreach ($requests as $request) : ?>
                    <article class="yombal-card yombal-request-card">
                        <div class="yombal-card__header">
                            <div class="yombal-stack">
                                <h2 class="yombal-section-title">Demande #<?php echo esc_html((string) $request['id']); ?></h2>
                                <div class="yombal-inline-meta">
                                    <span>Client #<?php echo esc_html((string) $request['customer_id']); ?></span>
                                    <span>Expire le: <?php echo esc_html((string) $request['expires_at']); ?></span>
                                </div>
                            </div>
                            <span class="yombal-badge <?php echo esc_attr(self::status_badge_class((string) $request['status'])); ?>">
                                <?php echo esc_html(Couture_Requests::get_status_label((string) $request['status'])); ?>
                            </span>
                        </div>
                        <?php if (! empty($request['model_reference'])) : ?>
                            <p><strong>Modele:</strong> <?php echo esc_html((string) $request['model_reference']); ?></p>
                        <?php endif; ?>
                        <?php if (! empty($request['customer_notes'])) : ?>
                            <div class="yombal-prose"><?php echo wp_kses_post(wpautop((string) $request['customer_notes'])); ?></div>
                        <?php endif; ?>
                        <p>Paiement: <strong><?php echo (int) $request['payment_unlocked'] === 1 ? 'accessible' : 'en attente'; ?></strong></p>

                        <?php if (in_array($request['status'], [Couture_Requests::STATUS_PENDING_TAILOR_REVIEW, Couture_Requests::STATUS_NEEDS_MORE_FABRIC], true)) : ?>
                            <div class="yombal-grid yombal-grid--two">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="yombal-card yombal-card--soft yombal-form">
                                    <?php wp_nonce_field('yombal_approve_couture_request_' . (int) $request['id']); ?>
                                    <input type="hidden" name="action" value="yombal_approve_couture_request">
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $request['id']); ?>">
                                    <h3 class="yombal-section-title">Valider la confection</h3>
                                    <p>
                                        <label for="couture_price_<?php echo esc_attr((string) $request['id']); ?>">Prix de la confection</label>
                                        <input id="couture_price_<?php echo esc_attr((string) $request['id']); ?>" name="couture_price" type="number" min="0" step="0.01">
                                    </p>
                                    <div class="yombal-form__actions">
                                        <button type="submit" class="yombal-button yombal-button--accent">Confirmer la demande</button>
                                    </div>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="yombal-card yombal-form">
                                    <?php wp_nonce_field('yombal_require_more_fabric_' . (int) $request['id']); ?>
                                    <input type="hidden" name="action" value="yombal_require_more_fabric">
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $request['id']); ?>">
                                    <h3 class="yombal-section-title">Demander plus de tissu</h3>
                                    <p>
                                        <label for="required_fabric_qty_<?php echo esc_attr((string) $request['id']); ?>">Quantite de tissu necessaire</label>
                                        <input id="required_fabric_qty_<?php echo esc_attr((string) $request['id']); ?>" name="required_fabric_qty" type="number" min="0" step="0.01" required>
                                    </p>
                                    <p>
                                        <label for="tailor_response_<?php echo esc_attr((string) $request['id']); ?>">Message pour le client</label>
                                        <textarea id="tailor_response_<?php echo esc_attr((string) $request['id']); ?>" name="tailor_response" rows="4"></textarea>
                                    </p>
                                    <div class="yombal-form__actions">
                                        <button type="submit" class="yombal-button yombal-button--secondary">Signaler un besoin de tissu</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function handle_create_request(): void {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        check_admin_referer('yombal_create_couture_request');

        $tailor_user_id = isset($_POST['tailor_user_id']) ? (int) $_POST['tailor_user_id'] : 0;
        if ($tailor_user_id <= 0 || ! self::is_tailor_available($tailor_user_id)) {
            wp_die('Couturier invalide.');
        }

        $measurement_profile_id = isset($_POST['measurement_profile_id']) ? (int) $_POST['measurement_profile_id'] : 0;
        if ($measurement_profile_id > 0 && ! Measurements::get_measurement($measurement_profile_id, get_current_user_id())) {
            wp_die('Profil de mesures invalide.');
        }

        $attachment_id = self::handle_model_upload();
        $request_id = Couture_Requests::create([
            'customer_id' => get_current_user_id(),
            'tailor_user_id' => $tailor_user_id,
            'measurement_profile_id' => $measurement_profile_id > 0 ? $measurement_profile_id : null,
            'model_source_type' => sanitize_key((string) ($_POST['model_source_type'] ?? 'reference')),
            'model_reference' => sanitize_text_field((string) ($_POST['model_reference'] ?? '')),
            'model_attachment_id' => $attachment_id > 0 ? $attachment_id : null,
            'customer_notes' => sanitize_textarea_field((string) ($_POST['customer_notes'] ?? '')),
            'cart_snapshot' => self::cart_snapshot(),
            'fabric_requirements' => self::fabric_requirements_snapshot(),
        ]);

        if ($request_id <= 0) {
            wp_die('Impossible de creer la demande couture.');
        }

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('yombal_checkout_mode', 'fabric_plus_couture');
            WC()->session->set('yombal_couture_request_id', $request_id);
        }

        $redirect_url = add_query_arg('request_id', (string) $request_id, wp_get_referer() ?: home_url('/'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function handle_approve_request(): void {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        check_admin_referer('yombal_approve_couture_request_' . $request_id);

        $request = Couture_Requests::get($request_id);
        if (! $request || (int) $request['tailor_user_id'] !== get_current_user_id()) {
            wp_die('Acces refuse.');
        }

        $price = isset($_POST['couture_price']) && $_POST['couture_price'] !== '' ? (float) $_POST['couture_price'] : null;
        if ($price === null || $price <= 0) {
            wp_die('Le prix de la confection est necessaire pour ouvrir le paiement au client.');
        }

        Couture_Requests::approve($request_id, get_current_user_id(), $price);

        wp_safe_redirect(wp_get_referer() ?: home_url('/'));
        exit;
    }

    public static function handle_require_more_fabric(): void {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        check_admin_referer('yombal_require_more_fabric_' . $request_id);

        $request = Couture_Requests::get($request_id);
        if (! $request || (int) $request['tailor_user_id'] !== get_current_user_id()) {
            wp_die('Acces refuse.');
        }

        $required_qty = isset($_POST['required_fabric_qty']) ? (float) $_POST['required_fabric_qty'] : 0.0;
        if ($required_qty <= 0) {
            wp_die('Quantite invalide.');
        }

        Couture_Requests::mark_requires_more_fabric(
            $request_id,
            get_current_user_id(),
            $required_qty,
            sanitize_textarea_field((string) ($_POST['tailor_response'] ?? ''))
        );

        wp_safe_redirect(wp_get_referer() ?: home_url('/'));
        exit;
    }

    public static function handle_choose_fabric_only(): void {
        check_admin_referer('yombal_choose_fabric_only');

        if (function_exists('WC') && WC()->session) {
            WC()->session->__unset('yombal_couture_request_id');
            WC()->session->set('yombal_checkout_mode', 'fabric_only');
        }

        wp_safe_redirect(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'));
        exit;
    }

    private static function render_request_summary(array $request, string $checkout_url): string {
        $status_labels = [
            Couture_Requests::STATUS_PENDING_TAILOR_REVIEW => Couture_Requests::get_status_label(Couture_Requests::STATUS_PENDING_TAILOR_REVIEW),
            Couture_Requests::STATUS_APPROVED => Couture_Requests::get_status_label(Couture_Requests::STATUS_APPROVED),
            Couture_Requests::STATUS_PAYMENT_COMPLETED => Couture_Requests::get_status_label(Couture_Requests::STATUS_PAYMENT_COMPLETED),
            Couture_Requests::STATUS_NEEDS_MORE_FABRIC => Couture_Requests::get_status_label(Couture_Requests::STATUS_NEEDS_MORE_FABRIC),
            Couture_Requests::STATUS_EXPIRED => Couture_Requests::get_status_label(Couture_Requests::STATUS_EXPIRED),
            Couture_Requests::STATUS_CANCELLED => Couture_Requests::get_status_label(Couture_Requests::STATUS_CANCELLED),
        ];

        ob_start();
        ?>
        <section class="yombal-card yombal-request-summary">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title">Votre demande couture #<?php echo esc_html((string) $request['id']); ?></h2>
                    <div class="yombal-inline-meta">
                        <span>Reponse attendue avant le: <?php echo esc_html((string) $request['expires_at']); ?></span>
                    </div>
                </div>
                <span class="yombal-badge <?php echo esc_attr(self::status_badge_class((string) $request['status'])); ?>">
                    <?php echo esc_html($status_labels[$request['status']] ?? (string) $request['status']); ?>
                </span>
            </div>
            <?php if (! empty($request['tailor_response'])) : ?>
                <div class="yombal-prose"><?php echo wp_kses_post(wpautop((string) $request['tailor_response'])); ?></div>
            <?php endif; ?>
            <?php if (! empty($request['required_fabric_qty'])) : ?>
                <p>Quantite demandee par le couturier: <strong><?php echo esc_html((string) $request['required_fabric_qty']); ?></strong></p>
            <?php endif; ?>
            <?php if (! empty($request['couture_price'])) : ?>
                <p>Montant de la confection: <strong><?php echo wp_kses_post(wc_price((float) $request['couture_price'])); ?></strong></p>
            <?php endif; ?>
            <?php if ((int) $request['payment_unlocked'] === 1 && $request['status'] === Couture_Requests::STATUS_APPROVED) : ?>
                <div class="yombal-form__actions">
                    <a href="<?php echo esc_url($checkout_url); ?>" class="yombal-button yombal-button--accent">Passer au paiement</a>
                </div>
            <?php elseif ($request['status'] === Couture_Requests::STATUS_PAYMENT_COMPLETED) : ?>
                <div class="yombal-empty-state">Le paiement a deja ete confirme pour cette demande.</div>
            <?php else : ?>
                <div class="yombal-empty-state">Le paiement s ouvrira des que votre couturier aura confirme la demande.</div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function get_available_tailors(): array {
        global $wpdb;

        $statuses = implode("', '", array_map('esc_sql', Profile_Service::public_statuses()));
        $rows = $wpdb->get_results(
            'SELECT user_id, display_name, store_name, city, partner_type, profile_status
            FROM ' . Installer::table_name('yombal_partner_profiles') . "
            WHERE partner_type IN ('tailor', 'hybrid')
                AND profile_status IN ('{$statuses}')
            ORDER BY updated_at DESC",
            ARRAY_A
        );

        $items = [];
        foreach ((array) $rows as $row) {
            if (Fixtures::is_fixture_user((int) $row['user_id'])) {
                continue;
            }
            $label = (string) ($row['store_name'] ?: $row['display_name']);
            if (! empty($row['city'])) {
                $label .= ' - ' . (string) $row['city'];
            }
            $items[(int) $row['user_id']] = [
                'user_id' => (int) $row['user_id'],
                'label' => $label,
            ];
        }

        foreach (get_users(['role__in' => [Roles::ROLE_TAILOR, Roles::ROLE_HYBRID], 'fields' => ['ID', 'display_name']]) as $user) {
            if (isset($items[(int) $user->ID])) {
                continue;
            }
            if (Fixtures::is_fixture_user((int) $user->ID) || ! Profile_Service::has_public_visibility((int) $user->ID)) {
                continue;
            }
            $profile = Profile_Service::get_profile((int) $user->ID);
            $items[(int) $user->ID] = [
                'user_id' => (int) $user->ID,
                'label' => (string) ($profile['store_name'] ?? $user->display_name),
            ];
        }

        return array_values($items);
    }

    private static function is_tailor_available(int $user_id): bool {
        foreach (self::get_available_tailors() as $tailor) {
            if ((int) $tailor['user_id'] === $user_id) {
                return true;
            }
        }

        return false;
    }

    private static function current_request_id(): int {
        if (! function_exists('WC') || ! WC()->session) {
            return 0;
        }

        return (int) WC()->session->get('yombal_couture_request_id', 0);
    }

    private static function cart_has_items(): bool {
        return function_exists('WC') && WC()->cart && ! WC()->cart->is_empty();
    }

    private static function cart_snapshot(): array {
        if (! self::cart_has_items()) {
            return [];
        }

        $snapshot = [];
        foreach (WC()->cart->get_cart() as $item) {
            $product_id = (int) ($item['product_id'] ?? 0);
            $snapshot[] = [
                'product_id' => $product_id,
                'name' => get_the_title($product_id),
                'quantity' => (float) ($item['quantity'] ?? 0),
            ];
        }

        return $snapshot;
    }

    private static function fabric_requirements_snapshot(): array {
        $requirements = [];
        foreach (self::cart_snapshot() as $row) {
            $requirements[] = [
                'product_id' => (int) $row['product_id'],
                'name' => (string) $row['name'],
                'selected_qty' => (float) $row['quantity'],
            ];
        }

        return $requirements;
    }

    private static function get_tailor_requests(int $user_id): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_couture_requests') . ' WHERE tailor_user_id = %d ORDER BY created_at DESC LIMIT 30',
                $user_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private static function handle_model_upload(): int {
        if (empty($_FILES['model_image']['name'])) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('model_image', 0);
        if (is_wp_error($attachment_id)) {
            wp_die($attachment_id->get_error_message());
        }

        return (int) $attachment_id;
    }

    private static function status_badge_class(string $status): string {
        return match ($status) {
            Couture_Requests::STATUS_APPROVED, Couture_Requests::STATUS_PAYMENT_COMPLETED => 'yombal-badge--success',
            Couture_Requests::STATUS_NEEDS_MORE_FABRIC => 'yombal-badge--accent',
            Couture_Requests::STATUS_EXPIRED, Couture_Requests::STATUS_CANCELLED => 'yombal-badge--danger',
            default => 'yombal-badge--muted',
        };
    }
}
