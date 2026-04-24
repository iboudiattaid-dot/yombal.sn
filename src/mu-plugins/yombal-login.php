<?php
/* Plugin Name: Yombal Login Redirect */
add_action('init', function() {
    if (!is_admin() && isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, 'wp-login.php') !== false && !isset($_POST['log'])) {
            $redirect = isset($_GET['redirect_to']) ? '?redirect_to=' . urlencode($_GET['redirect_to']) : '';
            wp_redirect(home_url('/connexion/' . $redirect));
            exit;
        }
    }
});
