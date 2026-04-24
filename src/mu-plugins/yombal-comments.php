<?php
/**
 * Plugin Name: Yombal Comments Control
 * Description: Desactive les commentaires WordPress globalement sauf acheteurs verifies
 */

// Desactiver completement les commentaires WordPress sur toutes les pages/articles
add_action('init', function() {
    // Fermer les commentaires sur tous les posts existants
    add_filter('comments_open', '__return_false', 20, 2);
    add_filter('pings_open', '__return_false', 20, 2);
    // Masquer les commentaires existants
    add_filter('comments_array', '__return_empty_array', 10, 2);
});

// Supprimer les menus commentaires de l'admin
add_action('admin_menu', function() {
    remove_menu_page('edit-comments.php');
});

// Supprimer la bulle de commentaires dans la toolbar
add_action('wp_before_admin_bar_render', function() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
});

// Desactiver le support commentaires sur tous les types de contenus
add_action('init', function() {
    $types = get_post_types();
    foreach ($types as $type) {
        if (post_type_supports($type, 'comments')) {
            remove_post_type_support($type, 'comments');
            remove_post_type_support($type, 'trackbacks');
        }
    }
}, 100);

// Sur les produits WooCommerce : verifier l'achat avant d'autoriser l'avis
add_filter('woocommerce_review_rating_verification_required', '__return_true');