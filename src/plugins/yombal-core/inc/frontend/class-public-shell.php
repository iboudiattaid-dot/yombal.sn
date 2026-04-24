<?php

declare(strict_types=1);

namespace Yombal\Core\Frontend;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Customers\Workspace as Customer_Workspace;
use Yombal\Core\Database\Installer;
use Yombal\Core\Partners\Public_Pages;
use Yombal\Core\Partners\Profile_Service;
use Yombal\Core\Partners\Roles;

if (! defined('ABSPATH')) {
    exit;
}

final class Public_Shell {
    private const PAGE_COPY = [
        'connexion' => [
            'eyebrow' => 'Bienvenue sur Yombal',
            'title' => 'Connectez-vous simplement',
            'intro' => 'Retrouvez votre espace, vos commandes, vos mesures et vos services sur mesure en quelques instants.',
        ],
        'panier' => [
            'eyebrow' => 'Votre selection',
            'title' => 'Votre panier Yombal',
            'intro' => 'Revoyez vos articles en toute tranquillite avant de confirmer la suite de votre commande.',
        ],
        'commander' => [
            'eyebrow' => 'Finalisation',
            'title' => 'Finaliser votre commande',
            'intro' => 'Verifiez vos informations et avancez vers un paiement simple et rassurant.',
        ],
        'checkout' => [
            'eyebrow' => 'Finalisation',
            'title' => 'Finaliser votre commande',
            'intro' => 'Verifiez vos informations et avancez vers un paiement simple et rassurant.',
        ],
        'a-propos' => [
            'eyebrow' => 'L univers Yombal',
            'title' => 'Une plateforme pensee pour la couture sur mesure',
            'intro' => 'Yombal rapproche clients, couturiers et vendeurs de tissus autour d une experience plus claire et plus humaine.',
        ],
        'comment-ca-marche' => [
            'eyebrow' => 'Parcours Yombal',
            'title' => 'Comment Yombal simplifie la couture sur mesure',
            'intro' => 'Retrouvez les etapes, les garanties et les bons reperes pour commander plus sereinement.',
        ],
        'contact' => [
            'eyebrow' => 'Restons en contact',
            'title' => 'Contacter l equipe Yombal',
            'intro' => 'Une question, un besoin d accompagnement ou une demande precise ? Notre equipe reste joignable facilement.',
        ],
        'faq' => [
            'eyebrow' => 'Questions frequentes',
            'title' => 'Tout comprendre rapidement',
            'intro' => 'Retrouvez les reponses essentielles pour commander, choisir un partenaire et suivre vos services sur mesure.',
        ],
        'guide-mesures' => [
            'eyebrow' => 'Guide pratique',
            'title' => 'Prendre ses mesures sereinement',
            'intro' => 'Suivez des indications simples pour partager des mesures claires a votre couturier.',
        ],
        'guide-tissus' => [
            'eyebrow' => 'Guide pratique',
            'title' => 'Mieux choisir vos tissus',
            'intro' => 'Comprenez les matieres, les usages et les quantites pour preparer votre commande plus facilement.',
        ],
        'mentions-legales' => [
            'eyebrow' => 'Cadre legal',
            'title' => 'Mentions legales Yombal',
            'intro' => 'Les informations d identification et de responsabilite de la plateforme, presentees de maniere claire.',
        ],
        'politique-de-confidentialite' => [
            'eyebrow' => 'Protection des donnees',
            'title' => 'Politique de confidentialite',
            'intro' => 'Comprenez quelles donnees sont collecte es, pourquoi et comment Yombal protege votre vie privee.',
        ],
        'conditions-generales-dutilisation' => [
            'eyebrow' => 'Regles d usage',
            'title' => 'Conditions generales d utilisation',
            'intro' => 'Les regles essentielles qui encadrent l utilisation de Yombal pour les clients et les partenaires.',
        ],
        'conditions-generales-de-vente' => [
            'eyebrow' => 'Cadre commercial',
            'title' => 'Conditions generales de vente',
            'intro' => 'Les conditions applicables aux commandes, paiements, livraisons et engagements sur Yombal.',
        ],
        'politique-remboursements-retours' => [
            'eyebrow' => 'Retours et remboursements',
            'title' => 'Politique de remboursements et de retours',
            'intro' => 'Retrouvez les conditions de retouche, de retour et de remboursement dans un format plus lisible.',
        ],
        'mes-messages' => [
            'eyebrow' => 'Vos echanges',
            'title' => 'Restez en contact',
            'intro' => 'Consultez vos conversations et gardez le fil avec vos partenaires sur Yombal.',
        ],
        'messages-yombal' => [
            'eyebrow' => 'Vos echanges',
            'title' => 'Messages Yombal',
            'intro' => 'Discutez facilement avec vos partenaires et gardez une trace claire de vos echanges.',
        ],
        'litiges-yombal' => [
            'eyebrow' => 'Aide et suivi',
            'title' => 'Aide et litiges Yombal',
            'intro' => 'Retrouvez ici un espace simple pour poser une question, signaler un probleme et suivre les reponses.',
        ],
    ];

    private const MEDIA_LIBRARY_PREFIXES = [
        'model' => 'ymb-mdl-',
        'fabric' => 'ymb-fab-',
        'logo' => 'ymb-lgo-',
    ];

    private const PUBLIC_MEDIA_SIZE = 'large';

    private const PARTNER_LOGO_META_KEYS = [
        'yombal_partner_logo_id',
        'yombal_logo_id',
        'yombal_partner_logo_url',
        'store_logo',
        'store_logo_id',
        'wcfm_store_logo',
        'wcfm_store_logo_id',
        'avatar_id',
        'profile_image_id',
    ];

    private const PARTNER_COVER_META_KEYS = [
        'yombal_partner_cover_id',
        'yombal_cover_id',
        'yombal_partner_cover_url',
        'store_banner',
        'store_banner_id',
        'banner_id',
        'cover_image_id',
        'wcfm_store_banner',
        'wcfm_store_banner_id',
    ];

    private const PARTNER_SETTINGS_META_KEYS = [
        'wcfmmp_profile_settings',
        'yombal_partner_media',
    ];

    private const GENERIC_MEDIA_VALUE_KEYS = [
        'attachment_id',
        'image_id',
        'id',
        'url',
        'src',
        'image',
        'value',
    ];

    private static bool $identity_strip_rendered = false;

    public static function boot(): void {
        add_filter('body_class', [self::class, 'body_classes']);
        add_filter('the_content', [self::class, 'wrap_standard_content'], 12);
        add_filter('wp_get_custom_css', [self::class, 'filter_wp_custom_css'], 99, 2);
        add_action('template_redirect', [self::class, 'start_output_buffer'], 1);
    }

    public static function body_classes(array $classes): array {
        if (is_admin()) {
            return $classes;
        }

        $classes[] = 'yombal-site';

        if (is_front_page()) {
            $classes[] = 'yombal-site--home';
        }

        if (is_page() || is_singular('post')) {
            $classes[] = 'yombal-site--editorial';
        }

        if (function_exists('is_woocommerce') && is_woocommerce()) {
            $classes[] = 'yombal-site--commerce';
        }

        if (is_user_logged_in()) {
            $classes[] = 'yombal-site--logged-in';
        }

        return $classes;
    }

