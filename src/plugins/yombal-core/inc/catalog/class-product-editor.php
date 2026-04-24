<?php

declare(strict_types=1);

namespace Yombal\Core\Catalog;

use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Partners\Profile_Service;

if (! defined('ABSPATH')) {
    exit;
}

final class Product_Editor {
    private const META_FIELDS = [
        'ville' => 'Ville',
        'delai_confection' => 'Delai de confection (jours)',
        'matiere_composition' => 'Matiere / composition',
        'largeur' => 'Largeur (cm)',
        'couleur_motifs' => 'Couleur / motifs',
        'delai_expedition' => 'Delai d expedition (jours)',
    ];

    private const EXTRA_TAXONOMIES = [
        'product_cat' => 'Categories',
        'yombal_type_tenue' => 'Types de tenues',
        'yombal_occasion' => 'Occasions',
        'yombal_genre' => 'Genre',
        'yombal_specialite' => 'Specialites',
        'yombal_type_tissu' => 'Types de tissus',
    ];

    public static function boot(): void {
        add_shortcode('yombal_partner_products', [self::class, 'render_products_page']);
        add_shortcode('yombal_partner_product_editor', [self::class, 'render_products_page']);
        add_action('admin_post_yombal_save_partner_product', [self::class, 'handle_save']);
        add_action('admin_post_yombal_delete_partner_product', [self::class, 'handle_delete']);
    }

    public static function render_products_page(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour gerer vos produits.</div></div>';
        }

