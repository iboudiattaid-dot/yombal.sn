<?php
/* Plugin Name: Yombal Checkout JS */
add_action('wp_footer', function() {
    if (!is_checkout()) return;
    echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    var labels = {
        "billing_first_name": "Prenom",
        "billing_last_name": "Nom",
        "billing_phone": "Telephone",
        "billing_email": "Email",
        "billing_address_1": "Adresse",
        "billing_city": "Ville",
        "billing_state": "Region",
        "order_comments": "Notes sur la commande"
    };
    Object.keys(labels).forEach(function(id) {
        var el = document.querySelector("label[for=" + id + "]");
        if (el) el.childNodes[0].textContent = labels[id] + " ";
    });
});
</script>';
}, 99);