    public static function wrap_standard_content(string $content): string {
        if (is_admin() || ! is_main_query() || ! in_the_loop()) {
            return $content;
        }

        if (! (is_page() || is_singular('post'))) {
            return $content;
        }

        if (is_front_page()) {
            return self::render_homepage();
        }

        if (self::is_custom_yombal_content($content)) {
            return $content;
        }

        $copy = self::page_copy();
        if ($copy === null) {
            return $content;
        }

        $class = is_page() ? 'yombal-standard-page' : 'yombal-standard-post';
        $shell_classes = ['yombal-ui', 'yombal-public-shell', 'yhr-page-shell', 'yhr-page-shell--standard', $class];

        if (function_exists('is_account_page') && is_account_page()) {
            $shell_classes[] = 'yombal-public-shell--account';
        }

        if (function_exists('is_cart') && is_cart()) {
            $shell_classes[] = 'yombal-public-shell--cart';
        }

        if (function_exists('is_checkout') && is_checkout()) {
            $shell_classes[] = 'yombal-public-shell--checkout';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $shell_classes)); ?>">
            <?php echo self::render_identity_strip(); ?>
            <section class="yombal-hero yombal-hero--page-shell yhr-page-hero">
                <span class="yombal-eyebrow"><?php echo esc_html($copy['eyebrow']); ?></span>
                <h1><?php echo esc_html($copy['title']); ?></h1>
                <p><?php echo esc_html($copy['intro']); ?></p>
            </section>
            <section class="yombal-card yombal-card--soft yombal-rich-content yhr-page-card">
                <?php echo $content; ?>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function filter_wp_custom_css(string $css, string $stylesheet = ''): string {
        if (is_admin()) {
            return $css;
        }

        return '';
    }

    public static function logo_url(): string {
        return plugins_url('assets/images/yombal-logo-primary.jpg', YOMBAL_CORE_FILE);
    }

    public static function public_media_size(): string {
        return self::PUBLIC_MEDIA_SIZE;
    }

    public static function partner_media_variant(string $partner_type): string {
        return match ($partner_type) {
            Roles::TYPE_FABRIC_VENDOR => 'fabric',
            default => 'model',
        };
    }

    public static function placeholder_image_url(string $variant = 'product', int $seed = 0): string {
        $category = match ($variant) {
            'fabric' => 'fabric',
            'partner', 'avatar', 'logo' => 'logo',
            default => 'model',
        };

        $library_url = self::media_library_fallback_url($category, $seed, self::PUBLIC_MEDIA_SIZE);
        if ($library_url !== '') {
            return $library_url;
        }

        $path = $category === 'logo'
            ? 'assets/images/yombal-partner-fallback.svg'
            : 'assets/images/yombal-product-fallback.svg';

        return plugins_url($path, YOMBAL_CORE_FILE);
    }

    public static function avatar_url(int $user_id, string $display_name = ''): string {
        return self::partner_logo_url($user_id, $display_name);
    }

    public static function product_image_url(int $post_id, string $size = '', string $variant = 'product'): string {
        $size = $size !== '' ? $size : self::PUBLIC_MEDIA_SIZE;
        if ($post_id > 0) {
            $src = get_the_post_thumbnail_url($post_id, $size);
            if ($src) {
                return (string) $src;
            }
        }

        return self::placeholder_image_url($variant, $post_id);
    }