        $user_id = get_current_user_id();
        if (! Profile_Service::is_partner_user($user_id)) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Ce compte n est pas reconnu comme partenaire Yombal.</div></div>';
        }

        $product_id = isset($_GET['edit_product']) ? (int) $_GET['edit_product'] : 0;
        $product = $product_id > 0 ? wc_get_product($product_id) : null;

        if ($product && ! self::can_manage_product($product_id, $user_id)) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous ne pouvez pas modifier ce produit.</div></div>';
        }

        $current_url = get_permalink() ?: home_url('/');
        $products = self::get_partner_products($user_id);

        ob_start();
        ?>
        <div class="yombal-ui yombal-partner-products yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Catalogue partenaire</span>
                <h1>Mes produits</h1>
                <p>Ajoutez, mettez a jour et organisez vos produits simplement pour mieux presenter votre offre aux clients.</p>
            </section>

            <section class="yombal-card">
                <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title">Catalogue courant</h2>
                        <div class="yombal-card__meta">Retrouvez ici vos produits publies, en attente ou en brouillon.</div>
                    </div>
                    <a href="<?php echo esc_url(add_query_arg('new_product', '1', $current_url)); ?>" class="yombal-button yombal-button--accent">Nouveau produit</a>
                </div>

                <?php if ($products) : ?>
                    <table class="shop_table shop_table_responsive">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Statut</th>
                                <th>Prix</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product_row) : ?>
                                <tr>
                                    <td><?php echo esc_html($product_row['title']); ?></td>
                                    <td><?php echo esc_html($product_row['status']); ?></td>
                                    <td><?php echo esc_html($product_row['price']); ?></td>
                                    <td>
                                        <div class="yombal-table-actions">
                                            <a href="<?php echo esc_url(add_query_arg('edit_product', (string) $product_row['id'], $current_url)); ?>">Modifier</a>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=yombal_delete_partner_product&product_id=' . (int) $product_row['id']), 'yombal_delete_partner_product_' . (int) $product_row['id'])); ?>">Supprimer</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="yombal-empty-state">Aucun produit associe a ce partenaire pour le moment.</div>
                <?php endif; ?>
            </section>

            <?php if ($product || isset($_GET['new_product'])) : ?>
                <?php echo self::render_form($product, $current_url); ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function handle_save(): void {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $user_id = get_current_user_id();
        if (! Profile_Service::is_partner_user($user_id)) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('yombal_save_partner_product');

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if ($product_id > 0 && ! self::can_manage_product($product_id, $user_id)) {
            wp_die('Produit non autorise.');
        }

        $post_data = [
            'post_title' => sanitize_text_field((string) ($_POST['post_title'] ?? '')),
            'post_content' => wp_kses_post((string) ($_POST['post_content'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($_POST['post_excerpt'] ?? '')),
            'post_status' => in_array($_POST['post_status'] ?? 'draft', ['draft', 'publish', 'pending'], true) ? (string) $_POST['post_status'] : 'draft',
            'post_type' => 'product',
            'post_author' => $user_id,
        ];

        if ($product_id > 0) {
            $post_data['ID'] = $product_id;
            $product_id = wp_update_post($post_data, true);
        } else {
            $product_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($product_id)) {
            wp_die($product_id->get_error_message());
        }

        wp_set_object_terms($product_id, 'simple', 'product_type');

        update_post_meta($product_id, '_regular_price', wc_format_decimal((string) ($_POST['regular_price'] ?? '0')));
        update_post_meta($product_id, '_price', wc_format_decimal((string) ($_POST['regular_price'] ?? '0')));
        update_post_meta($product_id, '_manage_stock', ! empty($_POST['manage_stock']) ? 'yes' : 'no');
        update_post_meta($product_id, '_stock', ! empty($_POST['stock_quantity']) ? (int) $_POST['stock_quantity'] : '');
        update_post_meta($product_id, '_stock_status', ! empty($_POST['stock_quantity']) && (int) $_POST['stock_quantity'] > 0 ? 'instock' : 'outofstock');

        foreach (self::META_FIELDS as $meta_key => $label) {
            $value = $_POST[$meta_key] ?? '';
            if (in_array($meta_key, ['delai_confection', 'largeur', 'delai_expedition'], true)) {
                update_post_meta($product_id, $meta_key, $value !== '' ? (int) $value : '');
            } else {
                update_post_meta($product_id, $meta_key, sanitize_text_field((string) $value));
            }
        }

        foreach (array_keys(self::EXTRA_TAXONOMIES) as $taxonomy) {
            if (! taxonomy_exists($taxonomy)) {
                continue;
            }
            $term_ids = array_map('intval', (array) ($_POST[$taxonomy] ?? []));
            wp_set_object_terms($product_id, $term_ids, $taxonomy, false);
        }

        $redirect_url = isset($_POST['_redirect_url']) ? esc_url_raw((string) $_POST['_redirect_url']) : home_url('/');
        $redirect_url = add_query_arg('edit_product', (string) $product_id, remove_query_arg(['new_product'], $redirect_url));
        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function handle_delete(): void {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
        $user_id = get_current_user_id();
        if ($product_id <= 0 || ! self::can_manage_product($product_id, $user_id)) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('yombal_delete_partner_product_' . $product_id);
        wp_trash_post($product_id);
        wp_safe_redirect(remove_query_arg(['edit_product'], wp_get_referer() ?: home_url('/')));
        exit;
    }

    private static function render_form(?\WC_Product $product, string $current_url): string {
        $product_id = $product ? $product->get_id() : 0;

        ob_start();
        ?>
        <section class="yombal-card yombal-card--soft">
            <div class="yombal-card__header">
                    <div class="yombal-stack">
                        <h2 class="yombal-section-title"><?php echo $product ? 'Modifier le produit' : 'Nouveau produit'; ?></h2>
                        <div class="yombal-card__meta">Remplissez les informations essentielles pour mettre votre produit en valeur.</div>
                    </div>
                </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="yombal-form">
                <?php wp_nonce_field('yombal_save_partner_product'); ?>
                <input type="hidden" name="action" value="yombal_save_partner_product">
                <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>">
                <input type="hidden" name="_redirect_url" value="<?php echo esc_attr($current_url); ?>">

                <p>
                    <label for="post_title">Nom du produit</label>
                    <input id="post_title" name="post_title" type="text" value="<?php echo esc_attr($product ? $product->get_name() : ''); ?>" required>
                </p>

                <p>
                    <label for="post_excerpt">Resume court</label>
                    <textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea($product ? $product->get_short_description() : ''); ?></textarea>
                </p>

                <p>
                    <label for="post_content">Description</label>
                    <textarea id="post_content" name="post_content" rows="6"><?php echo esc_textarea($product ? $product->get_description() : ''); ?></textarea>
                </p>

                <div class="yombal-field-grid">
                    <p>
                        <label for="regular_price">Prix</label>
                        <input id="regular_price" name="regular_price" type="number" min="0" step="0.01" value="<?php echo esc_attr($product ? (string) $product->get_regular_price() : ''); ?>">
                    </p>
                    <p>
                        <label for="stock_quantity">Stock</label>
                        <input id="stock_quantity" name="stock_quantity" type="number" min="0" step="1" value="<?php echo esc_attr($product ? (string) $product->get_stock_quantity() : ''); ?>">
                    </p>
                    <p>
                        <label for="post_status">Statut</label>
                        <select id="post_status" name="post_status">
                            <?php foreach (['draft' => 'Brouillon', 'publish' => 'Publie', 'pending' => 'En attente'] as $status => $label) : ?>
                                <option value="<?php echo esc_attr($status); ?>" <?php selected($product ? $product->get_status() : 'draft', $status); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="manage_stock">Gestion stock</label>
                        <span class="yombal-checkbox-row">
                            <input id="manage_stock" name="manage_stock" type="checkbox" value="1" <?php checked($product ? $product->get_manage_stock() : false); ?>>
                            <span>Activer le suivi de stock</span>
                        </span>
                    </p>
                </div>

                <h3 class="yombal-section-title">Champs Yombal</h3>
                <div class="yombal-field-grid">
                    <?php foreach (self::META_FIELDS as $meta_key => $label) : ?>
                        <?php $value = $product ? get_post_meta($product_id, $meta_key, true) : ''; ?>
                        <p>
                            <label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label>
                            <input id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" type="<?php echo in_array($meta_key, ['delai_confection', 'largeur', 'delai_expedition'], true) ? 'number' : 'text'; ?>" value="<?php echo esc_attr((string) $value); ?>">
                        </p>
                    <?php endforeach; ?>
                </div>

                <h3 class="yombal-section-title">Taxonomies Yombal</h3>
                <div class="yombal-field-grid">
                    <?php foreach (self::EXTRA_TAXONOMIES as $taxonomy => $label) : ?>
                        <?php if (! taxonomy_exists($taxonomy)) { continue; } ?>
                        <?php $selected = $product ? wp_get_object_terms($product_id, $taxonomy, ['fields' => 'ids']) : []; ?>
                        <?php $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]); ?>
                        <?php if (is_wp_error($terms)) { $terms = []; } ?>
                        <p>
                            <label for="<?php echo esc_attr($taxonomy); ?>"><?php echo esc_html($label); ?></label>
                            <select id="<?php echo esc_attr($taxonomy); ?>" name="<?php echo esc_attr($taxonomy); ?>[]" multiple size="5">
                                <?php foreach ((array) $terms as $term) : ?>
                                    <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected(in_array($term->term_id, (array) $selected, true)); ?>><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    <?php endforeach; ?>
                </div>

                <div class="yombal-form__actions">
                    <button type="submit" class="yombal-button yombal-button--accent">Enregistrer le produit</button>
                </div>
            </form>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function get_partner_products(int $user_id): array {
        $query = new \WP_Query([
            'post_type' => 'product',
            'author' => $user_id,
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $rows = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            $rows[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'status' => (string) $post->post_status,
                'price' => $product ? (string) wc_price((float) $product->get_regular_price()) : '-',
            ];
        }

        return $rows;
    }

    private static function can_manage_product(int $product_id, int $user_id): bool {
        $post = get_post($product_id);
        if (! $post || $post->post_type !== 'product') {
            return false;
        }

        return (int) $post->post_author === $user_id || current_user_can('edit_post', $product_id);
    }
}
