<?php

declare(strict_types=1);

namespace Yombal\Core\Partners;

use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Notifications\Notification_Center;

if (! defined('ABSPATH')) {
    exit;
}

final class Registration {
    public static function boot(): void {
        add_shortcode('yombal_partner_registration', [self::class, 'render_page']);
        add_shortcode('yombal_partner_application', [self::class, 'render_page']);
        add_action('admin_post_yombal_update_partner_profile_status', [self::class, 'handle_admin_status_update']);
    }

    public static function render_page(): string {
        $message = self::handle_submission();
        $user_id = get_current_user_id();
        $profile = $user_id > 0 ? Profile_Service::get_profile($user_id) : null;
        $status = (string) ($profile['profile_status'] ?? '');
        $requested_partner_type = sanitize_key((string) ($_GET['partner_type'] ?? ''));
        $default_partner_type = array_key_exists($requested_partner_type, self::partner_type_labels()) ? $requested_partner_type : Roles::TYPE_FABRIC_VENDOR;

        ob_start();
        ?>
        <div class="yombal-ui yombal-partner-registration yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Rejoindre Yombal</span>
                <h1>Devenir partenaire Yombal</h1>
                <p style="margin:0;">Rejoignez une marketplace pensee pour les vendeurs de tissus, les couturiers et les ateliers qui veulent bien presenter leur savoir-faire.</p>
            </section>

            <?php if ($message !== '') : ?>
                <div class="woocommerce-message"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <?php if ($profile) : ?>
                <section class="yombal-card yombal-card--soft">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Votre profil actuel</h2>
                            <div class="yombal-card__meta"><?php echo esc_html((string) ($profile['store_name'] ?? $profile['display_name'] ?? 'Partenaire')); ?></div>
                        </div>
                        <span class="yombal-badge <?php echo esc_attr(self::status_badge_class($status)); ?>">
                            <?php echo esc_html(self::status_label($status !== '' ? $status : 'pending_review')); ?>
                        </span>
                    </div>
                    <div class="yombal-inline-meta">
                        <span>Activite: <?php echo esc_html(self::partner_type_labels()[(string) ($profile['partner_type'] ?? '')] ?? 'A preciser'); ?></span>
                        <span>Compte associe: <?php echo esc_html((string) ($profile['display_name'] ?? '')); ?></span>
                    </div>
                </section>
            <?php endif; ?>

            <section class="yombal-card">
                <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title">Candidature partenaire</h2>
                        <div class="yombal-card__meta">Parlez-nous de votre activite via un parcours en etapes plus clair pour orienter votre boutique ou votre atelier.</div>
                    </div>
                    <span class="yombal-badge yombal-badge--accent">Reponse sous validation</span>
                </div>

                <form method="post" class="yombal-form yombal-step-form" data-yombal-step-form="partner-registration">
                    <?php wp_nonce_field('yombal_partner_registration'); ?>
                    <div class="yombal-stepper" aria-label="Etapes de candidature partenaire">
                        <div class="yombal-stepper__item is-active" data-step-marker="1"><span>1</span><strong>Compte et activite</strong></div>
                        <div class="yombal-stepper__item" data-step-marker="2"><span>2</span><strong>Localisation et offre</strong></div>
                        <div class="yombal-stepper__item" data-step-marker="3"><span>3</span><strong>Presentation finale</strong></div>
                    </div>

                    <fieldset class="yombal-step-pane is-active" data-step-pane="1">
                        <legend>Compte et activite</legend>
                        <?php if (! is_user_logged_in()) : ?>
                            <div class="yombal-field-grid">
                                <p>
                                    <label for="yombal_email">Email</label>
                                    <input id="yombal_email" name="email" type="email" autocomplete="email" inputmode="email" required>
                                </p>
                                <p>
                                    <label for="yombal_password">Mot de passe</label>
                                    <input id="yombal_password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="yombal-field-grid">
                            <p>
                                <label for="yombal_display_name">Nom affiche</label>
                                <input id="yombal_display_name" name="display_name" type="text" autocomplete="name" value="<?php echo esc_attr((string) ($profile['display_name'] ?? wp_get_current_user()->display_name ?? '')); ?>" required>
                            </p>
                            <p>
                                <label for="yombal_store_name">Nom boutique / atelier</label>
                                <input id="yombal_store_name" name="store_name" type="text" autocomplete="organization" value="<?php echo esc_attr((string) ($profile['store_name'] ?? '')); ?>" required>
                            </p>
                            <p>
                                <label for="yombal_partner_type">Type de partenaire</label>
                                <select id="yombal_partner_type" name="partner_type" required>
                                    <?php foreach (self::partner_type_labels() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($profile['partner_type'] ?? $default_partner_type), $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        </div>
                        <div class="yombal-form__actions">
                            <button type="button" class="yombal-button yombal-button--accent" data-step-next="1">Continuer</button>
                        </div>
                    </fieldset>

                    <fieldset class="yombal-step-pane" data-step-pane="2" hidden>
                        <legend>Localisation et offre</legend>
                        <div class="yombal-field-grid">
                            <p>
                                <label for="yombal_city">Ville</label>
                                <input id="yombal_city" name="city" type="text" autocomplete="address-level2" value="<?php echo esc_attr((string) ($profile['city'] ?? '')); ?>">
                            </p>
                            <p>
                                <label for="yombal_phone">Telephone</label>
                                <input id="yombal_phone" name="phone" type="text" autocomplete="tel" inputmode="tel" value="<?php echo esc_attr((string) ($profile['phone'] ?? '')); ?>">
                            </p>
                        </div>

                        <div class="yombal-field-grid yombal-field-grid--wide">
                            <p>
                                <label for="yombal_specialties">Specialites</label>
                                <input id="yombal_specialties" name="specialties" type="text" value="<?php echo esc_attr(self::implode_profile_field($profile['specialties'] ?? '')); ?>" placeholder="ex: bazin, robe soiree, homme">
                            </p>

                            <p>
                                <label for="yombal_materials">Matieres / categories</label>
                                <input id="yombal_materials" name="materials" type="text" value="<?php echo esc_attr(self::implode_profile_field($profile['materials'] ?? '')); ?>" placeholder="ex: wax, lin, soie">
                            </p>
                        </div>
                        <div class="yombal-form__actions">
                            <button type="button" class="yombal-button yombal-button--secondary" data-step-prev="2">Retour</button>
                            <button type="button" class="yombal-button yombal-button--accent" data-step-next="2">Continuer</button>
                        </div>
                    </fieldset>

                    <fieldset class="yombal-step-pane" data-step-pane="3" hidden>
                        <legend>Presentation finale</legend>
                        <p>
                            <label for="yombal_biography">Presentation</label>
                            <textarea id="yombal_biography" name="biography" rows="5"><?php echo esc_textarea((string) ($profile['biography'] ?? '')); ?></textarea>
                        </p>

                        <div class="yombal-card yombal-card--soft">
                            <div class="yombal-stack">
                                <h3 class="yombal-section-title">Avant envoi</h3>
                                <div class="yombal-card__meta">Verifiez que votre activite, votre ville et vos specialites sont bien renseignes. Cela servira a mieux distinguer couture, tissus et profils hybrides dans le catalogue public.</div>
                            </div>
                        </div>

                        <div class="yombal-form__actions">
                            <button type="button" class="yombal-button yombal-button--secondary" data-step-prev="3">Retour</button>
                            <button type="submit" name="yombal_partner_registration_submit" value="1" class="yombal-button yombal-button--accent">Envoyer ma candidature</button>
                        </div>
                    </fieldset>
                </form>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function handle_submission(): string {
        if (! isset($_POST['yombal_partner_registration_submit'])) {
            return '';
        }

        check_admin_referer('yombal_partner_registration');

        $partner_type = sanitize_key((string) ($_POST['partner_type'] ?? ''));
        if (! array_key_exists($partner_type, self::partner_type_labels())) {
            return 'Type de partenaire invalide.';
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            $email = sanitize_email((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (! is_email($email) || email_exists($email)) {
                return 'Adresse email invalide ou deja utilisee.';
            }
            if (strlen($password) < 8) {
                return 'Le mot de passe doit contenir au moins 8 caracteres.';
            }

            $username = self::generate_username($email, (string) ($_POST['store_name'] ?? ''));
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                return $user_id->get_error_message();
            }

            wp_update_user([
                'ID' => $user_id,
                'display_name' => sanitize_text_field((string) ($_POST['display_name'] ?? $username)),
            ]);

            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        $user = get_user_by('id', $user_id);
        if (! $user) {
            return 'Impossible de charger le compte partenaire.';
        }

        self::assign_partner_role($user_id, $partner_type);

        $profile_data = [
            'user_id' => $user_id,
            'partner_type' => $partner_type,
            'profile_status' => 'pending_review',
            'display_name' => sanitize_text_field((string) ($_POST['display_name'] ?? $user->display_name)),
            'store_name' => sanitize_text_field((string) ($_POST['store_name'] ?? '')),
            'city' => sanitize_text_field((string) ($_POST['city'] ?? '')),
            'phone' => sanitize_text_field((string) ($_POST['phone'] ?? '')),
            'specialties' => wp_json_encode(self::parse_list((string) ($_POST['specialties'] ?? ''))),
            'materials' => wp_json_encode(self::parse_list((string) ($_POST['materials'] ?? ''))),
            'biography' => sanitize_textarea_field((string) ($_POST['biography'] ?? '')),
            'legacy_vendor_type' => null,
        ];

        self::upsert_profile($profile_data);

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $profile_data['display_name'],
        ]);

        Notification_Center::create(
            $user_id,
            'partner_application_received',
            'Candidature partenaire enregistree',
            'Votre candidature a bien ete recue. Notre equipe va la verifier avant ouverture de votre espace partenaire.'
        );

        return 'Votre candidature partenaire a bien ete envoyee. Nous reviendrons vers vous apres verification.';
    }

    private static function upsert_profile(array $profile_data): void {
        global $wpdb;

        $table = Installer::table_name('yombal_partner_profiles');
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", (int) $profile_data['user_id'])
        );

        if ($existing_id > 0) {
            $wpdb->update($table, $profile_data, ['id' => $existing_id]);
            return;
        }

        $wpdb->insert($table, $profile_data);
    }

    public static function handle_admin_status_update(): void {
        if (! current_user_can('yombal_manage_partners') && ! current_user_can('manage_woocommerce')) {
            wp_die('Acces refuse.');
        }

        $profile_id = isset($_GET['profile_id']) ? (int) $_GET['profile_id'] : 0;
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        if ($profile_id <= 0 || ! in_array($status, ['approved', 'rejected', 'pending_review'], true)) {
            wp_die('Statut partenaire invalide.');
        }

        check_admin_referer('yombal_update_partner_profile_status_' . $profile_id . '_' . $status);

        global $wpdb;

        $table = Installer::table_name('yombal_partner_profiles');
        $profile = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $profile_id),
            ARRAY_A
        );

