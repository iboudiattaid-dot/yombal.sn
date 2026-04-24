<?php
// Yombal Tissu - CPT + ACF fields + conditional visibility by vendor type

// --- CPT vendeur_tissu ---
add_action('init', 'yombal_register_cpt_tissu');
function yombal_register_cpt_tissu() {
    register_post_type('vendeur_tissu', array(
        'label'    => 'Tissus vendeur',
        'public'   => true,
        'show_ui'  => true,
        'supports' => array('title','editor','thumbnail'),
    ));
}

// --- Vendor type helpers ---
function yombal_vendor_type($uid = 0) {
    if (!$uid) $uid = get_current_user_id();
    return (string) get_user_meta($uid, 'wcfm_vendor_tax', true);
}
function yombal_is_tissu_vendor($uid = 0) {
    $t = yombal_vendor_type($uid);
    return (strpos($t,'tissus') !== false || strpos($t,'hybride') !== false);
}
function yombal_is_tailleur_vendor($uid = 0) {
    $t = yombal_vendor_type($uid);
    return (strpos($t,'tailleur') !== false || strpos($t,'hybride') !== false);
}

// --- Register ACF field groups ---
add_action('acf/init', 'yombal_register_acf_product_fields');
function yombal_register_acf_product_fields() {
    if (!function_exists('acf_add_local_field_group')) return;

    // GROUP TISSU
    acf_add_local_field_group(array(
        'key'      => 'group_yombal_tissu_produit',
        'title'    => 'Infos tissu',
        'fields'   => array(
            array(
                'key'        => 'field_tissu_matiere',
                'label'      => 'Matiere',
                'name'       => 'tissu_matiere',
                'type'       => 'select',
                'required'   => 0,
                'allow_null' => 1,
                'multiple'   => 0,
                'choices'    => array(
                    'wax'           => 'Wax',
                    'wax_hollandais'=> 'Wax Hollandais',
                    'bazin'         => 'Bazin',
                    'bazin_riche'   => 'Bazin Riche',
                    'soie'          => 'Soie',
                    'organza'       => 'Organza',
                    'coton'         => 'Coton',
                    'lin'           => 'Lin',
                    'dentelle'      => 'Dentelle',
                    'velours'       => 'Velours',
                    'satin'         => 'Satin',
                    'mousseline'    => 'Mousseline',
                    'ankara'        => 'Ankara',
                    'kente'         => 'Kente',
                    'bogolan'       => 'Bogolan',
                    'pagne'         => 'Pagne',
                    'autre'         => 'Autre',
                ),
            ),
            array(
                'key'      => 'field_tissu_largeur',
                'label'    => 'Largeur (cm)',
                'name'     => 'tissu_largeur',
                'type'     => 'number',
                'required' => 0,
            ),
            array(
                'key'      => 'field_tissu_couleur',
                'label'    => 'Couleurs disponibles',
                'name'     => 'tissu_couleur',
                'type'     => 'text',
                'required' => 0,
            ),
            array(
                'key'      => 'field_tissu_prix_metre',
                'label'    => 'Prix au metre (FCFA)',
                'name'     => 'tissu_prix_metre',
                'type'     => 'number',
                'required' => 0,
            ),
        ),
        'location' => array(array(array('param'=>'post_type','operator'=>'==','value'=>'product'))),
        'active'   => true,
    ));

    // GROUP TAILLEUR
    acf_add_local_field_group(array(
        'key'    => 'group_produit_tailleur',
        'title'  => 'Infos couture',
        'fields' => array(
            array(
                'key'      => 'field_tailleur_specialite',
                'label'    => 'Specialite',
                'name'     => 'tailleur_specialite',
                'type'     => 'checkbox',
                'required' => 0,
                'choices'  => array(
                    'boubou'         => 'Boubou',
                    'robe'           => 'Robe',
                    'kaftan'         => 'Kaftan',
                    'costume'        => 'Costume',
                    'tenue_mariee'   => 'Tenue de mariage',
                    'jupe'           => 'Jupe',
                    'pantalon'       => 'Pantalon',
                    'enfant'         => 'Enfant',
                    'autre'          => 'Autre',
                ),
            ),
            array(
                'key'        => 'field_tailleur_delai',
                'label'      => 'Delai de realisation',
                'name'       => 'tailleur_delai',
                'type'       => 'select',
                'required'   => 0,
                'allow_null' => 1,
                'choices'    => array(
                    '3j'    => 'Moins de 3 jours',
                    '1sem'  => '1 semaine',
                    '2sem'  => '2 semaines',
                    '1mois' => '1 mois',
                    'plus'  => 'Plus d un mois',
                ),
            ),
            array(
                'key'         => 'field_tailleur_tissu_fourni',
                'label'       => 'Tissu fourni par le tailleur',
                'name'        => 'tailleur_tissu_fourni',
                'type'        => 'true_false',
                'required'    => 0,
                'ui'          => 1,
                'ui_on_text'  => 'Oui',
                'ui_off_text' => 'Non',
            ),
        ),
        'location' => array(array(array('param'=>'post_type','operator'=>'==','value'=>'product'))),
        'active'   => true,
    ));
}

