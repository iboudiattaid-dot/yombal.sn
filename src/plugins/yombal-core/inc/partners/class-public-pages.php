<?php

declare(strict_types=1);

namespace Yombal\Core\Partners;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Journeys\Fixtures;
use Yombal\Core\Messages\Message_Center;

if (! defined('ABSPATH')) {
    exit;
}

final class Public_Pages {
    public static function boot(): void {
        add_shortcode('yombal_public_partner_catalog', [self::class, 'render_catalog_shortcode']);
        add_shortcode('yombal_public_partner_profile', [self::class, 'render_profile_shortcode']);
        add_action('init', [self::class, 'register_routes'], 20);
        add_action('admin_init', [self::class, 'maybe_flush_rewrite_rules']);
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_filter('the_content', [self::class, 'replace_public_pages'], 20);
        add_action('template_redirect', [self::class, 'redirect_legacy_store_pages'], 4);
    }

    public static function register_routes(): void {
        add_rewrite_tag('%partner%', '([^&]+)');
        add_rewrite_rule('^store/([^/]+)/?$', 'index.php?pagename=partenaire-yombal&partner=$matches[1]', 'top');
    }

    public static function maybe_flush_rewrite_rules(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (get_option('yombal_core_public_partner_routes_version') === '1') {
            return;
        }

        self::register_routes();
        flush_rewrite_rules(false);
        update_option('yombal_core_public_partner_routes_version', '1', false);
    }

    public static function register_query_vars(array $vars): array {
        $vars[] = 'partner';

        return array_values(array_unique($vars));
    }

    public static function replace_public_pages(string $content): string {
        if (is_admin() || ! is_main_query() || ! in_the_loop()) {
            return $content;
        }

        if (is_page('catalogue-tailleurs')) {
            return self::render_catalog([
                'title' => 'Catalogue des tailleurs Yombal',
                'eyebrow' => 'Partenaires couture',
                'intro' => 'Decouvrez les couturiers et ateliers qui confectionnent sur mesure avec le style et le soin Yombal.',
                'types' => [Roles::TYPE_TAILOR, Roles::TYPE_HYBRID],
                'empty' => 'Aucun partenaire couture visible pour le moment.',
            ]);
        }

        if (is_page('partenaire-yombal')) {
            return self::render_profile_page();
        }

        return $content;
    }

    public static function redirect_legacy_store_pages(): void {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (! is_page('partenaire-yombal')) {
            return;
        }

        $slug = sanitize_title((string) ($_GET['partner'] ?? ''));
        if ($slug === '') {
            return;
        }

        wp_safe_redirect(home_url('/store/' . $slug . '/'));
        exit;
    }