        if (! is_array($profile)) {
            wp_die('Profil partenaire introuvable.');
        }

        $wpdb->update(
            $table,
            ['profile_status' => $status],
            ['id' => $profile_id]
        );

        $titles = [
            'approved' => 'Candidature partenaire validee',
            'rejected' => 'Candidature partenaire rejetee',
            'pending_review' => 'Candidature partenaire repassee en revue',
        ];

        $messages = [
            'approved' => 'Bonne nouvelle, votre profil partenaire est maintenant valide. Vous pouvez acceder a votre espace Yombal.',
            'rejected' => 'Votre candidature n a pas pu etre validee pour le moment. Vous pouvez reprendre contact avec notre equipe pour la completer.',
            'pending_review' => 'Votre candidature est toujours en cours de verification par notre equipe.',
        ];

        Notification_Center::create(
            (int) $profile['user_id'],
            'partner_application_' . $status,
            $titles[$status],
            $messages[$status],
            'partner_profile',
            $profile_id
        );

        wp_safe_redirect(remove_query_arg(['profile_id', 'status', '_wpnonce'], wp_get_referer() ?: admin_url('admin.php?page=yombal-core-partners')));
        exit;
    }

    private static function assign_partner_role(int $user_id, string $partner_type): void {
        $user = new \WP_User($user_id);
        $role = match ($partner_type) {
            Roles::TYPE_TAILOR => Roles::ROLE_TAILOR,
            Roles::TYPE_FABRIC_VENDOR => Roles::ROLE_FABRIC_VENDOR,
            Roles::TYPE_HYBRID => Roles::ROLE_HYBRID,
            default => '',
        };

        if ($role === '' || in_array($role, (array) $user->roles, true)) {
            return;
        }

        $user->add_role($role);
    }

    private static function generate_username(string $email, string $store_name): string {
        $seed = $store_name !== '' ? $store_name : strstr($email, '@', true);
        $candidate = sanitize_user((string) $seed, true);
        if ($candidate === '') {
            $candidate = 'yombal_partner';
        }

        $username = $candidate;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $candidate . $suffix;
            $suffix++;
        }

        return $username;
    }

    private static function parse_list(string $value): array {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, static fn (string $item): bool => $item !== '');

        return array_values(array_map('sanitize_text_field', $parts));
    }

    private static function implode_profile_field(string $value): string {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode(', ', array_map('strval', $decoded));
        }

        return $value;
    }

    private static function partner_type_labels(): array {
        return [
            Roles::TYPE_FABRIC_VENDOR => 'Vendeur de tissus',
            Roles::TYPE_TAILOR => 'Couturier',
            Roles::TYPE_HYBRID => 'Partenaire hybride',
        ];
    }

    private static function status_badge_class(string $status): string {
        return match ($status) {
            'approved' => 'yombal-badge--success',
            'rejected' => 'yombal-badge--danger',
            'legacy_imported' => 'yombal-badge--accent',
            'legacy' => 'yombal-badge--accent',
            default => 'yombal-badge--muted',
        };
    }

    private static function status_label(string $status): string {
        return match ($status) {
            'approved' => 'Valide',
            'rejected' => 'Non valide',
            'pending_review' => 'En verification',
            'legacy_imported' => 'A completer',
            'legacy' => 'A completer',
            default => 'En attente',
        };
    }
}
