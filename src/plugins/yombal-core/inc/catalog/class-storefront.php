<?php

declare(strict_types=1);

namespace Yombal\Core\Catalog;

use Yombal\Core\Admin\Page_Provisioner;
use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Journeys\Fixtures;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Partners\Profile_Service;
use Yombal\Core\Partners\Public_Pages;
use Yombal\Core\Partners\Roles;

if (! defined('ABSPATH')) {
    exit;
}

final class Storefront {
    private const META_LABELS = [
        'ville' => 'Ville',
        'delai_confection' => 'Delai de confection',
        'matiere_composition' => 'Matiere',
        'largeur' => 'Largeur',
        'couleur_motifs' => 'Couleur / motifs',
        'delai_expedition' => 'Delai d expedition',
    ];

    public static function boot(): void {
        add_shortcode('yombal_public_product_catalog', [self::class, 'render_catalog_shortcode']);
        add_filter('the_content', [self::class, 'replace_public_catalog_pages'], 20);
        add_action('wp', [self::class, 'override_single_product_layout']);
    }

    public static function replace_public_catalog_pages(string $content): string {
        if (is_admin() || ! is_main_query() || ! in_the_loop()) {
            return $content;
        }

        if (is_page('catalogue-tissus')) {
            return self::render_catalog('fabric');
        }

        if (is_page('modeles') || is_page('catalogue-modeles')) {
            return self::render_catalog('model');
        }

        return $content;
    }

    public static function render_catalog_shortcode(array $atts = []): string {
        $atts = shortcode_atts(['mode' => 'fabric'], $atts);

        return self::render_catalog(sanitize_key((string) $atts['mode']));
    }

    public static function override_single_product_layout(): void {
        if (is_admin() || ! function_exists('is_product') || ! is_product()) {
            return;
        }

        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);

