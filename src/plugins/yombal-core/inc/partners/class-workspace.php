<?php

declare(strict_types=1);

namespace Yombal\Core\Partners;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Catalog\Product_Editor;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Support\Ticket_Center;
use Yombal\Core\UI\Dashboard_Shell;
use Yombal\Core\Workflows\Couture_Portal;

if (! defined('ABSPATH')) {
    exit;
}

final class Workspace {
    public static function boot(): void {
        add_shortcode('yombal_partner_workspace', [self::class, 'render_page']);
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            ob_start();
            ?>
            <div class="yombal-ui yombal-shell yombal-partner-dashboard">
                <?php echo Public_Shell::render_identity_strip(); ?>
                <section class="yombal-hero">
                    <span class="yombal-eyebrow">Espace partenaire</span>
                    <h1>Espace partenaire Yombal</h1>
                    <p>Connectez-vous pour suivre votre activite, gerer vos produits et retrouver vos echanges professionnels sur Yombal.</p>
                </section>
                <div class="yombal-card yombal-card--soft yombal-empty-state">
                    Vous devez etre connecte pour acceder a votre espace partenaire.
                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(self::login_url(self::get_page_url() ?: home_url('/espace-partenaire-yombal/'))); ?>">Se connecter</a>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        $user_id = get_current_user_id();
        $access_state = Profile_Service::access_state($user_id);
        if ($access_state === 'none') {
            ob_start();
            ?>
            <div class="yombal-ui yombal-shell yombal-partner-dashboard">
                <?php echo Public_Shell::render_identity_strip(); ?>
                <section class="yombal-hero">
                    <span class="yombal-eyebrow">Espace partenaire</span>
                    <h1>Un espace reserve aux partenaires</h1>
                    <p>Cette zone est concue pour les vendeurs de tissus, les tailleurs et les ateliers valides sur Yombal.</p>
                </section>
                <div class="yombal-card yombal-card--soft yombal-empty-state">
                    Votre compte n est pas encore configure comme partenaire.
                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire-yombal/')); ?>">Devenir partenaire</a>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        if ($access_state === 'pending_review' || $access_state === 'rejected') {
            $copy = $access_state === 'rejected'
                ? [
                    'title' => 'Votre candidature n est pas encore validee',
                    'message' => 'Votre compte partenaire n est pas accessible publiquement ni operationnel pour le moment. Vous pouvez mettre a jour votre candidature ou contacter Yombal.',
                    'cta' => 'Mettre a jour ma candidature',
                ]
                : [
                    'title' => 'Votre candidature est en cours de verification',
                    'message' => 'Votre espace partenaire est bien reserve, mais l equipe Yombal doit encore verifier votre profil avant l ouverture complete des outils boutique et couture.',
                    'cta' => 'Completer ma candidature',
                ];

            ob_start();
            ?>
            <div class="yombal-ui yombal-shell yombal-partner-dashboard">
                <?php echo Public_Shell::render_identity_strip(); ?>
                <section class="yombal-hero">
                    <span class="yombal-eyebrow">Espace partenaire</span>
                    <h1><?php echo esc_html($copy['title']); ?></h1>
                    <p><?php echo esc_html($copy['message']); ?></p>
                </section>
                <div class="yombal-card yombal-card--soft yombal-empty-state">
                    <?php echo esc_html($copy['message']); ?>
                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire-yombal/')); ?>">
                        <?php echo esc_html($copy['cta']); ?>
                    </a>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';
        $tabs = self::tabs($user_id);

        if (! isset($tabs[$tab])) {
            $tab = 'overview';
        }

        $profile = Profile_Service::get_profile($user_id);
        $sidebar_title = (string) ($profile['store_name'] ?? $profile['display_name'] ?? 'Yombal');
        $sidebar_meta = 'Gerez votre activite, suivez vos demandes et gardez vos informations a jour.';

        return Dashboard_Shell::render_layout([
            'sidebar_title' => $sidebar_title,
            'sidebar_meta' => $sidebar_meta,
            'sidebar_items' => self::sidebar_items($user_id, $tab),
            'content' => self::render_tab($tab, $user_id),
        ]);
    }

    public static function get_page_url(): string {
        return Page_Provisioner::get_page_url('espace-partenaire-yombal');
    }

    private static function login_url(string $target): string {
        return add_query_arg('redirect_to', $target, home_url('/connexion/'));
    }

    private static function render_tab(string $tab, int $user_id): string {
        return match ($tab) {
            'products' => Product_Editor::render_products_page(),
            'tailor-requests' => Couture_Portal::render_tailor_requests(),
            'messages' => Message_Center::render_page(),
            'support' => Ticket_Center::render_page(),
            'notifications' => Notification_Center::render_page(),
            'profile' => Registration::render_page(),
            default => Profile_Service::render_partner_dashboard(),
        };
    }

    private static function tabs(int $user_id): array {
        $tabs = [
            'overview' => 'Mon espace',
            'products' => 'Produits',
            'messages' => sprintf('Messages (%d)', Message_Center::count_unread_for_user($user_id)),
            'support' => sprintf('Aide (%d)', Ticket_Center::count_open_for_user($user_id)),
            'notifications' => sprintf('Notifications (%d)', Notification_Center::count_user_notifications($user_id, 'pending')),
            'profile' => 'Mon profil',
        ];

        $type = Roles::detect_partner_type($user_id);
        if (in_array($type, [Roles::TYPE_TAILOR, Roles::TYPE_HYBRID], true)) {
            $tabs['tailor-requests'] = 'Demandes clients';
        }

        return $tabs;
    }

    private static function sidebar_items(int $user_id, string $active_tab): array {
        $items = [];
        $base_url = self::get_page_url() ?: home_url('/espace-partenaire-yombal/');
        foreach (self::tabs($user_id) as $key => $label) {
            $items[] = [
                'label' => $label,
                'url' => add_query_arg('tab', $key, $base_url),
                'active' => $active_tab === $key,
            ];
        }

        $items[] = [
            'label' => 'Deconnexion',
            'url' => wp_logout_url(home_url('/')),
            'active' => false,
            'modifier' => 'logout',
        ];

        return $items;
    }
}
