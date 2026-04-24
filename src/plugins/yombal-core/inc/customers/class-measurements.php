<?php

declare(strict_types=1);

namespace Yombal\Core\Customers;

use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;

if (! defined('ABSPATH')) {
    exit;
}

final class Measurements {
    public static function boot(): void {
        add_shortcode('yombal_measurements', [self::class, 'render_page']);
        add_shortcode('yombal_mes_mesures', [self::class, 'render_page']);

        add_action('woocommerce_before_order_notes', [self::class, 'render_checkout_selector']);
        add_action('woocommerce_checkout_create_order', [self::class, 'store_order_meta'], 10, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', [self::class, 'render_admin_meta']);
        add_action('admin_post_yombal_delete_measurement', [self::class, 'handle_delete']);
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour gerer vos mesures.</div></div>';
        }

        $user_id = get_current_user_id();
        $message = self::handle_form_submission($user_id);
        $measurements = self::get_user_measurements($user_id);
        $editing = self::get_measurement((int) ($_GET['edit_measurement'] ?? 0), $user_id);

        ob_start();
        ?>
        <div class="yombal-ui yombal-measurements yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Mes mesures</span>
                <h1>Mes profils de mesures</h1>
                <p>Enregistrez vos mesures une seule fois pour retrouver plus facilement vos informations lors de vos prochaines commandes.</p>
            </section>
            <?php if ($message) : ?>
                <div class="woocommerce-message"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <section class="yombal-card">
                <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title">Profils disponibles</h2>
                        <div class="yombal-card__meta">Chaque profil peut correspondre a une tenue, une occasion ou une personne.</div>
                    </div>
                </div>
                <?php if ($measurements) : ?>
                    <div class="yombal-grid yombal-grid--three">
                        <?php foreach ($measurements as $measurement) : ?>
                            <article class="yombal-card yombal-card--soft">
                                <div class="yombal-card__header">
                                    <div class="yombal-stack">
                                        <h3 class="yombal-section-title"><?php echo esc_html((string) $measurement['profil_nom']); ?></h3>
                                        <div class="yombal-card__meta"><?php echo esc_html((string) ($measurement['occasion'] ?: 'General')); ?></div>
                                    </div>
                                </div>
                                <ul class="yombal-list">
                                    <?php foreach (self::field_labels() as $field => $label) : ?>
                                        <?php if (! empty($measurement[$field])) : ?>
                                            <li><?php echo esc_html($label . ': ' . $measurement[$field] . ' cm'); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="yombal-actions">
                                    <a href="<?php echo esc_url(add_query_arg('edit_measurement', (string) $measurement['id'])); ?>">Modifier</a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=yombal_delete_measurement&id=' . (int) $measurement['id']), 'yombal_delete_measurement_' . (int) $measurement['id'])); ?>">Supprimer</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="yombal-empty-state">Aucun profil de mesures enregistre pour le moment.</div>
                <?php endif; ?>
            </section>

            <section class="yombal-card yombal-card--soft">
                <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title"><?php echo $editing ? 'Modifier un profil' : 'Ajouter un profil'; ?></h2>
                        <div class="yombal-card__meta">Ces informations pourront etre reutilisees quand vous preparerez une confection.</div>
                    </div>
                </div>
                <form method="post" class="yombal-form">
                <?php wp_nonce_field('yombal_save_measurement_' . $user_id); ?>
                <input type="hidden" name="yombal_measurement_id" value="<?php echo esc_attr((string) ($editing['id'] ?? 0)); ?>">
                <div class="yombal-field-grid">
                    <p>
                        <label for="profil_nom">Nom du profil</label>
                        <input id="profil_nom" name="profil_nom" type="text" value="<?php echo esc_attr((string) ($editing['profil_nom'] ?? '')); ?>" required>
                    </p>
                    <p>
                        <label for="occasion">Occasion</label>
                        <input id="occasion" name="occasion" type="text" value="<?php echo esc_attr((string) ($editing['occasion'] ?? '')); ?>">
                    </p>
                </div>
                <div class="yombal-field-grid">
                    <?php foreach (self::field_labels() as $field => $label) : ?>
                        <p>
                            <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label>
                            <input id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" type="number" min="0" step="0.5" value="<?php echo esc_attr((string) ($editing[$field] ?? '')); ?>">
                        </p>
                    <?php endforeach; ?>
                </div>
                <p>
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo esc_textarea((string) ($editing['notes'] ?? '')); ?></textarea>
                </p>
                <div class="yombal-form__actions">
                    <button type="submit" name="yombal_save_measurement" value="1" class="yombal-button yombal-button--accent">Enregistrer</button>
                </div>
                </form>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_checkout_selector(\WC_Checkout $checkout): void {
        if (! is_user_logged_in() || ! self::cart_requires_measurements()) {
            return;
        }

        $measurements = self::get_user_measurements(get_current_user_id());
        if (! $measurements) {
            echo '<div class="woocommerce-info">Vous n avez pas encore de profil de mesures. Ajoutez-en un pour aider votre couturier a bien preparer votre tenue.</div>';
            return;
        }

        echo '<div id="yombal-measurement-checkout" class="yombal-ui yombal-card yombal-card--soft"><h3 class="yombal-section-title">Mesures</h3><p>Choisissez le profil de mesures a partager avec votre couturier pour cette commande.</p><select name="yombal_measurement_id">';
        echo '<option value="">Choisir un profil</option>';
        foreach ($measurements as $measurement) {
            $label = (string) $measurement['profil_nom'];
            if (! empty($measurement['occasion'])) {
                $label .= ' (' . $measurement['occasion'] . ')';
            }
            echo '<option value="' . esc_attr((string) $measurement['id']) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></div>';
    }

    public static function store_order_meta(\WC_Order $order, array $data): void {
        $measurement_id = isset($_POST['yombal_measurement_id']) ? (int) $_POST['yombal_measurement_id'] : 0;
        if ($measurement_id <= 0 || ! is_user_logged_in()) {
            return;
        }

        $measurement = self::get_measurement($measurement_id, get_current_user_id());
        if (! $measurement) {
            return;
        }

        $order->update_meta_data('_yombal_measurement_id', $measurement_id);
        $order->update_meta_data('_yombal_measurement_profile', (string) $measurement['profil_nom']);
        $order->update_meta_data('_yombal_measurement_data', wp_json_encode($measurement));
    }

    public static function render_admin_meta(\WC_Order $order): void {
        $data = (string) $order->get_meta('_yombal_measurement_data');
        if ($data === '') {
            return;
        }

        $measurement = json_decode($data, true);
        if (! is_array($measurement)) {
            return;
        }

        echo '<div class="order_data_column"><h4>Mesures Yombal</h4><ul>';
        foreach (self::field_labels() as $field => $label) {
            if (! empty($measurement[$field])) {
                echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html((string) $measurement[$field]) . ' cm</li>';
            }
        }
        echo '</ul></div>';
    }

    public static function handle_delete(): void {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $measurement_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($measurement_id <= 0) {
            wp_safe_redirect(wp_get_referer() ?: home_url('/'));
            exit;
        }

        check_admin_referer('yombal_delete_measurement_' . $measurement_id);

        global $wpdb;
        $wpdb->delete(
            Installer::table_name('yombal_mesures'),
            [
                'id' => $measurement_id,
                'user_id' => get_current_user_id(),
            ],
            ['%d', '%d']
        );

        wp_safe_redirect(remove_query_arg(['edit_measurement'], wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function get_measurement(int $measurement_id, int $user_id): ?array {
        global $wpdb;

        if ($measurement_id <= 0 || $user_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_mesures') . ' WHERE id = %d AND user_id = %d LIMIT 1',
                $measurement_id,
                $user_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function get_user_measurements(int $user_id): array {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_mesures') . ' WHERE user_id = %d ORDER BY updated_at DESC',
                $user_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    private static function handle_form_submission(int $user_id): string {
        if (! isset($_POST['yombal_save_measurement'])) {
            return '';
        }

        check_admin_referer('yombal_save_measurement_' . $user_id);

        global $wpdb;

        $measurement_id = isset($_POST['yombal_measurement_id']) ? (int) $_POST['yombal_measurement_id'] : 0;
        $data = [
            'user_id' => $user_id,
            'profil_nom' => sanitize_text_field((string) ($_POST['profil_nom'] ?? 'Mon profil')),
            'occasion' => sanitize_text_field((string) ($_POST['occasion'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
        ];

        foreach (array_keys(self::field_labels()) as $field) {
            $data[$field] = ($_POST[$field] ?? '') !== '' ? (float) $_POST[$field] : null;
        }

        if ($measurement_id > 0) {
            $wpdb->update(
                Installer::table_name('yombal_mesures'),
                $data,
                ['id' => $measurement_id, 'user_id' => $user_id]
            );
            return 'Profil de mesures mis a jour.';
        }

        $wpdb->insert(Installer::table_name('yombal_mesures'), $data);

        return 'Profil de mesures enregistre.';
    }

    private static function cart_requires_measurements(): bool {
        if (! function_exists('WC') || ! WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            $product_id = (int) ($item['product_id'] ?? 0);
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
            if (array_intersect(['modeles-couture', 'couture'], (array) $categories)) {
                return true;
            }
        }

        return false;
    }

    private static function field_labels(): array {
        return [
            'poitrine' => 'Poitrine',
            'taille' => 'Taille',
            'hanches' => 'Hanches',
            'epaules' => 'Epaules',
            'longueur_buste' => 'Longueur buste',
            'longueur_robe' => 'Longueur robe',
            'longueur_manche' => 'Longueur manche',
            'tour_bras' => 'Tour bras',
            'encolure' => 'Encolure',
        ];
    }
}