        add_action('woocommerce_single_product_summary', [self::class, 'render_single_product_shell'], 5);
    }

    public static function render_single_product_shell(): void {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $product_id = $product->get_id();
        $author_id = (int) get_post_field('post_author', $product_id);
        $profile = Profile_Service::get_profile($author_id);
        $gallery = self::product_gallery($product);
        $specs = self::product_specs($product_id);
        $partner_url = Public_Pages::get_profile_url($author_id);
        $couture_url = Page_Provisioner::get_page_url('demande-couture-yombal');
        $related = self::related_products($product_id, $author_id);

        ob_start();
        ?>
        <div class="yombal-ui yombal-single-product-shell yhr-page-shell yhr-page-shell--product">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-product-hero yhr-page-hero yhr-page-hero--product">
                <div class="yombal-product-hero__media">
                    <?php if ($gallery !== []) : ?>
                        <div class="yombal-product-gallery">
                            <div class="yombal-product-gallery__main">
                                <img src="<?php echo esc_url((string) $gallery[0]['src']); ?>" alt="<?php echo esc_attr((string) $gallery[0]['alt']); ?>">
                            </div>
                            <?php if (count($gallery) > 1) : ?>
                                <div class="yombal-product-gallery__thumbs">
                                    <?php foreach (array_slice($gallery, 0, 4) as $image) : ?>
                                        <img src="<?php echo esc_url((string) $image['src']); ?>" alt="<?php echo esc_attr((string) $image['alt']); ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="yombal-product-hero__content">
                    <div class="yombal-actions">
                        <span class="yombal-badge yombal-badge--accent"><?php echo esc_html(self::product_mode_label($author_id)); ?></span>
                        <span class="yombal-badge yombal-badge--muted"><?php echo esc_html(self::first_term_name($product_id, 'product_cat')); ?></span>
                    </div>
                    <h1><?php echo esc_html($product->get_name()); ?></h1>
                    <div class="yombal-inline-meta">
                        <span><?php echo esc_html((string) ($profile['store_name'] ?? get_the_author_meta('display_name', $author_id))); ?></span>
                        <span><?php echo esc_html((string) get_post_meta($product_id, 'ville', true) ?: 'Disponible sur Yombal'); ?></span>
                    </div>
                    <div class="yombal-product-price"><?php echo wp_kses_post($product->get_price_html() ?: 'Prix sur demande'); ?></div>
                    <?php if ($product->get_short_description() !== '') : ?>
                        <p><?php echo esc_html(wp_strip_all_tags($product->get_short_description())); ?></p>
                    <?php endif; ?>
                    <?php if ($specs !== []) : ?>
                        <div class="yombal-chip-list">
                            <?php foreach (array_slice($specs, 0, 4) as $label => $value) : ?>
                                <span class="yombal-chip"><?php echo esc_html($label . ': ' . $value); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="yombal-product-purchase">
                        <?php woocommerce_template_single_add_to_cart(); ?>
                    </div>
                    <div class="yombal-actions">
                        <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url($partner_url); ?>">Voir le partenaire</a>
                        <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url(Message_Center::compose_url($author_id, ['product_id' => $product_id])); ?>">Poser une question</a>
                        <?php if ($couture_url !== '') : ?>
                            <a class="yombal-button yombal-button--accent" href="<?php echo esc_url($couture_url); ?>">Preparer une demande sur mesure</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="yombal-grid yombal-grid--two">
                <section class="yombal-card">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Description</h2>
                            <div class="yombal-card__meta">Les informations utiles pour comprendre le produit avant de commander.</div>
                        </div>
                    </div>
                    <div class="yombal-product-description">
                        <?php echo wp_kses_post(wpautop($product->get_description() !== '' ? $product->get_description() : $product->get_short_description())); ?>
                    </div>
                </section>

                <section class="yombal-card yombal-card--soft">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Fiche rapide</h2>
                            <div class="yombal-card__meta">Une lecture claire pour aider les clients a se decider plus facilement.</div>
                        </div>
                    </div>
                    <?php if ($specs === []) : ?>
                        <div class="yombal-empty-state">Les details complementaires seront ajoutes prochainement.</div>
                    <?php else : ?>
                        <div class="yombal-stack">
                            <?php foreach ($specs as $label => $value) : ?>
                                <div class="yombal-inline-meta"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="yombal-card yombal-card--soft yombal-product-partner-card">
                <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title">Le partenaire Yombal</h2>
                        <div class="yombal-card__meta">Retrouvez qui propose ce produit et decouvrez son univers.</div>
                    </div>
                </div>
                <div class="yombal-product-partner-card__body">
                    <div class="yombal-inline-meta">
                        <span>Nom</span>
                        <strong><?php echo esc_html((string) ($profile['store_name'] ?? get_the_author_meta('display_name', $author_id))); ?></strong>
                    </div>
                    <div class="yombal-inline-meta">
                        <span>Type</span>
                        <strong><?php echo esc_html(self::product_mode_label($author_id)); ?></strong>
                    </div>
                    <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url($partner_url); ?>">Voir le profil partenaire</a>
                </div>
            </section>

            <?php if ($related !== []) : ?>
                <section class="yombal-card">
                    <div class="yombal-card__header">
                        <div class="yombal-stack">
                            <h2 class="yombal-section-title">Autres produits a decouvrir</h2>
                            <div class="yombal-card__meta">Dans le meme univers ou proposes par le meme partenaire.</div>
                        </div>
                    </div>
                    <div class="yombal-grid yombal-grid--three">
                        <?php foreach ($related as $item) : ?>
                            <article class="yombal-card yombal-card--soft yombal-mini-product-card">
                                <a href="<?php echo esc_url((string) $item['url']); ?>" class="yombal-mini-product-card__media">
                                    <?php if ($item['image'] !== '') : ?>
                                        <img src="<?php echo esc_url((string) $item['image']); ?>" alt="<?php echo esc_attr((string) $item['title']); ?>">
                                    <?php endif; ?>
                                </a>
                                <div class="yombal-stack">
                                    <h3 class="yombal-section-title"><?php echo esc_html((string) $item['title']); ?></h3>
                                    <div class="yombal-inline-meta">
                                        <span><?php echo wp_kses_post((string) $item['price']); ?></span>
                                        <span><?php echo esc_html((string) $item['category']); ?></span>
                                    </div>
                                    <div class="yombal-product-card__actions">
                                        <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url((string) $item['url']); ?>">Voir le produit</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <?php
        echo (string) ob_get_clean();
    }

    private static function render_catalog(string $mode): string {
        $filters = self::current_catalog_filters();
        $products = self::catalog_products($mode, $filters);
        $options = self::catalog_filter_options($mode);
        $title = $mode === 'model' ? 'Modeles et creations Yombal' : 'Catalogue tissus Yombal';
        $eyebrow = $mode === 'model' ? 'Inspirations couture' : 'Tissus et matieres';
        $intro = $mode === 'model'
            ? 'Parcourez les creations mises en avant par les couturiers et ateliers Yombal.'
            : 'Retrouvez les tissus disponibles chez les partenaires Yombal avec une presentation plus claire et plus fiable.';

        ob_start();
        ?>
        <div class="yombal-ui yombal-public-page yombal-product-catalog yhr-page-shell yhr-page-shell--catalog">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero yombal-hero--directory yhr-page-hero">
                <span class="yombal-eyebrow"><?php echo esc_html($eyebrow); ?></span>
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($intro); ?></p>
            </section>

            <section class="yombal-card yombal-card--soft yombal-directory-toolbar">
                <form method="get" class="yombal-directory-search">
                    <label for="yombal-product-search">Rechercher un produit</label>
                    <div class="yombal-inline-form yombal-inline-form--wide">
                        <input id="yombal-product-search" type="search" name="ys" value="<?php echo esc_attr((string) $filters['search']); ?>" placeholder="Nom, categorie ou matiere">
                    </div>
                    <div class="yombal-filter-grid">
                        <div>
                            <label for="yombal-product-city">Ville</label>
                            <select id="yombal-product-city" name="ville">
                                <option value="">Toutes les villes</option>
                                <?php foreach ($options['cities'] as $city) : ?>
                                    <option value="<?php echo esc_attr($city); ?>" <?php selected((string) $filters['city'], $city); ?>><?php echo esc_html($city); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="yombal-product-term"><?php echo esc_html($mode === 'model' ? 'Type de tenue' : 'Matiere'); ?></label>
                            <select id="yombal-product-term" name="term">
                                <option value=""><?php echo esc_html($mode === 'model' ? 'Tous les styles' : 'Toutes les matieres'); ?></option>
                                <?php foreach ($options['terms'] as $term) : ?>
                                    <option value="<?php echo esc_attr($term); ?>" <?php selected((string) $filters['term'], $term); ?>><?php echo esc_html($term); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="yombal-product-sort">Tri</label>
                            <select id="yombal-product-sort" name="sort">
                                <option value="recent" <?php selected((string) $filters['sort'], 'recent'); ?>>Plus recents</option>
                                <option value="price_asc" <?php selected((string) $filters['sort'], 'price_asc'); ?>>Prix croissant</option>
                                <option value="price_desc" <?php selected((string) $filters['sort'], 'price_desc'); ?>>Prix decroissant</option>
                            </select>
                        </div>
                    </div>
                    <div class="yombal-actions">
                        <button type="submit" class="yombal-button yombal-button--accent">Filtrer</button>
                        <?php if (self::has_active_catalog_filters($filters)) : ?>
                            <a href="<?php echo esc_url(remove_query_arg(['ys', 'ville', 'term', 'sort'])); ?>" class="yombal-button yombal-button--secondary">Reinitialiser</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="yombal-results-meta">
                    <div class="yombal-pill yombal-badge yombal-badge--muted"><?php echo esc_html((string) count($products)); ?> produit(s)</div>
                    <div class="yombal-inline-meta">
                        <span><?php echo esc_html($mode === 'model' ? 'Modeles et prestations couture' : 'Tissus separes des tailleurs'); ?></span>
                    </div>
                </div>
            </section>

            <?php if ($products === []) : ?>
                <div class="yombal-empty-state">Aucun produit visible pour le moment.</div>
            <?php else : ?>
                <div class="yombal-grid yombal-grid--product-catalog">
                    <?php foreach ($products as $item) : ?>
                        <article class="yombal-card yombal-card--soft yombal-product-card">
                            <a class="yombal-product-card__media" href="<?php echo esc_url((string) $item['url']); ?>">
                                <?php if ($item['image'] !== '') : ?>
                                    <img src="<?php echo esc_url((string) $item['image']); ?>" alt="<?php echo esc_attr((string) $item['title']); ?>">
                                <?php endif; ?>
                            </a>
                            <div class="yombal-product-card__body">
                                <div class="yombal-actions">
                                    <span class="yombal-badge yombal-badge--accent"><?php echo esc_html((string) $item['category']); ?></span>
                                    <span class="yombal-badge yombal-badge--muted"><?php echo esc_html((string) $item['partner_type']); ?></span>
                                </div>
                                <h2><?php echo esc_html((string) $item['title']); ?></h2>
                                <p><?php echo esc_html((string) $item['excerpt']); ?></p>
                                <?php if ((string) $item['term_label'] !== '' && (string) $item['term_label'] !== 'Produit Yombal' && (string) $item['term_label'] !== (string) $item['category']) : ?>
                                    <div class="yombal-chip-list">
                                        <span class="yombal-chip"><?php echo esc_html((string) $item['term_label']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="yombal-inline-meta">
                                    <span><?php echo esc_html((string) $item['partner_name']); ?></span>
                                    <span><?php echo esc_html((string) $item['city']); ?></span>
                                </div>
                                <div class="yombal-product-card__footer">
                                    <strong class="yombal-product-card__price"><?php echo wp_kses_post((string) $item['price']); ?></strong>
                                    <div class="yombal-product-card__actions">
                                        <a class="yombal-button yombal-button--accent" href="<?php echo esc_url((string) $item['url']); ?>">Voir le produit</a>
                                        <a class="yombal-button yombal-button--secondary" href="<?php echo esc_url((string) $item['partner_url']); ?>">Voir la boutique</a>
                                    </div>
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

    private static function catalog_products(string $mode, array $filters): array {
        $partner_types = $mode === 'model'
            ? [Roles::TYPE_TAILOR, Roles::TYPE_HYBRID]
            : [Roles::TYPE_FABRIC_VENDOR, Roles::TYPE_HYBRID];

        $author_ids = self::partner_ids_for_types($partner_types);
        if ($author_ids === []) {
            return [];
        }

        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 18,
            'orderby' => 'date',
            'order' => 'DESC',
            's' => (string) ($filters['search'] ?? ''),
            'author__in' => $author_ids,
        ]);

        $rows = [];
        foreach ((array) $query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (! $product instanceof \WC_Product) {
                continue;
            }

            $author_id = (int) $post->post_author;
            $profile = Profile_Service::get_profile($author_id);
            $row = [
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'image' => Public_Shell::product_image_url((int) $post->ID, 'large', $mode === 'model' ? 'model' : 'fabric'),
                'excerpt' => wp_trim_words(wp_strip_all_tags($product->get_short_description() ?: $product->get_description()), 18),
                'price' => $product->get_price_html() ?: 'Prix sur demande',
                'price_value' => (float) $product->get_price(),
                'category' => self::first_term_name((int) $post->ID, 'product_cat'),
                'term_label' => $mode === 'model'
                    ? self::first_term_name((int) $post->ID, 'yombal_type_tenue')
                    : ((string) get_post_meta((int) $post->ID, 'matiere_composition', true) ?: self::first_term_name((int) $post->ID, 'yombal_type_tissu')),
                'partner_name' => (string) ($profile['store_name'] ?? get_the_author_meta('display_name', $author_id)),
                'partner_type' => self::product_mode_label($author_id),
                'city' => (string) get_post_meta($post->ID, 'ville', true) ?: ((string) ($profile['city'] ?? 'Disponible sur Yombal')),
                'partner_url' => Public_Pages::get_profile_url($author_id),
                'created_at' => strtotime((string) $post->post_date_gmt) ?: strtotime((string) $post->post_date) ?: 0,
            ];

            if (! self::matches_catalog_filters($row, $filters)) {
                continue;
            }

            $rows[] = $row;
        }

        $sort = (string) ($filters['sort'] ?? 'recent');
        usort($rows, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'price_asc' => $a['price_value'] === $b['price_value']
                    ? ($b['created_at'] <=> $a['created_at'])
                    : ($a['price_value'] <=> $b['price_value']),
                'price_desc' => $a['price_value'] === $b['price_value']
                    ? ($b['created_at'] <=> $a['created_at'])
                    : ($b['price_value'] <=> $a['price_value']),
                default => [$b['created_at'], $b['price_value']] <=> [$a['created_at'], $a['price_value']],
            };
        });

        return $rows;
    }

    private static function partner_ids_for_types(array $types): array {
        global $wpdb;

        $table = Installer::table_name('yombal_partner_profiles');
        $placeholders = implode(', ', array_fill(0, count($types), '%s'));
        $statuses = implode("', '", array_map('esc_sql', Profile_Service::public_statuses()));
        $sql = $wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE partner_type IN ({$placeholders}) AND profile_status IN ('{$statuses}')",
            ...$types
        );
        $ids = $wpdb->get_col($sql);

        return is_array($ids)
            ? Fixtures::filter_public_user_ids(array_values(array_unique(array_map('intval', $ids))))
            : [];
    }

    private static function product_gallery(\WC_Product $product): array {
        $images = [];
        $ids = array_filter(array_unique(array_merge([$product->get_image_id()], $product->get_gallery_image_ids())));

        foreach ($ids as $image_id) {
            $src = wp_get_attachment_image_url($image_id, Public_Shell::public_media_size());
            if (! $src) {
                continue;
            }

            $images[] = [
                'src' => $src,
                'alt' => (string) get_post_meta($image_id, '_wp_attachment_image_alt', true),
            ];
        }

        if ($images === [] && $product->get_image_id() === 0) {
            $author_id = (int) get_post_field('post_author', $product->get_id());
            $images[] = [
                'src' => Public_Shell::placeholder_image_url(self::partner_media_variant($author_id), $product->get_id()),
                'alt' => $product->get_name(),
            ];
        }

        return $images;
    }

    private static function product_specs(int $product_id): array {
        $specs = [];

        foreach (self::META_LABELS as $meta_key => $label) {
            $value = get_post_meta($product_id, $meta_key, true);
            if ($value === '' || $value === null) {
                continue;
            }

            if (in_array($meta_key, ['delai_confection', 'delai_expedition'], true)) {
                $value = $value . ' jour(s)';
            }

            if ($meta_key === 'largeur') {
                $value = $value . ' cm';
            }

            $specs[$label] = (string) $value;
        }

        return $specs;
    }

    private static function related_products(int $product_id, int $author_id): array {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $author_id,
            'post__not_in' => [$product_id],
            'posts_per_page' => 3,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $rows = [];
        $image_variant = self::partner_media_variant($author_id);
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

    private static function first_term_name(int $post_id, string $taxonomy): string {
        $terms = get_the_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || ! is_array($terms) || $terms === []) {
            return 'Produit Yombal';
        }

        return (string) $terms[0]->name;
    }

    private static function product_mode_label(int $author_id): string {
        $profile = Profile_Service::get_profile($author_id);
        $type = (string) ($profile['partner_type'] ?? '');

        return match ($type) {
            Roles::TYPE_TAILOR => 'Couture',
            Roles::TYPE_FABRIC_VENDOR => 'Tissus',
            Roles::TYPE_HYBRID => 'Tissus + couture',
            default => 'Selection Yombal',
        };
    }

    private static function partner_media_variant(int $author_id): string {
        $profile = Profile_Service::get_profile($author_id);

        return Public_Shell::partner_media_variant((string) ($profile['partner_type'] ?? ''));
    }

    private static function current_catalog_filters(): array {
        return [
            'search' => sanitize_text_field((string) ($_GET['ys'] ?? '')),
            'city' => sanitize_text_field((string) ($_GET['ville'] ?? '')),
            'term' => sanitize_text_field((string) ($_GET['term'] ?? '')),
            'sort' => sanitize_key((string) ($_GET['sort'] ?? 'recent')),
        ];
    }

    private static function catalog_filter_options(string $mode): array {
        $cities = [];
        $terms = [];

        foreach (self::catalog_products($mode, []) as $row) {
            if ($row['city'] !== '' && $row['city'] !== 'Disponible sur Yombal') {
                $cities[] = (string) $row['city'];
            }
            if ($row['term_label'] !== '' && $row['term_label'] !== 'Produit Yombal') {
                $terms[] = (string) $row['term_label'];
            }
        }

        $cities = array_values(array_unique($cities));
        natcasesort($cities);
        $terms = array_values(array_unique($terms));
        natcasesort($terms);

        return [
            'cities' => array_values($cities),
            'terms' => array_values($terms),
        ];
    }

    private static function matches_catalog_filters(array $row, array $filters): bool {
        $city = strtolower((string) ($filters['city'] ?? ''));
        if ($city !== '' && strtolower((string) $row['city']) !== $city) {
            return false;
        }

        $term = strtolower((string) ($filters['term'] ?? ''));
        if ($term !== '' && strtolower((string) $row['term_label']) !== $term && strtolower((string) $row['category']) !== $term) {
            return false;
        }

        return true;
    }

    private static function has_active_catalog_filters(array $filters): bool {
        return trim((string) ($filters['search'] ?? '')) !== ''
            || trim((string) ($filters['city'] ?? '')) !== ''
            || trim((string) ($filters['term'] ?? '')) !== ''
            || in_array((string) ($filters['sort'] ?? 'recent'), ['price_asc', 'price_desc'], true);
    }

    private static function vendor_product_count(int $vendor_id): int {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $vendor_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return (int) $query->found_posts;
    }

    private static function decode_profile_items(string $value): array {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        $items = is_array($decoded) ? $decoded : (preg_split('/[,;]+/', $value) ?: []);
        $items = array_map(static fn ($item): string => trim((string) $item), $items);
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

        return array_values(array_unique($items));
    }

    private static function store_intro(array $profile): string {
        $type = (string) ($profile['partner_type'] ?? '');

        return match ($type) {
            Roles::TYPE_FABRIC_VENDOR => 'Une boutique Yombal qui met en avant ses tissus, ses matieres et des offres plus faciles a parcourir.',
            Roles::TYPE_HYBRID => 'Un partenaire Yombal qui combine confection sur mesure et selection de tissus pour un parcours plus complet.',
            default => 'Un atelier Yombal presente de facon plus claire pour aider les clients a choisir, contacter et commander en confiance.',
        };
    }
}