    public static function partner_logo_url(int $user_id, string $display_name = '', string $partner_type = ''): string {
        if ($user_id > 0) {
            $resolved = self::resolve_partner_media_url(
                $user_id,
                self::PARTNER_LOGO_META_KEYS,
                ['gravatar', 'logo', 'store_logo', 'avatar', 'profile_image'],
                self::PUBLIC_MEDIA_SIZE
            );
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return self::placeholder_image_url('logo', $user_id);
    }

    public static function partner_cover_url(int $user_id, string $partner_type = '', int $seed = 0): string {
        if ($user_id > 0) {
            $resolved = self::resolve_partner_media_url(
                $user_id,
                self::PARTNER_COVER_META_KEYS,
                ['banner', 'mobile_banner', 'cover', 'cover_image', 'hero'],
                self::PUBLIC_MEDIA_SIZE
            );
            if ($resolved !== '') {
                return $resolved;
            }

            $lead_product_image = self::first_public_product_image_url($user_id, self::PUBLIC_MEDIA_SIZE, $partner_type);
            if ($lead_product_image !== '') {
                return $lead_product_image;
            }
        }

        $variant = self::partner_media_variant($partner_type);

        return self::placeholder_image_url($variant, $seed !== 0 ? $seed : $user_id);
    }

    public static function contextual_social_image_url(): string {
        $store_slug = sanitize_title((string) get_query_var('partner', ''));
        if ($store_slug !== '') {
            $user = get_user_by('slug', $store_slug);
            if ($user instanceof \WP_User) {
                $profile = Profile_Service::get_profile((int) $user->ID);

                return self::partner_cover_url(
                    (int) $user->ID,
                    (string) ($profile['partner_type'] ?? ''),
                    (int) $user->ID
                );
            }
        }

        if (function_exists('is_product') && is_product()) {
            $product_id = get_queried_object_id();
            $author_id = $product_id > 0 ? (int) get_post_field('post_author', $product_id) : 0;
            $profile = $author_id > 0 ? Profile_Service::get_profile($author_id) : null;

            return self::product_image_url(
                $product_id,
                self::PUBLIC_MEDIA_SIZE,
                self::partner_media_variant((string) ($profile['partner_type'] ?? ''))
            );
        }

        if (is_page('catalogue-tissus') || is_page('guide-tissus')) {
            return self::placeholder_image_url('fabric', get_queried_object_id());
        }

        if (is_page('catalogue-modeles') || is_front_page() || is_page('devenir-partenaire-yombal')) {
            return self::placeholder_image_url('model', get_queried_object_id());
        }

        if (is_page('catalogue-tailleurs')) {
            return self::placeholder_image_url('logo', get_queried_object_id());
        }

        if (is_page() || is_singular('post')) {
            return self::placeholder_image_url('model', get_queried_object_id());
        }

        return self::logo_url();
    }

    public static function should_render_identity_strip(): bool {
        if (is_admin() || is_front_page()) {
            return false;
        }

        return ! self::$identity_strip_rendered;
    }

    public static function render_identity_strip(): string {
        if (! self::should_render_identity_strip()) {
            return '';
        }

        self::$identity_strip_rendered = true;

        return self::render_site_header();
    }

    private static function site_chrome_context(): array {
        $tailors_url = home_url('/catalogue-tailleurs/');
        $fabrics_url = home_url('/catalogue-tissus/');
        $models_url = home_url('/catalogue-modeles/');
        $partner_apply_url = Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire-yombal/');
        $partner_workspace_url = Page_Provisioner::get_page_url('espace-partenaire-yombal') ?: home_url('/espace-partenaire-yombal/');
        $client_workspace_url = Customer_Workspace::get_page_url() ?: home_url('/espace-client-yombal/');
        $messages_url = home_url('/messages-yombal/');
        $support_url = home_url('/litiges-yombal/');
        $account_url = home_url('/connexion/');
        $logo_url = self::logo_url();

        if (is_user_logged_in()) {
            $account_url = Profile_Service::is_partner_user(get_current_user_id()) ? $partner_workspace_url : $client_workspace_url;
        }

        return [
            'tailors_url' => $tailors_url,
            'fabrics_url' => $fabrics_url,
            'models_url' => $models_url,
            'partner_apply_url' => $partner_apply_url,
            'partner_workspace_url' => $partner_workspace_url,
            'client_workspace_url' => $client_workspace_url,
            'messages_url' => $messages_url,
            'support_url' => $support_url,
            'account_url' => $account_url,
            'account_label' => 'Mon compte',
            'logo_url' => $logo_url,
        ];
    }

    private static function render_site_header(): string {
        $context = self::site_chrome_context();

        ob_start();
        ?>
        <header class="yhr-site-header" data-yhr-site-header>
            <div class="yhr-container yhr-site-header__inner">
                <a class="yhr-brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <img class="yhr-brand__logo" src="<?php echo esc_url((string) $context['logo_url']); ?>" alt="Yombal" loading="eager" decoding="async">
                    <span class="screen-reader-text">Yombal</span>
                </a>
                <button class="yhr-site-header__toggle" type="button" aria-expanded="false" aria-controls="yhr-site-nav" aria-label="Ouvrir le menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <nav id="yhr-site-nav" class="yhr-site-nav" data-yhr-site-nav aria-label="Navigation principale">
                    <a href="<?php echo esc_url((string) $context['tailors_url']); ?>">Tailleurs</a>
                    <a href="<?php echo esc_url((string) $context['fabrics_url']); ?>">Tissus</a>
                    <a href="<?php echo esc_url((string) $context['models_url']); ?>">Mod&egrave;les</a>
                    <a href="<?php echo esc_url((string) $context['partner_apply_url']); ?>">Devenir partenaire</a>
                    <a class="yhr-site-nav__account" href="<?php echo esc_url((string) $context['account_url']); ?>"><?php echo esc_html((string) $context['account_label']); ?></a>
                </nav>
            </div>
        </header>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_site_footer(): string {
        $context = self::site_chrome_context();

        ob_start();
        ?>
        <footer class="yhr-site-footer">
            <div class="yhr-container yhr-site-footer__grid">
                <div>
                    <a class="yhr-brand yhr-brand--inverse" href="<?php echo esc_url(home_url('/')); ?>">
                        <img class="yhr-brand__logo" src="<?php echo esc_url((string) $context['logo_url']); ?>" alt="Yombal" loading="lazy" decoding="async">
                        <span class="screen-reader-text">Yombal</span>
                    </a>
                    <p>La marketplace couture du Senegal. Tailleurs, tissus et modeles pour commander sur mesure depuis Dakar et partout ailleurs.</p>
                </div>
                <div>
                    <span class="yhr-eyebrow yhr-eyebrow--inverse">Decouvrir</span>
                    <div class="yhr-site-footer__links">
                        <a href="<?php echo esc_url((string) $context['tailors_url']); ?>">Tailleurs</a>
                        <a href="<?php echo esc_url((string) $context['fabrics_url']); ?>">Tissus</a>
                        <a href="<?php echo esc_url((string) $context['models_url']); ?>">Mod&egrave;les</a>
                    </div>
                </div>
                <div>
                    <span class="yhr-eyebrow yhr-eyebrow--inverse">Support</span>
                    <div class="yhr-site-footer__links">
                        <a href="<?php echo esc_url((string) $context['messages_url']); ?>">Messages</a>
                        <a href="<?php echo esc_url((string) $context['support_url']); ?>">Aide et litiges</a>
                        <a href="<?php echo esc_url((string) $context['account_url']); ?>"><?php echo esc_html((string) $context['account_label']); ?></a>
                    </div>
                </div>
            </div>
        </footer>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_homepage(): string {
        $tailors_url = home_url('/catalogue-tailleurs/');
        $fabrics_url = home_url('/catalogue-tissus/');
        $models_url = home_url('/catalogue-modeles/');
        $partner_apply_url = Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire-yombal/');
        $partner_workspace_url = Page_Provisioner::get_page_url('espace-partenaire-yombal') ?: home_url('/espace-partenaire-yombal/');
        $client_workspace_url = Customer_Workspace::get_page_url() ?: home_url('/espace-client-yombal/');
        $messages_url = home_url('/messages-yombal/');
        $support_url = home_url('/litiges-yombal/');
        $account_url = home_url('/connexion/');

        $is_partner = is_user_logged_in() && Profile_Service::is_partner_user(get_current_user_id());

        if (is_user_logged_in()) {
            $account_url = $is_partner ? $partner_workspace_url : $client_workspace_url;
        }

        $featured_stores = Public_Pages::featured_partners(3);
        if ($featured_stores === []) {
            $featured_stores = [[
                'name' => 'Partenaire Yombal',
                'city' => 'Senegal',
                'rating' => '',
                'products_count' => 0,
                'orders_count' => 0,
                'type_label' => 'Couturier',
                'specialties' => [],
                'excerpt' => 'Decouvrez les ateliers et partenaires couture visibles sur Yombal.',
                'url' => $tailors_url,
                'avatar' => self::placeholder_image_url('partner'),
                'cover_image' => self::placeholder_image_url('hero'),
            ]];
        }
        $hero_partner = $featured_stores[0];
        $homepage_metrics = self::homepage_metrics();
        $leading_metric = $homepage_metrics[0] ?? ['value' => '0', 'label' => 'Ateliers visibles'];

        $occasions = [
            [
                'index' => '01',
                'name' => 'Tabaski',
                'url' => home_url('/produit/kaftan-homme-bapteme/'),
            ],
            [
                'index' => '02',
                'name' => 'Mariage',
                'url' => home_url('/catalogue-modeles/'),
            ],
            [
                'index' => '03',
                'name' => 'Bapteme',
                'url' => home_url('/produit/ensemble-tailleur-bureau-femme/'),
            ],
            [
                'index' => '04',
                'name' => 'Korite',
                'url' => home_url('/catalogue-modeles/'),
            ],
        ];

        ob_start();
        ?>
        <div class="yombal-ui yombal-home-refonte">
            <section class="yhr-hero">
                <div class="yhr-container yhr-hero__grid">
                    <div class="yhr-hero__copy">
                        <span class="yhr-eyebrow">Marketplace couture - Senegal</span>
                        <h1 class="yhr-display-xl">Votre tenue sur mesure,<br><span>livr&eacute;e chez vous au S&eacute;n&eacute;gal</span></h1>
                        <p class="yhr-lead">Trouvez le tailleur ideal, choisissez votre tissu, commandez votre modele et suivez chaque etape sans quitter votre maison. Paiement securise, mediations et messagerie incluses.</p>
                        <div class="yhr-actions">
                            <a class="yhr-btn yhr-btn--primary yhr-btn--lg" href="<?php echo esc_url($models_url); ?>">Commander ma tenue</a>
                            <a class="yhr-btn yhr-btn--ghost yhr-btn--lg" href="<?php echo esc_url($tailors_url); ?>">Parcourir les tailleurs</a>
                        </div>
                        <div class="yhr-search-rail">
                            <div class="yhr-search-rail__item">
                                <span class="yhr-mono yhr-muted yhr-label">Je cherche</span>
                                <strong>Un tailleur</strong>
                            </div>
                            <div class="yhr-search-rail__item">
                                <span class="yhr-mono yhr-muted yhr-label">A</span>
                                <strong>Dakar</strong>
                            </div>
                            <div class="yhr-search-rail__item yhr-search-rail__item--plain">
                                <span class="yhr-mono yhr-muted yhr-label">Pour</span>
                                <strong>Tabaski</strong>
                            </div>
                            <a class="yhr-btn yhr-btn--accent" href="<?php echo esc_url($tailors_url); ?>">Rechercher</a>
                        </div>
                    </div>
                    <div class="yhr-hero__visual">
                        <img class="yhr-media yhr-media--hero" src="<?php echo esc_url((string) ($hero_partner['cover_image'] ?? self::placeholder_image_url('hero'))); ?>" alt="<?php echo esc_attr((string) ($hero_partner['name'] ?? 'Yombal')); ?>" loading="eager" decoding="async">
                        <div class="yhr-floating-card yhr-floating-card--review">
                            <div class="yhr-floating-card__top">
                                <img class="yhr-avatar-media" src="<?php echo esc_url((string) ($hero_partner['avatar'] ?? self::placeholder_image_url('partner'))); ?>" alt="<?php echo esc_attr((string) ($hero_partner['name'] ?? 'Yombal')); ?>" loading="lazy" decoding="async">
                                <div>
                                    <div class="yhr-floating-card__title"><?php echo esc_html((string) ($hero_partner['name'] ?? 'Partenaire Yombal')); ?></div>
                                    <div class="yhr-mono yhr-muted"><?php echo esc_html((string) ($hero_partner['city'] ?? 'Senegal')); ?></div>
                                </div>
                            </div>
                            <p><?php echo esc_html((string) ($hero_partner['excerpt'] ?? 'Decouvrez ce partenaire et ses offres visibles sur Yombal.')); ?></p>
                            <div class="yhr-stars">
                                <?php
                                echo esc_html(
                                    (string) (($hero_partner['rating'] ?? '') !== ''
                                        ? ((string) $hero_partner['rating'] . '/5')
                                        : (number_format_i18n((int) ($hero_partner['products_count'] ?? 0)) . ' offre(s)'))
                                );
                                ?>
                            </div>
                        </div>
                        <div class="yhr-floating-card yhr-floating-card--metric">
                            <span class="yhr-mono">En ligne</span>
                            <strong><?php echo esc_html((string) $leading_metric['value']); ?></strong>
                            <small><?php echo esc_html((string) $leading_metric['label']); ?></small>
                        </div>
                    </div>
                </div>
            </section>

            <section class="yhr-metrics">
                <div class="yhr-container yhr-metrics__grid">
                    <?php foreach ($homepage_metrics as $metric) : ?>
                        <div class="yhr-metric">
                            <strong><?php echo esc_html((string) $metric['value']); ?></strong>
                            <span class="yhr-mono"><?php echo esc_html((string) $metric['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="yhr-section yhr-section--journey">
                <div class="yhr-container">
                    <div class="yhr-section__head">
                        <div>
                            <span class="yhr-eyebrow">Comment ca marche</span>
                            <h2 class="yhr-display-lg">Quatre etapes,<br><em>une tenue impeccable</em>.</h2>
                        </div>
                        <a class="yhr-btn yhr-btn--ghost yhr-hide-sm" href="<?php echo esc_url($client_workspace_url); ?>">Voir votre espace client</a>
                    </div>
                    <div class="yhr-steps-grid">
                        <article class="yhr-step-card">
                            <span class="yhr-step-card__index">01</span>
                            <div>
                                <h3>Choisissez un tailleur</h3>
                                <p>Parcourez les ateliers verifies, comparez les delais, les prix et les specialites couture.</p>
                            </div>
                        </article>
                        <article class="yhr-step-card">
                            <span class="yhr-step-card__index">02</span>
                            <div>
                                <h3>Partagez vos mesures</h3>
                                <p>Votre profil de mesures reste accessible et reutilisable pour toutes vos commandes.</p>
                            </div>
                        </article>
                        <article class="yhr-step-card">
                            <span class="yhr-step-card__index">03</span>
                            <div>
                                <h3>Selectionnez tissu et modele</h3>
                                <p>Partez d un modele ou d un tissu selon votre besoin et precisez vos finitions.</p>
                            </div>
                        </article>
                        <article class="yhr-step-card">
                            <span class="yhr-step-card__index">04</span>
                            <div>
                                <h3>Recevez chez vous</h3>
                                <p>Suivez votre commande, echangez via la messagerie et activez la mediation si necessaire.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="yhr-section yhr-section--tight yhr-section--featured">
                <div class="yhr-container">
                    <div class="yhr-section__head">
                        <div>
                            <span class="yhr-eyebrow">Ateliers en vedette</span>
                            <h2 class="yhr-display-lg">Les plus demandes cette semaine.</h2>
                        </div>
                        <a class="yhr-btn yhr-btn--ghost" href="<?php echo esc_url($tailors_url); ?>">Voir tout</a>
                    </div>
                    <div class="yhr-feature-grid">
                        <?php foreach ($featured_stores as $store) : ?>
                            <a class="yhr-feature-card" href="<?php echo esc_url($store['url']); ?>">
                                <img class="yhr-media" src="<?php echo esc_url((string) ($store['cover_image'] ?? self::placeholder_image_url('partner'))); ?>" alt="<?php echo esc_attr((string) $store['name']); ?>" loading="lazy" decoding="async">
                                <div class="yhr-feature-card__body">
                                    <div class="yhr-feature-card__top">
                                        <div>
                                            <h3><?php echo esc_html($store['name']); ?></h3>
                                            <span class="yhr-mono yhr-muted"><?php echo esc_html($store['city']); ?></span>
                                        </div>
                                        <span class="yhr-badge yhr-badge--verified"><?php echo esc_html((string) ($store['type_label'] ?? 'Partenaire')); ?></span>
                                    </div>
                                    <div class="yhr-feature-card__meta">
                                        <span>
                                            <span class="yhr-stars"><?php echo esc_html((string) (($store['rating'] ?? '') !== '' ? ((string) $store['rating'] . '/5') : 'N/A')); ?></span>
                                        </span>
                                        <span class="yhr-faint">-</span>
                                        <span><?php echo esc_html(number_format_i18n((int) ($store['orders_count'] ?? 0))); ?> commande(s)</span>
                                    </div>
                                    <div class="yhr-feature-card__footer">
                                        <div>
                                            <span class="yhr-mono yhr-muted yhr-label">Catalogue visible</span>
                                            <strong><?php echo esc_html(number_format_i18n((int) ($store['products_count'] ?? 0))); ?> <span>offres</span></strong>
                                        </div>
                                        <span class="yhr-chip yhr-chip--accent"><?php echo esc_html((string) (($store['specialties'][0] ?? '') !== '' ? $store['specialties'][0] : ($store['type_label'] ?? 'Yombal'))); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="yhr-section yhr-section--tight yhr-section--assurance">
                <div class="yhr-container yhr-split-grid">
                    <article class="yhr-highlight-card">
                        <span class="yhr-mono">Collection - Tabaski 2026</span>
                        <h2>Commandez<br>avant le 15 mai.</h2>
                        <p>Reperez les modeles phares, trouvez un tissu bazin ou wax et lancez votre commande suffisamment tot pour la fete.</p>
                        <a class="yhr-btn yhr-btn--light" href="<?php echo esc_url($models_url); ?>">Voir la collection</a>
                    </article>
                    <div class="yhr-occasion-grid">
                        <?php foreach ($occasions as $occasion) : ?>
                            <a class="yhr-occasion-card" href="<?php echo esc_url($occasion['url']); ?>">
                                <span class="yhr-mono yhr-faint"><?php echo esc_html($occasion['index']); ?></span>
                                <div>
                                    <strong><?php echo esc_html($occasion['name']); ?></strong>
                                    <span>Voir les modeles</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="yhr-section yhr-section--tight">
                <div class="yhr-container yhr-assurance-grid">
                    <article class="yhr-assurance-card">
                        <div class="yhr-assurance-card__icon">1</div>
                        <h3>Paiement securise</h3>
                        <p>Votre argent reste protege jusqu a la reception. Wave, Orange Money et carte bancaire pris en charge.</p>
                    </article>
                    <article class="yhr-assurance-card">
                        <div class="yhr-assurance-card__icon">2</div>
                        <h3>Mediation incluse</h3>
                        <p>Un probleme sur une commande ? L equipe Yombal intervient rapidement pour arbitrer le litige.</p>
                    </article>
                    <article class="yhr-assurance-card">
                        <div class="yhr-assurance-card__icon">3</div>
                        <h3>Tailleurs verifies</h3>
                        <p>Chaque atelier est controle avant ouverture avec references, identite et specialites clairement affichees.</p>
                    </article>
                </div>
            </section>

            <section class="yhr-section yhr-section--tight">
                <div class="yhr-container">
                    <div class="yhr-partner-banner">
                        <div>
                            <span class="yhr-eyebrow yhr-eyebrow--inverse">Vous etes tailleur ou vendeur de tissus ?</span>
                            <h2 class="yhr-display-lg">Ouvrez votre boutique Yombal<br><em>en 10 minutes.</em></h2>
                            <p>Inscription gratuite, accompagnement dedie et support disponible pour lancer votre boutique sereinement.</p>
                            <div class="yhr-actions">
                                <a class="yhr-btn yhr-btn--accent yhr-btn--lg" href="<?php echo esc_url($partner_apply_url); ?>">Devenir partenaire</a>
                                <a class="yhr-btn yhr-btn--ghost-light yhr-btn--lg" href="<?php echo esc_url($partner_workspace_url); ?>">Espace partenaire</a>
                            </div>
                        </div>
                        <div class="yhr-partner-kpis">
                            <div><span class="yhr-mono">Inscription</span><strong>Gratuite</strong></div>
                            <div><span class="yhr-mono">Support</span><strong>7j/7</strong></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="yhr-section yhr-section--tight yhr-section--links yhr-section--last">
                <div class="yhr-container yhr-link-grid">
                    <a class="yhr-link-card" href="<?php echo esc_url($fabrics_url); ?>">
                        <span class="yhr-eyebrow">Tissus</span>
                        <strong>Voir les tissus</strong>
                        <p>Wax, bazin, damask et selections partenaires pour lancer votre commande.</p>
                    </a>
                    <a class="yhr-link-card" href="<?php echo esc_url($messages_url); ?>">
                        <span class="yhr-eyebrow">Messages</span>
                        <strong>Contacter un partenaire</strong>
                        <p>Gardez une trace claire de vos echanges, ajustements et confirmations.</p>
                    </a>
                    <a class="yhr-link-card" href="<?php echo esc_url($support_url); ?>">
                        <span class="yhr-eyebrow">Aide</span>
                        <strong>Suivre un litige</strong>
                        <p>Accedez a l aide, signalez un probleme et suivez la mediation Yombal.</p>
                    </a>
                    <a class="yhr-link-card" href="<?php echo esc_url($account_url); ?>">
                        <span class="yhr-eyebrow">Compte</span>
                        <strong>Acceder a votre espace</strong>
                        <p>Retrouvez commandes, mesures, messages et informations personnelles.</p>
                    </a>
                </div>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function homepage_metrics(): array {
        return [
            [
                'value' => number_format_i18n(self::visible_tailor_count()),
                'label' => 'Ateliers visibles',
            ],
            [
                'value' => number_format_i18n(self::completed_orders_count()),
                'label' => 'Commandes finalisees',
            ],
            [
                'value' => self::average_marketplace_rating(),
                'label' => 'Note moyenne',
            ],
            [
                'value' => self::average_mediation_delay(),
                'label' => 'Mediation moyenne',
            ],
        ];
    }

    private static function visible_tailor_count(): int {
        global $wpdb;

        $profiles_table = Installer::table_name('yombal_partner_profiles');
        if (! self::table_exists($profiles_table)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$profiles_table}
                WHERE partner_type IN (%s, %s)
                AND profile_status IN ('approved', 'legacy', 'legacy_imported')",
                Roles::TYPE_TAILOR,
                Roles::TYPE_HYBRID
            )
        );
    }

    private static function completed_orders_count(): int {
        global $wpdb;

        $profiles_table = Installer::table_name('yombal_partner_profiles');
        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $order_stats_table = $wpdb->prefix . 'wc_order_stats';

        if (! self::table_exists($profiles_table) || ! self::table_exists($lookup_table) || ! self::table_exists($order_stats_table)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT lookup_table.order_id)
            FROM {$lookup_table} AS lookup_table
            INNER JOIN {$wpdb->posts} AS products
                ON products.ID = lookup_table.product_id
                AND products.post_type = 'product'
            INNER JOIN {$profiles_table} AS profiles
                ON profiles.user_id = products.post_author
            INNER JOIN {$order_stats_table} AS stats
                ON stats.order_id = lookup_table.order_id
            WHERE profiles.profile_status IN ('approved', 'legacy', 'legacy_imported')
            AND stats.status IN ('wc-completed', 'completed')"
        );
    }

    private static function average_marketplace_rating(): string {
        global $wpdb;

        $profiles_table = Installer::table_name('yombal_partner_profiles');
        if (! self::table_exists($profiles_table)) {
            return 'N/A';
        }

        $rating = $wpdb->get_var(
            "SELECT AVG(CAST(meta.meta_value AS DECIMAL(10,2)))
            FROM {$wpdb->comments} AS comments
            INNER JOIN {$wpdb->commentmeta} AS meta
                ON meta.comment_id = comments.comment_ID
                AND meta.meta_key = 'rating'
            INNER JOIN {$wpdb->posts} AS products
                ON products.ID = comments.comment_post_ID
                AND products.post_type = 'product'
            INNER JOIN {$profiles_table} AS profiles
                ON profiles.user_id = products.post_author
            WHERE comments.comment_approved = '1'
            AND profiles.profile_status IN ('approved', 'legacy', 'legacy_imported')"
        );

        if ($rating === null) {
            return 'N/A';
        }

        $value = (float) $rating;

        return $value > 0 ? number_format_i18n($value, 1) . '/5' : 'N/A';
    }

    private static function average_mediation_delay(): string {
        global $wpdb;

        $tickets_table = Installer::table_name('yombal_support_tickets');
        if (! self::table_exists($tickets_table)) {
            return 'N/A';
        }

        $hours = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(closed_at, last_reply_at, updated_at, created_at)))
            FROM {$tickets_table}
            WHERE status IN ('resolved', 'closed')"
        );

        if ($hours === null) {
            return 'N/A';
        }

        $value = (float) $hours;
        if ($value <= 0) {
            return 'N/A';
        }

        if ($value < 24) {
            return max(1, (int) round($value)) . 'h';
        }

        return max(1, (int) round($value / 24)) . 'j';
    }

