<?php
// Yombal Differenciation - vendor registration form UX + JS toggles

// --- Pre-select vendor type from URL ?type= param ---
add_action('wp_footer', 'yombal_register_type_preselect');
function yombal_register_type_preselect() {
    if (!is_page('vendor-register') && !is_page('devenir-partenaire')) return;
    ?>
    <script>
    (function(){
        var urlParams = new URLSearchParams(window.location.search);
        var t = urlParams.get('type');
        if (!t) return;
        // Map URL param to select value
        var map = {
            'tailleur': 'tailleur:Tailleur (Couture sur mesure)',
            'tissus':   'tissus:Vendeur de tissus',
            'hybride':  'hybride:Tailleur + Vendeur de tissus'
        };
        var val = map[t] || t;
        function tryPreselect() {
            var sel = document.querySelector('select[name="wcfmvm_custom_infos[vous-etes]"]');
            if (!sel) { setTimeout(tryPreselect, 300); return; }
            for (var i=0; i<sel.options.length; i++) {
                if (sel.options[i].value === val || sel.options[i].value.indexOf(t) !== -1) {
                    sel.selectedIndex = i;
                    sel.dispatchEvent(new Event('change', {bubbles:true}));
                    break;
                }
            }
        }
        document.addEventListener('DOMContentLoaded', tryPreselect);
        if (document.readyState !== 'loading') tryPreselect();
    })();
    </script>
    <?php
}

// --- JS toggle: show/hide specialty fields based on vendor type selection ---
add_action('wp_footer', 'yombal_register_toggle_js');
function yombal_register_toggle_js() {
    if (!is_page('vendor-register')) return;
    ?>
    <style>
    /* Registration form improvements */
    .wcfmvm-reg-field-wrapper { margin-bottom: 18px !important; }
    #wcfmvm-registration-form .button,
    #wcfmvm-registration-form input[type="submit"] {
        background: #04273e !important;
        color: #fff !important;
        border: none !important;
        padding: 12px 32px !important;
        font-size: 1em !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        font-weight: 600 !important;
        width: 100% !important;
        margin-top: 8px !important;
    }
    #wcfmvm-registration-form .button:hover,
    #wcfmvm-registration-form input[type="submit"]:hover {
        background: #063556 !important;
    }
    #wcfmvm-registration-form h2 {
        color: #04273e !important;
        font-size: 1.3em !important;
        margin-bottom: 20px !important;
    }
    #wcfmvm-registration-form input[type="text"],
    #wcfmvm-registration-form input[type="email"],
    #wcfmvm-registration-form input[type="password"],
    #wcfmvm-registration-form input[type="tel"],
    #wcfmvm-registration-form select,
    #wcfmvm-registration-form textarea {
        border: 1px solid #ddd !important;
        border-radius: 6px !important;
        padding: 10px 14px !important;
        font-size: .95em !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    #wcfmvm-registration-form input:focus,
    #wcfmvm-registration-form select:focus {
        border-color: #04273e !important;
        outline: none !important;
        box-shadow: 0 0 0 2px rgba(4,39,62,.15) !important;
    }
    /* Toggle sections */
    .y-toggle-section {
        background: #f8f9fa;
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        padding: 16px;
        margin-top: 12px;
        display: none;
    }
    .y-toggle-section.visible { display: block; }
    .y-toggle-section h4 { margin: 0 0 12px; color: #04273e; font-size: .95em; }
    .y-toggle-section label { display: inline-flex; align-items: center; gap: 6px;
                               margin-right: 12px; margin-bottom: 8px; font-size: .9em; cursor: pointer; }
    </style>
    <script>
    (function(){
        function initToggle() {
            var sel = document.querySelector('select[name="wcfmvm_custom_infos[vous-etes]"]');
            if (!sel) { setTimeout(initToggle, 400); return; }

            // Build toggle sections after the select
            var wrap = sel.closest('.wcfmvm-reg-field-wrapper') || sel.parentNode;

            // Create tailleur specialites section
            if (!document.getElementById('y-sect-tailleur')) {
                var sects = {
                    tailleur: {
                        id: 'y-sect-tailleur',
                        title: 'Vos specialites',
                        items: ['Boubou','Robe','Kaftan','Costume','Tenue de mariage','Jupe','Pantalon','Enfants','Autre'],
                        name: 'wcfmvm_custom_infos[specialites][]'
                    },
                    tissus: {
                        id: 'y-sect-tissus',
                        title: 'Vos types de tissus',
                        items: ['Wax','Wax Hollandais','Bazin','Bazin Riche','Soie','Organza','Coton','Lin','Dentelle','Velours','Satin','Ankara','Kente','Bogolan','Pagne','Autre'],
                        name: 'wcfmvm_custom_infos[matieres][]'
                    }
                };
                Object.keys(sects).forEach(function(key) {
                    var s = sects[key];
                    var div = document.createElement('div');
                    div.className = 'y-toggle-section';
                    div.id = s.id;
                    var html = '<h4>' + s.title + '</h4>';
                    s.items.forEach(function(item) {
                        html += '<label><input type="checkbox" name="' + s.name + '" value="' + item.toLowerCase().replace(/ /g,'_') + '"> ' + item + '</label>';
                    });
                    div.innerHTML = html;
                    wrap.parentNode.insertBefore(div, wrap.nextSibling);
                });
            }

            function updateSections() {
                var v = sel.value || '';
                var isTail = v.indexOf('tailleur') !== -1;
                var isTiss = v.indexOf('tissus') !== -1;
                var isHybr = v.indexOf('hybride') !== -1;
                var st = document.getElementById('y-sect-tailleur');
                var ss = document.getElementById('y-sect-tissus');
                if (st) st.className = 'y-toggle-section' + ((isTail || isHybr) ? ' visible' : '');
                if (ss) ss.className = 'y-toggle-section' + ((isTiss || isHybr) ? ' visible' : '');
            }

            sel.addEventListener('change', updateSections);
            updateSections();

            // Rename submit button
            var btn = document.querySelector('#wcfmvm-registration-form input[type="submit"], #wcfmvm-registration-form button[type="submit"]');
            if (btn) btn.value = btn.value || 'Creer mon compte';
        }
        document.addEventListener('DOMContentLoaded', initToggle);
        if (document.readyState !== 'loading') initToggle();
    })();
    </script>
    <?php
}

// --- Save toggle field values to user meta on vendor registration ---
add_action('wcfm_vendor_registration_complete', 'yombal_save_registration_extras', 10, 2);
function yombal_save_registration_extras($vendor_id, $data) {
    if (isset($_POST['wcfmvm_custom_infos']['specialites'])) {
        update_user_meta($vendor_id, 'yombal_specialites', array_map('sanitize_text_field', (array)$_POST['wcfmvm_custom_infos']['specialites']));
    }
    if (isset($_POST['wcfmvm_custom_infos']['matieres'])) {
        update_user_meta($vendor_id, 'yombal_matieres', array_map('sanitize_text_field', (array)$_POST['wcfmvm_custom_infos']['matieres']));
    }
    // Save vendor type from vous-etes select
    if (isset($_POST['wcfmvm_custom_infos']['vous-etes'])) {
        $raw = sanitize_text_field($_POST['wcfmvm_custom_infos']['vous-etes']);
        $type = explode(':', $raw)[0];
        update_user_meta($vendor_id, 'wcfm_vendor_tax', $type);
    }
}
