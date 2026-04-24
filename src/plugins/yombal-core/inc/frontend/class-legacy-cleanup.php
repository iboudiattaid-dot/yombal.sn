<?php

declare(strict_types=1);

namespace Yombal\Core\Frontend;

use Yombal\Core\Partners\Profile_Service;

if (! defined('ABSPATH')) {
    exit;
}

final class Legacy_Cleanup {
    private const CUSTOM_SHELL_PAGES = [
        'espace-client-yombal',
        'espace-partenaire-yombal',
        'devenir-partenaire-yombal',
        'demande-couture-yombal',
        'notifications-yombal',
        'messages-yombal',
        'litiges-yombal',
        'partenaire-yombal',
    ];

    private const SEO_COPY = [
        'connexion' => [
            'title' => 'Connexion Yombal',
            'description' => 'Connectez-vous a votre espace Yombal pour retrouver commandes, mesures, messages et suivi client.',
        ],
        'devenir-partenaire-yombal' => [
            'title' => 'Devenir partenaire Yombal',
            'description' => 'Rejoignez Yombal comme tailleur ou vendeur de tissus avec un onboarding plus clair et un espace partenaire dedie.',
        ],
        'catalogue-tailleurs' => [
            'title' => 'Catalogue tailleurs',
            'description' => 'Trouvez un tailleur Yombal selon votre ville, votre specialite et votre occasion de couture.',
        ],
        'catalogue-tissus' => [
            'title' => 'Catalogue tissus',
            'description' => 'Parcourez les tissus Yombal par matiere, categorie et disponibilite locale.',
        ],
        'catalogue-modeles' => [
            'title' => 'Catalogue modeles',
            'description' => 'Explorez les modeles et inspirations couture proposes par les partenaires Yombal.',
        ],
        'messages-yombal' => [
            'title' => 'Messages Yombal',
            'description' => 'Retrouvez vos conversations avec les partenaires Yombal dans un espace simple et clair.',
        ],
        'litiges-yombal' => [
            'title' => 'Aide et litiges Yombal',
            'description' => 'Posez une question, signalez un probleme et suivez facilement vos demandes d aide sur Yombal.',
        ],
        'espace-client-yombal' => [
            'title' => 'Espace client Yombal',
            'description' => 'Suivez vos commandes, vos mesures et vos notifications dans votre espace client Yombal.',
        ],
        'espace-partenaire-yombal' => [
            'title' => 'Espace partenaire Yombal',
            'description' => 'Gerez vos produits, vos commandes et vos demandes clients depuis votre espace partenaire Yombal.',
        ],
        'a-propos' => [
            'title' => 'A propos de Yombal',
            'description' => 'Decouvrez la vision Yombal et la facon dont la plateforme structure la couture sur mesure au Senegal.',
        ],
        'contact' => [
            'title' => 'Contacter Yombal',
            'description' => 'Contactez l equipe Yombal pour une question, un accompagnement ou un besoin lie a votre commande.',
        ],
        'faq' => [
            'title' => 'FAQ Yombal',
            'description' => 'Retrouvez les reponses essentielles sur les commandes, les partenaires, les tissus et les services Yombal.',
        ],
        'guide-mesures' => [
            'title' => 'Guide des mesures Yombal',
            'description' => 'Suivez les bonnes pratiques Yombal pour prendre vos mesures clairement avant une commande sur mesure.',
        ],
        'guide-tissus' => [
            'title' => 'Guide des tissus Yombal',
            'description' => 'Comprenez les tissus proposes sur Yombal et choisissez la bonne matiere pour votre tenue.',
        ],
        'mentions-legales' => [
            'title' => 'Mentions legales Yombal',
            'description' => 'Consultez les informations legales et d identification de la plateforme Yombal.',
        ],
        'politique-de-confidentialite' => [
            'title' => 'Politique de confidentialite Yombal',
            'description' => 'Comprenez comment Yombal collecte, utilise et protege vos donnees personnelles.',
        ],
        'conditions-generales-dutilisation' => [
            'title' => 'Conditions generales d utilisation Yombal',
            'description' => 'Consultez les conditions d utilisation applicables aux clients et partenaires de Yombal.',
        ],
        'conditions-generales-de-vente' => [
            'title' => 'Conditions generales de vente Yombal',
            'description' => 'Retrouvez les regles applicables aux commandes, paiements, livraisons et engagements sur Yombal.',
        ],
        'politique-remboursements-retours' => [
            'title' => 'Politique de remboursements et retours Yombal',
            'description' => 'Consultez la politique Yombal relative aux retours, retouches et remboursements.',
        ],
    ];

    private const NOINDEX_SLUGS = [
        'connexion',
        'connexion-2',
        'connexion-3',
        'dashboard',
        'dashboard-partenaire',
        'vendor-register',
        'vendor-registration',
        'messages-yombal',
        'notifications-yombal',
        'espace-client-yombal',
        'espace-partenaire-yombal',
        'demande-couture-yombal',
        'litiges-yombal',
    ];