    private static function table_exists(string $table): bool {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    private static function is_custom_yombal_content(string $content): bool {
        $markers = [
            'yombal-ui',
            'yombal-public-page',
            'yombal-shell',
            'yombal-dashboard',
            'yombal-public-shell',
        ];

        foreach ($markers as $marker) {
            if (str_contains($content, $marker)) {
                return true;
            }
        }

        return false;
    }

    private static function page_copy(): ?array {
        $slug = '';

        if (is_page()) {
            global $post;
            $slug = $post instanceof \WP_Post ? (string) $post->post_name : '';
        }

        if ($slug !== '' && isset(self::PAGE_COPY[$slug])) {
            return self::PAGE_COPY[$slug];
        }

        $title = trim(wp_strip_all_tags((string) get_the_title()));
        if ($title === '') {
            return null;
        }

        if (is_singular('post')) {
            return [
                'eyebrow' => 'Inspiration Yombal',
                'title' => $title,
                'intro' => 'Decouvrez un contenu utile pour mieux choisir, commander et avancer dans votre experience Yombal.',
            ];
        }

        if (is_page()) {
            return [
                'eyebrow' => 'Page Yombal',
                'title' => $title,
                'intro' => self::fallback_intro($title),
            ];
        }

        return null;
    }

    private static function fallback_intro(string $title): string {
        $title = mb_strtolower($title);

        if (str_contains($title, 'contact')) {
            return 'Une equipe a l ecoute pour vous accompagner avant, pendant et apres votre commande.';
        }

        if (str_contains($title, 'guide')) {
            return 'Retrouvez ici des reperes clairs pour avancer plus facilement dans votre projet.';
        }

        if (str_contains($title, 'message')) {
            return 'Gardez une vue simple sur vos echanges importants.';
        }

        return 'Retrouvez sur cette page les informations utiles dans une presentation plus claire et plus confortable.';
    }

    public static function start_output_buffer(): void {
        if (is_admin() || wp_doing_ajax() || is_feed() || is_trackback()) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        self::debug_trace('start_output_buffer', [
            'front_page' => is_front_page() ? '1' : '0',
            'request' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        ]);

        if (is_front_page()) {
            ob_start();
            self::render_front_page_document();
            $html = ob_get_clean();
            if (is_string($html)) {
                echo self::rewrite_front_markup($html);
            }
            exit;
        }

        if (self::should_render_public_document()) {
            self::render_current_public_document();
            exit;
        }

        ob_start([self::class, 'rewrite_front_markup']);
    }

    private static function should_render_public_document(): bool {
        if (is_front_page()) {
            return false;
        }

        $request_path = trim((string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $legacy_redirect_paths = [
            'modeles',
            'litige',
            'mes-messages',
            'vendor-register',
            'vendor-registration',
            'dashboard-partenaire',
            'connexion-2',
            'connexion-3',
            'store-manager',
            'devenir-tailleur',
            'devenir-vendeur-tissus',
            'devenir-partenaire',
        ];

        if (in_array($request_path, $legacy_redirect_paths, true)) {
            return false;
        }

        if (is_page() || is_singular('post')) {
            return true;
        }

        return function_exists('is_product') && is_product();
    }

    private static function render_current_public_document(): void {
        status_header(200);
        nocache_headers();
        self::$identity_strip_rendered = false;
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(['yombal-site', 'yombal-public-document']); ?>>
<?php wp_body_open(); ?>
<div id="page-wrap">
    <?php echo self::render_site_header(); ?>
    <main id="skip-link-target" class="yhr-page-document__main">
        <?php echo self::render_current_public_content(); ?>
    </main>
    <?php echo self::render_site_footer(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html><?php
        self::$identity_strip_rendered = false;
    }

    private static function render_current_public_content(): string {
        global $post;

        $previous_identity_state = self::$identity_strip_rendered;
        self::$identity_strip_rendered = true;

        ob_start();

        if (function_exists('is_product') && is_product() && function_exists('wc_get_template_part')) {
            while (have_posts()) {
                the_post();
                wc_get_template_part('content', 'single-product');
            }
        } else {
            while (have_posts()) {
                the_post();
                the_content();
            }
        }

        $content = (string) ob_get_clean();
        wp_reset_postdata();
        if ($post instanceof \WP_Post) {
            setup_postdata($post);
        }

        self::$identity_strip_rendered = $previous_identity_state;

        return self::normalize_heading_outline($content);
    }

    public static function rewrite_front_markup(string $html): string {
        self::debug_trace('rewrite_enter', [
            'request' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'len' => (string) strlen($html),
            'has_yhr_header' => str_contains($html, 'data-yhr-site-header') ? '1' : '0',
            'has_site_header' => str_contains($html, 'class="site-header"') ? '1' : '0',
        ]);

        $html = self::strip_legacy_front_noise($html);
        self::debug_trace('after_strip_noise', ['len' => (string) strlen($html)]);

        if (is_page() && str_contains($html, 'yombal-ui')) {
            $html = preg_replace('#<header class="post-header[^"]*">.*?</header>#s', '', $html, 1) ?: $html;
            self::debug_trace('after_post_header_cleanup', ['len' => (string) strlen($html)]);
        }

        $html = preg_replace(
            '#<section\b[^>]*class=(["\'])[^"\']*\byombal-brand-strip\b[^"\']*\1[^>]*>.*?</section>#is',
            '',
            $html
        ) ?: $html;
        $html = preg_replace(
            '#<div\b[^>]*class=(["\'])[^"\']*\byombal-brand-strip\b[^"\']*\1[^>]*>.*?</div>#is',
            '',
            $html
        ) ?: $html;

        $is_partner = is_user_logged_in() && Profile_Service::is_partner_user(get_current_user_id());
        $partner_apply_url = Page_Provisioner::get_page_url('devenir-partenaire-yombal') ?: home_url('/devenir-partenaire-yombal/');
        $partner_workspace_url = Page_Provisioner::get_page_url('espace-partenaire-yombal') ?: home_url('/espace-partenaire-yombal/');
        $client_workspace_url = Customer_Workspace::get_page_url() ?: home_url('/espace-client-yombal/');
        $messages_url = home_url('/messages-yombal/');
        $models_url = home_url('/catalogue-modeles/');
        $support_url = home_url('/litiges-yombal/');
        $tailor_apply_url = add_query_arg('partner_type', 'tailor', $partner_apply_url);
        $fabric_apply_url = add_query_arg('partner_type', 'fabric_vendor', $partner_apply_url);
        $account_url = home_url('/connexion/');
        $account_label = 'Mon compte';

        if (is_user_logged_in()) {
            if ($is_partner) {
                $account_url = $partner_workspace_url;
            } else {
                $account_url = $client_workspace_url;
            }
        }

        $partner_entry_url = $is_partner ? $partner_workspace_url : $partner_apply_url;
        $partner_entry_label = $is_partner ? 'Espace partenaire' : 'Devenir partenaire';

        $replacements = [
            'href="https://yombal.sn/devenir-partenaire/"' => 'href="' . esc_url($partner_apply_url) . '"',
            "href='https://yombal.sn/devenir-partenaire/'" => "href='" . esc_url($partner_apply_url) . "'",
            'href="/devenir-partenaire/"' => 'href="' . esc_url($partner_apply_url) . '"',
            "href='/devenir-partenaire/'" => "href='" . esc_url($partner_apply_url) . "'",
            'href="https://yombal.sn/devenir-vendeur-tissus/"' => 'href="' . esc_url($fabric_apply_url) . '"',
            "href='https://yombal.sn/devenir-vendeur-tissus/'" => "href='" . esc_url($fabric_apply_url) . "'",
            'href="/devenir-vendeur-tissus/"' => 'href="' . esc_url($fabric_apply_url) . '"',
            "href='/devenir-vendeur-tissus/'" => "href='" . esc_url($fabric_apply_url) . "'",
            'href="https://yombal.sn/devenir-tailleur/"' => 'href="' . esc_url($tailor_apply_url) . '"',
            "href='https://yombal.sn/devenir-tailleur/'" => "href='" . esc_url($tailor_apply_url) . "'",
            'href="/devenir-tailleur/"' => 'href="' . esc_url($tailor_apply_url) . '"',
            "href='/devenir-tailleur/'" => "href='" . esc_url($tailor_apply_url) . "'",
            'href="https://yombal.sn/dashboard-partenaire/"' => 'href="' . esc_url($partner_workspace_url) . '"',
            "href='https://yombal.sn/dashboard-partenaire/'" => "href='" . esc_url($partner_workspace_url) . "'",
            'href="/dashboard-partenaire/"' => 'href="' . esc_url($partner_workspace_url) . '"',
            "href='/dashboard-partenaire/'" => "href='" . esc_url($partner_workspace_url) . "'",
            'href="https://yombal.sn/mes-messages/"' => 'href="' . esc_url($messages_url) . '"',
            "href='https://yombal.sn/mes-messages/'" => "href='" . esc_url($messages_url) . "'",
            'href="/mes-messages/"' => 'href="' . esc_url($messages_url) . '"',
            "href='/mes-messages/'" => "href='" . esc_url($messages_url) . "'",
            'href="https://yombal.sn/modeles/"' => 'href="' . esc_url($models_url) . '"',
            "href='https://yombal.sn/modeles/'" => "href='" . esc_url($models_url) . "'",
            'href="/modeles/"' => 'href="' . esc_url($models_url) . '"',
            "href='/modeles/'" => "href='" . esc_url($models_url) . "'",
            'href="https://yombal.sn/support-tickets/"' => 'href="' . esc_url($support_url) . '"',
            "href='https://yombal.sn/support-tickets/'" => "href='" . esc_url($support_url) . "'",
            'href="/support-tickets/"' => 'href="' . esc_url($support_url) . '"',
            "href='/support-tickets/'" => "href='" . esc_url($support_url) . "'",
            'href="https://yombal.sn/aide-litige/"' => 'href="' . esc_url($support_url) . '"',
            "href='https://yombal.sn/aide-litige/'" => "href='" . esc_url($support_url) . "'",
            'href="/aide-litige/"' => 'href="' . esc_url($support_url) . '"',
            "href='/aide-litige/'" => "href='" . esc_url($support_url) . "'",
            'href="https://yombal.sn/litige/"' => 'href="' . esc_url($support_url) . '"',
            "href='https://yombal.sn/litige/'" => "href='" . esc_url($support_url) . "'",
            'href="/litige/"' => 'href="' . esc_url($support_url) . '"',
            "href='/litige/'" => "href='" . esc_url($support_url) . "'",
            '>Dashboard Partenaire<' => '>Espace partenaire<',
            '>ðŸ“Š Dashboard Partenaire<' => '>ðŸ“Š Espace partenaire<',
            '>Devenir Vendeur<' => '>Devenir partenaire<',
            '>Support<' => '>Aide et litiges<',
            '>ModÃ¨les<' => '>Catalogue Modeles<',
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $html = preg_replace(
            '#href=(["\'])(?:https://yombal\.sn)?/(?:store-manager|dashboard-partenaire)/?(?:\?[^"\']*)?\1#i',
            'href="' . esc_url($partner_entry_url) . '"',
            $html
        ) ?: $html;

        $html = preg_replace(
            '#href=(["\'])(?:https://yombal\.sn)?/mon-compte/?(?:\?[^"\']*)?\1#i',
            'href="' . esc_url($account_url) . '"',
            $html
        ) ?: $html;

        $html = preg_replace_callback(
            '#href=(["\'])https://yombal\.sn/wp-login\.php\?redirect_to=([^"\']+)\1#i',
            static function (array $matches): string {
                $redirect = rawurldecode((string) ($matches[2] ?? ''));
                $login_url = add_query_arg('redirect_to', $redirect, home_url('/connexion/'));

                return 'href="' . esc_url($login_url) . '"';
            },
            $html
        ) ?: $html;

        $html = preg_replace_callback(
            '#https://yombal\.sn/wp-login\.php\?redirect_to=([^\s"\']+)#i',
            static function (array $matches): string {
                $redirect = rawurldecode((string) ($matches[1] ?? ''));
                $login_url = add_query_arg('redirect_to', $redirect, home_url('/connexion/'));

                return esc_url($login_url);
            },
            $html
        ) ?: $html;

        $html = preg_replace(
            '#(<a[^>]+href=["\'][^"\']*(?:/mon-compte/|/connexion/|/espace-client-yombal/|/espace-partenaire-yombal/)[^"\']*["\'][^>]*>)\s*(?:[^<]*?)?Mon compte\s*</a>#iu',
            '$1' . esc_html($account_label) . '</a>',
            $html
        ) ?: $html;

        $nav_label_patterns = [
            '#(<a[^>]+href=["\'][^"\']*/catalogue-tailleurs/?(?:\?[^"\']*)?["\'][^>]*>).*?(</a>)#is' => '$1Tailleurs$2',
            '#(<a[^>]+href=["\'][^"\']*/catalogue-tissus/?(?:\?[^"\']*)?["\'][^>]*>).*?(</a>)#is' => '$1Tissus$2',
            '#(<a[^>]+href=["\'][^"\']*/catalogue-modeles/?(?:\?[^"\']*)?["\'][^>]*>).*?(</a>)#is' => '$1Mod&egrave;les$2',
            '#(<a[^>]+href=["\'][^"\']*/devenir-partenaire-yombal/?(?:\?[^"\']*)?["\'][^>]*>).*?(</a>)#is' => '$1Devenir partenaire$2',
            '#(<a[^>]+href=["\'][^"\']*(?:/connexion/|/mon-compte/|/espace-client-yombal/|/espace-partenaire-yombal/)(?:\?[^"\']*)?["\'][^>]*>).*?(</a>)#is' => '$1Mon compte$2',
        ];

        foreach ($nav_label_patterns as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html) ?: $html;
        }

        $html = preg_replace(
            '#(<a[^>]+href=["\'][^"\']*/devenir-partenaire-yombal/\?partner_type=fabric_vendor[^"\']*["\'][^>]*>).*?(</a>)#is',
            '$1Vendre des tissus$2',
            $html
        ) ?: $html;

        $html = preg_replace(
            '#(<a[^>]+href=["\'][^"\']*/devenir-partenaire-yombal/\?partner_type=tailor[^"\']*["\'][^>]*>).*?(</a>)#is',
            '$1Devenir tailleur$2',
            $html
        ) ?: $html;

        $html = preg_replace(
            '#(<a[^>]+href=["\'][^"\']*(?:/store-manager/|/dashboard-partenaire/|/espace-partenaire-yombal/|/devenir-partenaire(?:-yombal)?/)[^"\']*["\'][^>]*>)\s*(?:[^<]*?)?(?:Devenir Vendeur|Dashboard Partenaire)\s*</a>#iu',
            '$1' . esc_html($partner_entry_label) . '</a>',
            $html
        ) ?: $html;

        $html = preg_replace(
            '#(<li[^>]*>\s*<a[^>]+href=["\'][^"\']*(?:/connexion/|/mon-compte/|/espace-client-yombal/|/espace-partenaire-yombal/)[^"\']*["\'][^>]*>\s*Mon compte\s*</a>\s*</li>)(?:\s*<li[^>]*>\s*<a[^>]+href=["\'][^"\']*(?:/connexion/|/mon-compte/|/espace-client-yombal/|/espace-partenaire-yombal/)[^"\']*["\'][^>]*>\s*Mon compte\s*</a>\s*</li>)+#is',
            '$1',
            $html
        ) ?: $html;

        $html = preg_replace(
            '#(<a[^>]+href=["\'])https://yombal\.sn/devenir-partenaire-yombal/(["\'][^>]*>\s*ðŸ§µ\s*Devenir Tailleur\s*<)#u',
            '$1https://yombal.sn/devenir-partenaire-yombal/?partner_type=tailor$2',
            $html
        ) ?: $html;

        $html = self::rewrite_inline_palette($html);
        self::debug_trace('after_palette', ['len' => (string) strlen($html)]);

        if (! is_front_page() && str_contains($html, 'data-yhr-site-header')) {
            self::debug_trace('before_theme_chrome_cleanup', [
                'has_site_header' => str_contains($html, 'class="site-header"') ? '1' : '0',
                'has_page_footer' => str_contains($html, 'id="page-footer"') ? '1' : '0',
            ]);
            $html = self::remove_theme_header_markup($html);
            self::debug_trace('after_theme_header_cleanup', ['len' => (string) strlen($html)]);
            $html = self::replace_or_inject_theme_footer_markup($html);
            self::debug_trace('after_theme_footer_cleanup', ['len' => (string) strlen($html)]);
        }

        $html = self::normalize_heading_outline($html);
        self::debug_trace('rewrite_return', ['len' => (string) strlen($html)]);

        return $html;
    }

    private static function inject_public_chrome(string $html): string {
        if (! str_contains($html, '<body')) {
            return $html;
        }

        if (! str_contains($html, 'data-yhr-site-header')) {
            $region = self::find_tag_region($html, 'header', ['<header id="site-header"', ' id="site-header"', ' class="site-header"']);
            if ($region !== null) {
                $html = substr($html, 0, $region['start']) . self::render_site_header() . substr($html, $region['end']);
            }
        }

        return self::replace_or_inject_theme_footer_markup($html);
    }

    private static function strip_legacy_front_noise(string $html): string {
        $html = preg_replace(
            '#<style\b[^>]*id=["\'](?:yombal-cssfix|yombal-ux-mobile)["\'][^>]*>.*?</style>#is',
            '',
            $html
        ) ?: $html;

        $html = preg_replace(
            '#<script\b[^>]*>.*?(?:wcfm_dashboard_title|wcfm_menu_item\s+span).*?</script>#is',
            '',
            $html
        ) ?: $html;

        $html = preg_replace_callback(
            '#<style\b[^>]*id=(["\'])wp-custom-css\1[^>]*>(.*?)</style>#is',
            static function (array $matches): string {
                $css = (string) ($matches[2] ?? '');
                $legacy_css_patterns = [
                    '#/\*\s*===.*?(?:MON COMPTE|DASHBOARD).*?===\s*\*/.*?(?=/\*\s*===|\z)#is',
                    '#/\*\s*===\s*FIX FINAL\s*:.*?MON C.*?(?=/\*\s*===|\z)#is',
                ];

                foreach ($legacy_css_patterns as $pattern) {
                    $css = preg_replace($pattern, '', $css) ?: $css;
                }

                return '<style type="text/css" id="wp-custom-css">' . $css . '</style>';
            },
            $html
        ) ?: $html;

        return $html;
    }

    private static function rewrite_inline_palette(string $html): string {
        $replacements = [
            '#04273e' => '#0d2454',
            '#C8963E' => '#f0a534',
            '#27ae60' => '#10855c',
            '#4a5568' => '#607597',
            '#f4f6f9' => '#eef4ff',
            '#123d5b' => '#1f4d9f',
            '#1f5f83' => '#18a1be',
            '#06263d' => '#0d2454',
            '#083554' => '#15407d',
            '#0d4570' => '#1387ab',
            '#e5b96a' => '#f6bf63',
            'rgba(200,150,62,.2)' => 'rgba(240,165,52,.16)',
            'rgba(200,150,62,.4)' => 'rgba(240,165,52,.34)',
            'rgba(200, 150, 62, 0.2)' => 'rgba(240, 165, 52, 0.16)',
            'rgba(200, 150, 62, 0.4)' => 'rgba(240, 165, 52, 0.34)',
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $html);
    }

    private static function normalize_heading_outline(string $html): string {
        $heading_index = 0;

        return preg_replace_callback(
            '#<h1(\b[^>]*)>(.*?)</h1>#is',
            static function (array $matches) use (&$heading_index): string {
                $heading_index++;
                if ($heading_index === 1) {
                    return $matches[0];
                }

                return '<h2' . $matches[1] . '>' . $matches[2] . '</h2>';
            },
            $html
        ) ?: $html;
    }

    private static function replace_first_markup(string $html, string $pattern, string $replacement): string {
        return preg_replace_callback(
            $pattern,
            static fn (): string => $replacement,
            $html,
            1
        ) ?: $html;
    }

    private static function remove_theme_header_markup(string $html): string {
        $region = self::find_tag_region($html, 'header', ['<header id="site-header"', ' id="site-header"', ' class="site-header"']);

        if ($region === null) {
            self::debug_trace('remove_theme_header_none');
            return $html;
        }

        self::debug_trace('remove_theme_header_region', [
            'start' => (string) $region['start'],
            'end' => (string) $region['end'],
        ]);

        return substr($html, 0, $region['start']) . substr($html, $region['end']);
    }

    private static function replace_or_inject_theme_footer_markup(string $html): string {
        $footer_markup = self::render_site_footer();
        $region = self::find_tag_region($html, 'footer', ['<footer id="page-footer"', ' id="page-footer"', ' class="site-footer"'], true);

        if ($region !== null) {
            self::debug_trace('replace_theme_footer_region', [
                'start' => (string) $region['start'],
                'end' => (string) $region['end'],
            ]);
            return substr($html, 0, $region['start']) . $footer_markup . substr($html, $region['end']);
        }

        if (str_contains($html, 'yhr-site-footer')) {
            self::debug_trace('replace_theme_footer_existing');
            return $html;
        }

        $page_wrap_marker = '<!-- #page-wrap -->';
        $marker_pos = stripos($html, $page_wrap_marker);
        if ($marker_pos === false) {
            self::debug_trace('replace_theme_footer_no_marker');
            return $html;
        }

        self::debug_trace('replace_theme_footer_inject', ['marker' => (string) $marker_pos]);
        return substr($html, 0, $marker_pos) . $footer_markup . "\n\n" . substr($html, $marker_pos);
    }

    /**
     * @return array{start:int,end:int}|null
     */
    private static function find_tag_region(string $html, string $tag, array $anchors, bool $expand_footer_tail = false): ?array {
        $tag_start = null;

        foreach ($anchors as $anchor) {
            $anchor_pos = stripos($html, $anchor);
            if ($anchor_pos === false) {
                continue;
            }

            $prefix = substr($html, 0, $anchor_pos);
            $candidate = strripos($prefix, '<' . $tag);
            if ($candidate === false) {
                continue;
            }

            $tag_start = $candidate;
            break;
        }

        if ($tag_start === null) {
            self::debug_trace('find_tag_region_anchor_miss', ['tag' => $tag]);
            return null;
        }

        $tag_close = stripos($html, '</' . $tag . '>', $tag_start);
        if ($tag_close === false) {
            self::debug_trace('find_tag_region_close_miss', ['tag' => $tag]);
            return null;
        }

        $end = $tag_close + strlen('</' . $tag . '>');

        if ($expand_footer_tail) {
            $tail = substr($html, $end);
            if (preg_match('/^\s*(?:<!--\s*Responsive footer\s*-->\s*)?<style\b[^>]*>.*?<\/style>/is', $tail, $matches) === 1) {
                $end += strlen((string) $matches[0]);
            }
        }

        return [
            'start' => $tag_start,
            'end' => $end,
        ];
    }

    private static function debug_trace(string $label, array $context = []): void {
        return;
    }

    private static function resolve_partner_media_url(int $user_id, array $direct_meta_keys, array $preferred_value_keys, string $size): string {
        foreach ($direct_meta_keys as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            $resolved = self::resolve_media_url_from_value($value, $size, $preferred_value_keys);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        foreach (self::PARTNER_SETTINGS_META_KEYS as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            $resolved = self::resolve_media_url_from_value($value, $size, $preferred_value_keys);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '';
    }

    private static function resolve_media_url_from_value($value, string $size, array $preferred_keys = []): string {
        if (is_numeric($value)) {
            $url = wp_get_attachment_image_url((int) $value, $size);

            return $url ? (string) $url : '';
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }

            if (is_numeric($trimmed)) {
                $url = wp_get_attachment_image_url((int) $trimmed, $size);

                return $url ? (string) $url : '';
            }

            if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                return $trimmed;
            }

            if (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return self::resolve_media_url_from_value($decoded, $size, $preferred_keys);
                }
            }

            return '';
        }

        if (! is_array($value)) {
            return '';
        }

        $ordered_keys = array_values(array_unique(array_merge($preferred_keys, self::GENERIC_MEDIA_VALUE_KEYS)));
        foreach ($ordered_keys as $key) {
            if (! array_key_exists($key, $value)) {
                continue;
            }

            $resolved = self::resolve_media_url_from_value($value[$key], $size, $preferred_keys);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        foreach ($value as $candidate) {
            $resolved = self::resolve_media_url_from_value($candidate, $size, $preferred_keys);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '';
    }

    private static function first_public_product_image_url(int $user_id, string $size, string $partner_type = ''): string {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        $product_id = ! empty($query->posts[0]) ? (int) $query->posts[0] : 0;
        if ($product_id <= 0) {
            return '';
        }

        return self::product_image_url($product_id, $size, self::partner_media_variant($partner_type));
    }

    private static function media_library_fallback_url(string $category, int $seed = 0, string $size = 'large'): string {
        $pool = self::media_library_pool_urls($category, $size);
        if ($pool === []) {
            return '';
        }

        $index = abs($seed) % count($pool);

        return (string) $pool[$index];
    }

    private static function media_library_pool_urls(string $category, string $size = 'large'): array {
        static $cache = [];

        $key = $category . '|' . $size;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $prefix = self::MEDIA_LIBRARY_PREFIXES[$category] ?? '';
        if ($prefix === '') {
            $cache[$key] = [];

            return $cache[$key];
        }

        global $wpdb;

        $like = '%' . $wpdb->esc_like($prefix) . '%';
        $attachment_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT posts.ID
                FROM {$wpdb->posts} AS posts
                LEFT JOIN {$wpdb->postmeta} AS meta
                    ON meta.post_id = posts.ID
                    AND meta.meta_key = '_wp_attached_file'
                WHERE posts.post_type = 'attachment'
                AND posts.post_status = 'inherit'
                AND posts.post_mime_type LIKE 'image/%%'
                AND (
                    LOWER(posts.post_title) LIKE LOWER(%s)
                    OR LOWER(posts.post_name) LIKE LOWER(%s)
                    OR LOWER(meta.meta_value) LIKE LOWER(%s)
                )
                ORDER BY posts.menu_order ASC, posts.post_date ASC, posts.ID ASC
                LIMIT 40",
                $like,
                $like,
                $like
            )
        );

        $urls = [];
        foreach ((array) $attachment_ids as $attachment_id) {
            $url = wp_get_attachment_image_url((int) $attachment_id, $size);
            if ($url) {
                $urls[] = (string) $url;
            }
        }

        $cache[$key] = array_values(array_unique($urls));

        return $cache[$key];
    }

    private static function render_front_page_document(): void {
        status_header(200);
        nocache_headers();
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(['yombal-site', 'yombal-site--home', 'yombal-homepage-document']); ?>>
<?php wp_body_open(); ?>
        <div id="page-wrap">
    <?php echo self::render_site_header(); ?>

    <?php echo self::render_homepage(); ?>

    <?php echo self::render_site_footer(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html><?php
    }
}