    public static function render_catalog_shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Catalogue des partenaires Yombal',
            'eyebrow' => 'Partenaires Yombal',
            'intro' => 'Retrouvez les ateliers et partenaires qui font vivre Yombal.',
            'types' => '',
            'empty' => 'Aucun partenaire disponible pour le moment.',
        ], $atts);

        $types = array_filter(array_map('sanitize_key', array_map('trim', explode(',', (string) $atts['types']))));

        return self::render_catalog([
            'title' => (string) $atts['title'],
            'eyebrow' => (string) $atts['eyebrow'],
            'intro' => (string) $atts['intro'],
            'types' => $types,
            'empty' => (string) $atts['empty'],
        ]);
    }

    public static function render_profile_shortcode(): string {
        return self::render_profile_page();
    }

    public static function get_profile_page_url(): string {
        return Page_Provisioner::get_page_url('partenaire-yombal') ?: home_url('/partenaire-yombal/');
    }

    public static function get_profile_url(int $user_id): string {
        $user = get_userdata($user_id);
        $slug = $user ? sanitize_title((string) $user->user_nicename) : (string) $user_id;

        return home_url('/store/' . $slug . '/');
    }

    public static function featured_partners(int $limit = 3): array {
        $partners = self::get_public_partners([Roles::TYPE_TAILOR, Roles::TYPE_HYBRID], ['sort' => 'recommended']);

        foreach ($partners as &$partner) {
            $weekly_orders = Partner_Stats::count_orders_since((int) ($partner['user_id'] ?? 0), 7);
            $partner['weekly_orders_count'] = $weekly_orders;
            $partner['orders_count'] = $weekly_orders;
        }
        unset($partner);

        usort($partners, static function (array $a, array $b): int {
            return [
                (int) ($b['weekly_orders_count'] ?? 0),
                (int) ($b['products_count'] ?? 0),
                (float) ($b['rating'] ?? 0),
                (int) ($b['total_orders_count'] ?? $b['orders_count'] ?? 0),
                (int) ($b['registered_at'] ?? 0),
            ] <=> [
                (int) ($a['weekly_orders_count'] ?? 0),
                (int) ($a['products_count'] ?? 0),
                (float) ($a['rating'] ?? 0),
                (int) ($a['total_orders_count'] ?? $a['orders_count'] ?? 0),
                (int) ($a['registered_at'] ?? 0),
            ];
        });

        return array_slice($partners, 0, max(1, $limit));
    }

    public static function render_catalog(array $args): string {
        $filters = self::current_filters();
        $partners = self::get_public_partners((array) ($args['types'] ?? []), $filters);
        $options = self::directory_filter_options((array) ($args['types'] ?? []));

        ob_start();
        ?>
        <div class="yombal-ui yombal-public-page yombal-partner-directory yhr-page-shell yhr-page-shell--directory">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero yombal-hero--directory yhr-page-hero">
                <span class="yombal-eyebrow"><?php echo esc_html((string) ($args['eyebrow'] ?? 'Partenaires Yombal')); ?></span>
                <h1><?php echo esc_html((string) ($args['title'] ?? 'Catalogue des partenaires Yombal')); ?></h1>
                <p><?php echo esc_html((string) ($args['intro'] ?? 'Retrouvez les partenaires Yombal.')); ?></p>
            </section>

            <section class="yombal-card yombal-card--soft yombal-directory-toolbar">
                <form method="get" class="yombal-directory-search">
                    <label for="yombal-partner-search">Rechercher un partenaire</label>
                    <div class="yombal-inline-form yombal-inline-form--wide">
                        <input id="yombal-partner-search" type="search" name="ys" value="<?php echo esc_attr((string) $filters['search']); ?>" placeholder="Nom, ville ou specialite">
                    </div>
                    <div class="yombal-filter-grid">
                        <div>
                            <label for="yombal-partner-city">Ville</label>
                            <select id="yombal-partner-city" name="ville">
                                <option value="">Toutes les villes</option>
                                <?php foreach ($options['cities'] as $city) : ?>
                                    <option value="<?php echo esc_attr($city); ?>" <?php selected((string) $filters['city'], $city); ?>><?php echo esc_html($city); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="yombal-partner-specialty">Specialite</label>
                            <select id="yombal-partner-specialty" name="specialty">
                                <option value="">Toutes les specialites</option>
                                <?php foreach ($options['specialties'] as $specialty) : ?>
                                    <option value="<?php echo esc_attr($specialty); ?>" <?php selected((string) $filters['specialty'], $specialty); ?>><?php echo esc_html($specialty); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="yombal-partner-availability">Disponibilite</label>
                            <select id="yombal-partner-availability" name="availability">
                                <option value="all" <?php selected((string) $filters['availability'], 'all'); ?>>Tous</option>
                                <option value="available" <?php selected((string) $filters['availability'], 'available'); ?>>Avec offres visibles</option>
                            </select>
                        </div>
                        <div>
                            <label for="yombal-partner-sort">Tri</label>
                            <select id="yombal-partner-sort" name="sort">
                                <option value="recommended" <?php selected((string) $filters['sort'], 'recommended'); ?>>Pertinence</option>
                                <option value="rating" <?php selected((string) $filters['sort'], 'rating'); ?>>Meilleures notes</option>
                                <option value="recent" <?php selected((string) $filters['sort'], 'recent'); ?>>Plus recents</option>
                                <option value="catalog" <?php selected((string) $filters['sort'], 'catalog'); ?>>Plus d offres</option>
                            </select>
                        </div>
                    </div>
                    <div class="yombal-actions">
                        <button type="submit" class="yombal-button yombal-button--accent">Filtrer</button>
                        <?php if (self::has_active_filters($filters)) : ?>
                            <a href="<?php echo esc_url(remove_query_arg(['ys', 'ville', 'specialty', 'availability', 'sort'])); ?>" class="yombal-button yombal-button--secondary">Reinitialiser</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="yombal-results-meta">
                    <div class="yombal-pill yombal-badge yombal-badge--muted"><?php echo esc_html((string) count($partners)); ?> partenaire(s)</div>
                    <div class="yombal-inline-meta">
                        <span>Recherche locale</span>
                        <span>Catalogue couture distinct des tissus</span>
                    </div>
                </div>
            </section>

            <?php if ($partners === []) : ?>
                <div class="yombal-empty-state"><?php echo esc_html((string) ($args['empty'] ?? 'Aucun partenaire disponible.')); ?></div>
            <?php else : ?>
                <div class="yombal-grid yombal-grid--partner-directory">
                    <?php foreach ($partners as $partner) : ?>
                        <article class="yombal-card yombal-card--soft yombal-partner-card">
                            <div class="yombal-partner-card__media">
                                <img src="<?php echo esc_url((string) $partner['cover_image']); ?>" alt="<?php echo esc_attr((string) $partner['name']); ?>">
                            </div>
                            <div class="yombal-partner-card__body">
                                <div class="yombal-actions">
                                    <span class="yombal-badge yombal-badge--accent"><?php echo esc_html((string) $partner['type_label']); ?></span>
                                    <?php if ($partner['rating'] !== '') : ?>
                                        <span class="yombal-badge yombal-badge--muted"><?php echo esc_html((string) $partner['rating']); ?>/5</span>
                                    <?php endif; ?>
                                </div>
                                <h2><?php echo esc_html((string) $partner['name']); ?></h2>
                                <p class="yombal-partner-card__meta"><?php echo esc_html((string) $partner['city']); ?></p>
                                <p><?php echo esc_html((string) $partner['excerpt']); ?></p>
                                <div class="yombal-inline-meta">
                                    <span><?php echo esc_html((string) $partner['products_count']); ?> produit(s)</span>
                                    <span><?php echo esc_html((string) $partner['orders_count']); ?> commande(s)</span>
                                </div>
                                <?php if ($partner['specialties'] !== []) : ?>
                                    <div class="yombal-chip-list">
                                        <?php foreach (array_slice((array) $partner['specialties'], 0, 3) as $specialty) : ?>
                                            <span class="yombal-chip"><?php echo esc_html((string) $specialty); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="yombal-actions">
                                    <a class="yombal-button yombal-button--accent" href="<?php echo esc_url((string) $partner['url']); ?>">Voir le profil</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_profile_page(): string {
        $partner_slug = (string) ($_GET['partner'] ?? get_query_var('partner', ''));
        $partner = self::find_partner($partner_slug);

        ob_start();
        ?>
        <div class="yombal-ui yombal-public-page yombal-partner-profile-page yhr-page-shell yhr-page-shell--profile">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <?php if (! $partner) : ?>
                <section class="yombal-hero yhr-page-hero">
                    <span class="yombal-eyebrow">Profil partenaire</span>
                    <h1>Partenaire introuvable</h1>
                    <p>Ce profil n est pas disponible pour le moment. Vous pouvez revenir au catalogue des partenaires Yombal.</p>
                    <div class="yombal-hero-actions">
                        <a class="yombal-button yombal-button--accent" href="<?php echo esc_url(home_url('/catalogue-tailleurs/')); ?>">Voir les tailleurs</a>
                    </div>
                </section>
            <?php else : ?>
                <section class="yombal-hero yombal-hero--partner-profile yhr-page-hero">
                    <span class="yombal-eyebrow">Profil partenaire</span>
                    <h1><?php echo esc_html((string) $partner['name']); ?></h1>
                    <div class="yombal-inline-meta">
                        <span><?php echo esc_html((string) $partner['type_label']); ?></span>
                        <span><?php echo esc_html((string) $partner['city']); ?></span>
                        <?php if ($partner['rating'] !== '') : ?>
                            <span><?php echo esc_html((string) $partner['rating']); ?>/5</span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo esc_html((string) $partner['bio']); ?></p>
                    <div class="yombal-hero-actions">
                        <?php if ($partner['products_url'] !== '') : ?>
                            <a class="yombal-button yombal-button--accent" href="<?php echo esc_url((string) $partner['products_url']); ?>">Voir les produits</a>
                        <?php endif; ?>
                        <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url(Message_Center::compose_url((int) $partner['user_id'])); ?>">Contacter ce partenaire</a>
                        <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url(home_url('/demande-couture-yombal/')); ?>">Preparer une demande</a>
                    </div>
                </section>

                <section class="yombal-grid yombal-grid--partner-profile-head">
                    <article class="yombal-card yombal-card--soft yombal-partner-profile-card">
                        <div class="yombal-partner-profile-card__media">
                            <img class="yombal-partner-profile-card__cover" src="<?php echo esc_url((string) $partner['cover_image']); ?>" alt="<?php echo esc_attr((string) $partner['name']); ?>">
                            <img class="yombal-partner-profile-card__avatar" src="<?php echo esc_url((string) $partner['avatar']); ?>" alt="<?php echo esc_attr((string) $partner['name']); ?>">
                        </div>
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">A propos</h2>
                            <div class="yombal-inline-meta"><span>Telephone</span><strong><?php echo esc_html((string) $partner['phone']); ?></strong></div>
                            <div class="yombal-inline-meta"><span>Ville</span><strong><?php echo esc_html((string) $partner['city']); ?></strong></div>
                            <div class="yombal-inline-meta"><span>Membre depuis</span><strong><?php echo esc_html((string) $partner['since']); ?></strong></div>
                        </div>
                    </article>

                    <article class="yombal-card yombal-partner-profile-stats">
                        <div class="yombal-card__header">
                            <div class="yombal-stack">
                                <h2 class="yombal-section-title">Points cles</h2>
                                <div class="yombal-card__meta">Une vue simple pour aider les clients a choisir le bon partenaire.</div>
                            </div>
                        </div>
                        <div class="yombal-grid yombal-grid--stats">
                            <div class="yombal-card yombal-stat">
                                <div class="yombal-stat__value"><?php echo esc_html((string) $partner['products_count']); ?></div>
                                <div class="yombal-stat__label">Produits publies</div>
                            </div>
                            <div class="yombal-card yombal-stat">
                                <div class="yombal-stat__value"><?php echo esc_html((string) $partner['orders_count']); ?></div>
                                <div class="yombal-stat__label">Commandes</div>
                            </div>
                            <div class="yombal-card yombal-stat">
                                <div class="yombal-stat__value"><?php echo esc_html($partner['rating'] !== '' ? (string) $partner['rating'] : '-'); ?></div>
                                <div class="yombal-stat__label">Note moyenne</div>
                            </div>
                        </div>
                    </article>
                </section>

                <div class="yombal-grid yombal-grid--two">
                    <?php echo self::render_list_section('Specialites', 'Les domaines mis en avant sur ce profil.', (array) $partner['specialties']); ?>
                    <?php echo self::render_list_section('Matieres et univers', 'Les tissus, categories ou styles les plus presentes.', (array) $partner['materials']); ?>
                </div>

                <section class="yombal-card">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Produits presentes</h2>
                            <div class="yombal-card__meta">Un apercu des produits publics de ce partenaire.</div>
                        </div>
                    </div>
                    <?php if ($partner['products'] === []) : ?>
                        <div class="yombal-empty-state">Aucun produit public pour le moment.</div>
                    <?php else : ?>
                        <div class="yombal-grid yombal-grid--three">
                            <?php foreach ((array) $partner['products'] as $product) : ?>
                                <article class="yombal-card yombal-card--soft yombal-mini-product-card">
                                    <a href="<?php echo esc_url((string) $product['url']); ?>" class="yombal-mini-product-card__media">
                                        <img src="<?php echo esc_url((string) $product['image']); ?>" alt="<?php echo esc_attr((string) $product['title']); ?>">
                                    </a>
                                    <div class="yombal-stack">
                                        <h3 class="yombal-section-title"><?php echo esc_html((string) $product['title']); ?></h3>
                                        <div class="yombal-inline-meta">
                                            <span><?php echo wp_kses_post((string) $product['price']); ?></span>
                                            <span><?php echo esc_html((string) $product['category']); ?></span>
                                        </div>
                                        <a href="<?php echo esc_url((string) $product['url']); ?>">Voir le produit</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_list_section(string $title, string $meta, array $items): string {
        ob_start();
        if ($items === []) {
            echo '<div class="yombal-empty-state">Aucune information communiquee pour le moment.</div>';
        } else {
            echo '<div class="yombal-chip-list">';
            foreach ($items as $item) {
                echo '<span class="yombal-chip">' . esc_html((string) $item) . '</span>';
            }
            echo '</div>';
        }

        return '<section class="yombal-card"><div class="yombal-card__header"><div class="yombal-stack"><h2 class="yombal-section-title">' .
            esc_html($title) .
            '</h2><div class="yombal-card__meta">' .
            esc_html($meta) .
            '</div></div></div>' .
            (string) ob_get_clean() .
            '</section>';
    }

    private static function get_public_partners(array $types, array $filters = []): array {
        $rows = self::load_profile_rows();
        $partners = [];

        foreach ($rows as $row) {
            $partner_type = (string) ($row['partner_type'] ?? '');
            if ($types !== [] && ! in_array($partner_type, $types, true)) {
                continue;
            }

            $partner = self::build_partner((int) ($row['user_id'] ?? 0), $row);
            if (! $partner) {
                continue;
            }

            if (! self::matches_directory_filters($partner, $filters)) {
                continue;
            }

            $partners[] = $partner;
        }

        $sort = (string) ($filters['sort'] ?? 'recommended');
        usort($partners, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'recent' => [$b['registered_at'], $b['products_count'], $b['orders_count']] <=> [$a['registered_at'], $a['products_count'], $a['orders_count']],
                'catalog' => [$b['products_count'], $b['orders_count'], $b['registered_at']] <=> [$a['products_count'], $a['orders_count'], $a['registered_at']],
                'rating' => [$b['rating'], $b['products_count'], $b['orders_count']] <=> [$a['rating'], $a['products_count'], $a['orders_count']],
                default => [$b['products_count'], $b['orders_count'], $b['rating'], $b['registered_at']] <=> [$a['products_count'], $a['orders_count'], $a['rating'], $a['registered_at']],
            };
        });

        return $partners;
    }

    private static function find_partner(string $identifier): ?array {
        $identifier = sanitize_title($identifier);
        if ($identifier === '') {
            return null;
        }

        foreach (self::load_profile_rows() as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $user = get_userdata($user_id);
            $slug = $user ? sanitize_title((string) $user->user_nicename) : '';
            if ($slug !== $identifier && (string) $user_id !== $identifier) {
                continue;
            }

            return self::build_partner($user_id, $row, true);
        }

        return null;
    }

    private static function build_partner(int $user_id, array $row, bool $with_products = false): ?array {
        if ($user_id <= 0) {
            return null;
        }

        $user = get_userdata($user_id);
        if (! $user) {
            return null;
        }

        $specialties = self::decode_list((string) ($row['specialties'] ?? ''));
        $materials = self::decode_list((string) ($row['materials'] ?? ''));
        $bio = trim((string) ($row['biography'] ?? ''));
        $store_name = trim((string) ($row['store_name'] ?? ''));
        $name = $store_name !== '' ? $store_name : (string) $user->display_name;
        $city = trim((string) ($row['city'] ?? ''));
        if ($city === '') {
            $city = trim((string) get_user_meta($user_id, 'billing_city', true));
        }
        if ($city === '') {
            $city = trim((string) get_user_meta($user_id, 'shipping_city', true));
        }
        $phone = trim((string) ($row['phone'] ?? ''));
        $type = (string) ($row['partner_type'] ?? '');
        $lead_product = self::lead_product($user_id, $type);
        $orders_count = self::count_orders($user_id);
        $weekly_orders_count = Partner_Stats::count_orders_since($user_id, 7);

        return [
            'user_id' => $user_id,
            'slug' => sanitize_title((string) $user->user_nicename),
            'name' => $name,
            'type_label' => self::partner_type_label($type),
            'type_key' => $type,
            'city' => $city !== '' ? $city : 'Ville a preciser',
            'phone' => $phone !== '' ? $phone : 'Contact sur demande',
            'avatar' => Public_Shell::partner_logo_url($user_id, $name, $type),
            'cover_image' => Public_Shell::partner_cover_url($user_id, $type, $user_id),
            'specialties' => $specialties,
            'materials' => $materials,
            'bio' => $bio !== '' ? $bio : 'Ce partenaire complete actuellement sa presentation sur Yombal.',
            'excerpt' => $bio !== '' ? wp_trim_words($bio, 18) : 'Decouvrez le savoir-faire de ce partenaire Yombal.',
            'products_count' => self::count_products($user_id),
            'orders_count' => $orders_count,
            'total_orders_count' => $orders_count,
            'weekly_orders_count' => $weekly_orders_count,
            'rating' => self::get_rating($user_id),
            'since' => wp_date('Y', strtotime((string) $user->user_registered)),
            'registered_at' => strtotime((string) $user->user_registered) ?: 0,
            'url' => self::get_profile_url($user_id),
            'products_url' => (string) $lead_product['url'],
            'products' => $with_products ? self::get_partner_products($user_id, $type) : [],
        ];
    }

    private static function load_profile_rows(): array {
        global $wpdb;

        $table = Installer::table_name('yombal_partner_profiles');
        $statuses = implode("', '", array_map('esc_sql', Profile_Service::public_statuses()));
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE profile_status IN ('{$statuses}') ORDER BY updated_at DESC",
            ARRAY_A
        );
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static function (array $row): bool {
            $user_id = (int) ($row['user_id'] ?? 0);

            return $user_id > 0 && ! Fixtures::is_fixture_user($user_id);
        }));
    }

    private static function get_partner_products(int $user_id, string $partner_type = ''): array {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 6,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $rows = [];
        $image_variant = Public_Shell::partner_media_variant($partner_type);
        foreach ((array) $query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (! $product instanceof \WC_Product) {
                continue;
            }

            $rows[] = [
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'image' => Public_Shell::product_image_url((int) $post->ID, Public_Shell::public_media_size(), $image_variant),
                'price' => $product->get_price_html() ?: 'Prix sur demande',
                'category' => self::first_term_name((int) $post->ID, 'product_cat'),
            ];
        }

        return $rows;
    }

    private static function lead_product(int $user_id, string $partner_type = ''): array {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        $product_id = ! empty($query->posts[0]) ? (int) $query->posts[0] : 0;

        return [
            'url' => $product_id > 0 ? (string) get_permalink($product_id) : '',
            'image' => $product_id > 0
                ? Public_Shell::product_image_url($product_id, Public_Shell::public_media_size(), Public_Shell::partner_media_variant($partner_type))
                : Public_Shell::placeholder_image_url(Public_Shell::partner_media_variant($partner_type), $user_id),
        ];
    }

    private static function first_product_url(int $user_id): string {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return ! empty($query->posts[0]) ? (string) get_permalink((int) $query->posts[0]) : '';
    }

    private static function count_products(int $user_id): int {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return (int) $query->found_posts;
    }

    private static function count_orders(int $user_id): int {
        return Partner_Stats::count_orders($user_id);
    }

    private static function get_rating(int $user_id): string {
        return Partner_Stats::average_rating($user_id);
    }

    private static function partner_type_label(string $type): string {
        return match ($type) {
            Roles::TYPE_TAILOR => 'Couturier',
            Roles::TYPE_FABRIC_VENDOR => 'Vendeur de tissus',
            Roles::TYPE_HYBRID => 'Atelier tissus + couture',
            default => 'Partenaire Yombal',
        };
    }

    private static function decode_list(string $value): array {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return [];
    }

    private static function first_term_name(int $post_id, string $taxonomy): string {
        $terms = get_the_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || ! is_array($terms) || $terms === []) {
            return 'Catalogue Yombal';
        }

        return (string) $terms[0]->name;
    }

    private static function current_filters(): array {
        return [
            'search' => sanitize_text_field((string) ($_GET['ys'] ?? '')),
            'city' => sanitize_text_field((string) ($_GET['ville'] ?? '')),
            'specialty' => sanitize_text_field((string) ($_GET['specialty'] ?? $_GET['specialite'] ?? '')),
            'availability' => sanitize_key((string) ($_GET['availability'] ?? 'all')),
            'sort' => sanitize_key((string) ($_GET['sort'] ?? 'recommended')),
        ];
    }

    private static function directory_filter_options(array $types): array {
        $cities = [];
        $specialties = [];

        foreach (self::get_public_partners($types, []) as $partner) {
            if ($partner['city'] !== '' && $partner['city'] !== 'Ville a preciser') {
                $cities[] = (string) $partner['city'];
            }

            foreach ((array) $partner['specialties'] as $specialty) {
                if ($specialty !== '') {
                    $specialties[] = (string) $specialty;
                }
            }
        }

        $cities = array_values(array_unique($cities));
        natcasesort($cities);
        $specialties = array_values(array_unique($specialties));
        natcasesort($specialties);

        return [
            'cities' => array_values($cities),
            'specialties' => array_values($specialties),
        ];
    }

    private static function matches_directory_filters(array $partner, array $filters): bool {
        $search = strtolower((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                (string) $partner['name'],
                (string) $partner['city'],
                implode(' ', (array) $partner['specialties']),
                implode(' ', (array) $partner['materials']),
            ]));
            if (! str_contains($haystack, $search)) {
                return false;
            }
        }

        $city = strtolower((string) ($filters['city'] ?? ''));
        if ($city !== '' && strtolower((string) $partner['city']) !== $city) {
            return false;
        }

        $specialty = strtolower((string) ($filters['specialty'] ?? ''));
        if ($specialty !== '') {
            $specialties = array_map('strtolower', array_map('strval', (array) $partner['specialties']));
            if (! in_array($specialty, $specialties, true)) {
                return false;
            }
        }

        if (($filters['availability'] ?? 'all') === 'available' && (int) $partner['products_count'] <= 0) {
            return false;
        }

        return true;
    }

    private static function has_active_filters(array $filters): bool {
        foreach (['search', 'city', 'specialty'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return in_array((string) ($filters['availability'] ?? 'all'), ['available'], true)
            || in_array((string) ($filters['sort'] ?? 'recommended'), ['rating', 'recent', 'catalog'], true);
    }
}