    public static function boot(): void {
        add_action('init', [self::class, 'remove_legacy_hooks'], 100);
        add_filter('body_class', [self::class, 'mark_custom_shell_pages'], 40);
        add_filter('document_title_parts', [self::class, 'filter_document_title']);
        add_filter('wpseo_title', [self::class, 'filter_seo_title']);
        add_filter('wpseo_metadesc', [self::class, 'filter_seo_description']);
        add_filter('wpseo_opengraph_title', [self::class, 'filter_social_title']);
        add_filter('wpseo_twitter_title', [self::class, 'filter_social_title']);
        add_filter('wpseo_opengraph_desc', [self::class, 'filter_seo_description']);
        add_filter('wpseo_twitter_description', [self::class, 'filter_seo_description']);
        add_filter('wpseo_opengraph_url', [self::class, 'filter_social_url']);
        add_filter('wpseo_opengraph_image', [self::class, 'filter_social_image']);
        add_filter('wpseo_twitter_image', [self::class, 'filter_social_image']);
        add_filter('wpseo_canonical', [self::class, 'filter_canonical']);
        add_filter('wpseo_schema_webpage', [self::class, 'filter_schema_webpage']);
        add_filter('wpseo_robots', [self::class, 'filter_wpseo_robots']);
        add_filter('wp_robots', [self::class, 'filter_robots']);
        add_action('wp_head', [self::class, 'render_manual_head_tags'], 1);
    }

    public static function remove_legacy_hooks(): void {
        $hooks = [
            'woocommerce_view_order',
            'woocommerce_my_account_my_orders_actions',
            'gettext',
            'template_redirect',
            'wp_footer',
            'wp_ajax_wcfm_ajax_submit_message',
        ];

        foreach ($hooks as $hook_name) {
            self::remove_callbacks_from_hook($hook_name, [
                'hello.php',
                'v2hello.php',
                'yombal-messagerie.php',
            ]);
        }
    }

    public static function mark_custom_shell_pages(array $classes): array {
        if (is_admin() || ! is_page()) {
            return $classes;
        }

        global $post;
        $slug = $post instanceof \WP_Post ? (string) $post->post_name : '';

        if ($slug !== '' && in_array($slug, self::CUSTOM_SHELL_PAGES, true)) {
            $classes[] = 'y-has-custom-shell';
        }

        return array_values(array_unique($classes));
    }

    public static function filter_document_title(array $parts): array {
        $copy = self::current_copy();
        if ($copy !== null) {
            $parts['title'] = $copy['title'];

            return $parts;
        }

        $dynamic_title = self::dynamic_title();
        if ($dynamic_title !== '') {
            $parts['title'] = $dynamic_title;
        }

        return $parts;
    }

    public static function filter_seo_title(string $title): string {
        $copy = self::current_copy();
        if ($copy !== null) {
            return $copy['title'] . ' | Yombal.sn';
        }

        $dynamic_title = self::dynamic_title();

        return $dynamic_title !== '' ? $dynamic_title . ' | Yombal.sn' : $title;
    }

    public static function filter_social_title(string $title): string {
        $copy = self::current_copy();
        if ($copy !== null) {
            return $copy['title'] . ' | Yombal.sn';
        }

        $dynamic_title = self::dynamic_title();

        return $dynamic_title !== '' ? $dynamic_title . ' | Yombal.sn' : $title;
    }

    public static function filter_seo_description(string $description): string {
        $copy = self::current_copy();
        if ($copy !== null) {
            return $copy['description'];
        }

        $dynamic_description = self::dynamic_description();

        return $dynamic_description !== '' ? $dynamic_description : $description;
    }

    public static function filter_canonical(string $canonical): string {
        $resolved = self::canonical_url();

        return $resolved !== '' ? $resolved : $canonical;
    }

    public static function filter_social_url(string $url): string {
        $resolved = self::canonical_url();

        return $resolved !== '' ? $resolved : $url;
    }

    public static function filter_social_image(string $image): string {
        if (self::is_noindex_page()) {
            return $image;
        }

        $resolved = Public_Shell::contextual_social_image_url();

        return $resolved !== '' ? $resolved : $image;
    }

    public static function filter_schema_webpage(array $data): array {
        $resolved_url = self::canonical_url();
        if ($resolved_url !== '') {
            $data['url'] = $resolved_url;
            $data['@id'] = $resolved_url;
        }

        $title = self::current_copy()['title'] ?? self::dynamic_title();
        if ($title !== '') {
            $data['name'] = $title . ' | Yombal.sn';
        }

        $description = self::filter_seo_description('');
        if ($description !== '') {
            $data['description'] = $description;
        }

        $image = self::filter_social_image('');
        if ($image !== '') {
            $data['image'] = [
                '@type' => 'ImageObject',
                'url' => $image,
            ];
        }

        return $data;
    }

    public static function filter_robots(array $robots): array {
        if (! self::is_noindex_page()) {
            return $robots;
        }

        $robots['index'] = false;
        $robots['follow'] = ! is_page('connexion');

        return $robots;
    }

