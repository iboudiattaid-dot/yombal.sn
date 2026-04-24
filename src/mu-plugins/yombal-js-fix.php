<?php
/* Plugin Name: Yombal JS Fix */
if (function_exists('get_option') && in_array('yombal-core/yombal-core.php', (array) get_option('active_plugins', []), true)) {
    return;
}

add_action('wp_footer', function() {
    echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Fix T11 - Bienvenue message WCFM
    var t11 = document.querySelector(".wcfm_dashboard_title, #wcfm_dashboard_title");
    if (t11 && t11.textContent.includes("Welcome")) {
        t11.textContent = t11.textContent.replace("Welcome to your", "Bienvenue sur votre espace Yombal");
    }
    // Fix menu items WCFM
    document.querySelectorAll(".wcfm_menu_item span").forEach(function(el) {
        el.style.display = "block";
    });
});
</script>';
}, 99);
