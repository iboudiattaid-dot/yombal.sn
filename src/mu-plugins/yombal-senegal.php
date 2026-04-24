<?php
/* Plugin Name: Yombal Senegal Forms */

// Supprimer le champ code postal
add_filter('woocommerce_checkout_fields', function($fields) {
    unset($fields['billing']['billing_postcode']);
    unset($fields['shipping']['shipping_postcode']);
    return $fields;
});

add_filter('woocommerce_billing_fields', function($fields) {
    unset($fields['billing_postcode']);
    return $fields;
});

// Remplacer le champ etat/region par les 14 regions du Senegal
add_filter('woocommerce_states', function($states) {
    $states['SN'] = [
        'DK' => 'Dakar',
        'TH' => 'Thies',
        'SL' => 'Saint-Louis',
        'ZG' => 'Ziguinchor',
        'DL' => 'Diourbel',
        'FK' => 'Fatick',
        'KA' => 'Kaolack',
        'KE' => 'Kedougou',
        'KL' => 'Kolda',
        'LG' => 'Louga',
        'MT' => 'Matam',
        'SK' => 'Sedhiou',
        'TC' => 'Tambacounda',
        'KD' => 'Kaffrine',
    ];
    return $states;
});

// Rendre WhatsApp obligatoire dans les champs
add_filter('wcfm_registration_fields', function($fields) {
    if (isset($fields['phone'])) {
        $fields['phone']['label'] = 'Telephone / WhatsApp';
        $fields['phone']['required'] = true;
        $fields['phone']['placeholder'] = 'Ex: 77 123 45 67';
    }
    return $fields;
});

// Forcer la devise CFA
add_filter('woocommerce_currency', function() {
    return 'XOF';
});
