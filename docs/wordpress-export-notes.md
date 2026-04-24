# Notes sur les exports WordPress

## Objectif

Documenter ce qui doit être exporté du site WordPress actuel pour alimenter le dépôt GitHub sans données sensibles.

## Ce qui DOIT être exporté

| Élément | Outil recommandé | Dossier cible |
|---------|-----------------|---------------|
| Thème enfant (PHP, CSS, JS) | FTP / WP File Manager | `src/theme/` |
| Plugins personnalisés | FTP / WP File Manager | `src/plugins/` |
| MU-plugins | FTP / WP File Manager | `src/mu-plugins/` |
| Templates Elementor | Elementor > Templates > Exporter | `exports/elementor/` |
| Formulaires Forminator | Forminator > Export | `exports/forminator/` |
| Paramètres WooCommerce | WooCommerce > Outils > Export | `exports/woocommerce/` |
| Export contenu WordPress | Outils > Exporter (XML) | `exports/wordpress/` |

## Ce qui NE DOIT PAS être exporté

- `wp-config.php` — contient les identifiants BDD
- La base de données complète (`.sql`)
- Le dossier `wp-content/uploads/` (médias)
- Les fichiers de cache
- Les sauvegardes Updraftplus ou All-in-One WP Migration
- Les clés API ou tokens dans les options WordPress

## Processus recommandé

1. Faire une sauvegarde complète avant tout export.
2. Exporter uniquement les éléments listés ci-dessus.
3. Vérifier que les exports ne contiennent pas de données sensibles.
4. Placer dans les bons dossiers du dépôt.
5. Committer avec un message descriptif dans CHANGELOG.md.

## Priorité des exports

1. **Thème enfant** — priorité haute (code CSS/PHP personnalisé)
2. **Plugins personnalisés** — priorité haute
3. **Templates Elementor** — priorité moyenne
4. **Formulaires Forminator** — priorité moyenne
5. **Export XML WordPress** — priorité basse (structure pages)

## État actuel

| Export | Statut |
|--------|--------|
| Thème enfant | À faire |
| Plugins personnalisés | À faire |
| Templates Elementor | À faire |
| Forminator | À faire |
| WooCommerce settings | À faire |
| Export XML | À faire |