// --- Conditional field visibility ---
// Tissu fields: hide if not tissu/hybride vendor
add_filter('acf/prepare_field/key=field_tissu_matiere',      'yombal_maybe_hide_tissu_field');
add_filter('acf/prepare_field/key=field_tissu_largeur',      'yombal_maybe_hide_tissu_field');
add_filter('acf/prepare_field/key=field_tissu_couleur',      'yombal_maybe_hide_tissu_field');
add_filter('acf/prepare_field/key=field_tissu_prix_metre',   'yombal_maybe_hide_tissu_field');
function yombal_maybe_hide_tissu_field($field) {
    if (current_user_can('manage_options')) return $field;
    if (!yombal_is_tissu_vendor()) return false;
    return $field;
}

// Tailleur fields: hide if not tailleur/hybride vendor
add_filter('acf/prepare_field/key=field_tailleur_specialite',    'yombal_maybe_hide_tailleur_field');
add_filter('acf/prepare_field/key=field_tailleur_delai',         'yombal_maybe_hide_tailleur_field');
add_filter('acf/prepare_field/key=field_tailleur_tissu_fourni',  'yombal_maybe_hide_tailleur_field');
function yombal_maybe_hide_tailleur_field($field) {
    if (current_user_can('manage_options')) return $field;
    if (!yombal_is_tailleur_vendor()) return false;
    return $field;
}

// --- Bypass required validation for hidden fields ---
add_filter('acf/validate_value/key=field_tissu_matiere',      'yombal_bypass_tissu_val', 10, 4);
add_filter('acf/validate_value/key=field_tissu_largeur',      'yombal_bypass_tissu_val', 10, 4);
add_filter('acf/validate_value/key=field_tissu_couleur',      'yombal_bypass_tissu_val', 10, 4);
add_filter('acf/validate_value/key=field_tissu_prix_metre',   'yombal_bypass_tissu_val', 10, 4);
function yombal_bypass_tissu_val($valid, $value, $field, $input) {
    if (!yombal_is_tissu_vendor()) return true;
    return $valid;
}

add_filter('acf/validate_value/key=field_tailleur_specialite',   'yombal_bypass_tailleur_val', 10, 4);
add_filter('acf/validate_value/key=field_tailleur_delai',        'yombal_bypass_tailleur_val', 10, 4);
add_filter('acf/validate_value/key=field_tailleur_tissu_fourni', 'yombal_bypass_tailleur_val', 10, 4);
function yombal_bypass_tailleur_val($valid, $value, $field, $input) {
    if (!yombal_is_tailleur_vendor()) return true;
    return $valid;
}