    public static function filter_wpseo_robots($robots) {
        if (! self::is_noindex_page()) {
            return $robots;
        }

        return is_page('connexion') ? 'noindex, follow' : 'noindex, nofollow';
    }

    public static function render_manual_head_tags(): void {
        if (is_page('connexion')) {
            echo '<link rel="canonical" href="' . esc_url(home_url('/connexion/')) . '">' . "\n";
        }

        if (self::is_noindex_page()) {
            return;
        }

        $image = self::filter_social_image('');
        if ($image === '') {
            return;
        }

        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
    }

    private static function current_copy(): ?array {
        if (! is_page()) {
            return null;
        }

        global $post;
        $slug = $post instanceof \WP_Post ? (string) $post->post_name : '';

        return self::SEO_COPY[$slug] ?? null;
    }

    private static function is_noindex_page(): bool {
        if (! is_page()) {
            return false;
        }

        global $post;
        $slug = $post instanceof \WP_Post ? (string) $post->post_name : '';

        return $slug !== '' && in_array($slug, self::NOINDEX_SLUGS, true);
    }

    private static function dynamic_title(): string {
        $store = self::current_store_profile();
        if ($store !== null) {
            return $store['store_name'];
        }

        if (function_exists('is_product') && is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());

            return $product instanceof \WC_Product ? trim((string) $product->get_name()) : '';
        }

        return '';
    }

    private static function dynamic_description(): string {
        $store = self::current_store_profile();
        if ($store !== null) {
            if ($store['biography'] !== '') {
                return wp_trim_words($store['biography'], 28, '');
            }

            $segments = array_filter([
                $store['store_name'] !== '' ? 'Decouvrez ' . $store['store_name'] . ' sur Yombal.' : '',
                $store['partner_type'] !== '' ? 'Activite: ' . $store['partner_type'] . '.' : '',
                $store['city'] !== '' ? 'Ville: ' . $store['city'] . '.' : '',
            ]);

            return implode(' ', $segments);
        }

        if (function_exists('is_product') && is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
            if (! $product instanceof \WC_Product) {
                return '';
            }

            $source = trim(wp_strip_all_tags((string) ($product->get_short_description() ?: $product->get_description())));

            return $source !== '' ? wp_trim_words($source, 28, '') : '';
        }

        return '';
    }

    private static function canonical_url(): string {
        $store = self::current_store_profile();
        if ($store !== null) {
            return home_url('/store/' . $store['slug'] . '/');
        }

        if (is_page('connexion')) {
            return home_url('/connexion/');
        }

        if (is_page('modeles') || is_page('catalogue-modeles')) {
            return home_url('/catalogue-modeles/');
        }

        if (is_page() || is_singular('post') || (function_exists('is_product') && is_product())) {
            $permalink = get_permalink(get_queried_object_id());
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        return '';
    }

    private static function current_store_profile(): ?array {
        if (! is_page('partenaire-yombal')) {
            return null;
        }

        $slug = sanitize_title((string) get_query_var('partner', ''));
        if ($slug === '') {
            return null;
        }

        $user = get_user_by('slug', $slug);
        if (! $user instanceof \WP_User) {
            return null;
        }

        $profile = Profile_Service::get_profile((int) $user->ID);

        return [
            'user_id' => (int) $user->ID,
            'slug' => $slug,
            'store_name' => trim((string) ($profile['store_name'] ?? $user->display_name)),
            'city' => trim((string) ($profile['city'] ?? '')),
            'partner_type' => trim((string) ($profile['partner_type'] ?? '')),
            'biography' => trim(wp_strip_all_tags((string) ($profile['biography'] ?? ''))),
        ];
    }

    private static function remove_callbacks_from_hook(string $hook_name, array $target_files): void {
        global $wp_filter;

        if (! isset($wp_filter[$hook_name])) {
            return;
        }

        $hook = $wp_filter[$hook_name];
        if (! is_object($hook) || ! isset($hook->callbacks) || ! is_array($hook->callbacks)) {
            return;
        }

        foreach ($hook->callbacks as $priority => $callbacks) {
            if (! is_array($callbacks)) {
                continue;
            }

            foreach ($callbacks as $callback) {
                if (! isset($callback['function'])) {
                    continue;
                }

                $callable = $callback['function'];
                $file = self::callback_file($callable);
                if ($file === null) {
                    continue;
                }

                if (! in_array(strtolower(wp_basename($file)), $target_files, true)) {
                    continue;
                }

                remove_filter($hook_name, $callable, (int) $priority);
            }
        }
    }

    private static function callback_file($callable): ?string {
        try {
            if ($callable instanceof \Closure) {
                return (new \ReflectionFunction($callable))->getFileName() ?: null;
            }

            if (is_string($callable) && function_exists($callable)) {
                return (new \ReflectionFunction($callable))->getFileName() ?: null;
            }

            if (is_array($callable) && count($callable) === 2) {
                return (new \ReflectionMethod($callable[0], (string) $callable[1]))->getFileName() ?: null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
