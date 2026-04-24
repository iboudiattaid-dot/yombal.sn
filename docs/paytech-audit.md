# Audit PayTech — Yombal.sn

Version plugin : 6.0.3
Date audit : 2026-04-24

---

## Flux de paiement documenté

```
Client clique "Payer"
    │
    ▼
process_payment() — WooCommerce
    │
    ▼
POST → https://paytech.sn/api/payment/request-payment
  headers : API_KEY + API_SECRET
  body    : item_name, item_price, currency (XOF), ref_command,
            success_url, cancel_url, ipn_url, custom_field (JSON)
    │
    ▼
PayTech retourne un token
    │
    ▼
Redirection client → https://paytech.sn/payment/checkout/{token}
    │
    ▼
Client paie (Wave, Orange Money, Carte Visa, etc.)
    │
    ├── Annulation → cancel_url → ?_cancel=1 → statut "cancelled"
    └── Succès → success_url → ?_success=1 → redirection "merci"
                                │
                                ▼ (en parallèle)
                           IPN → /paytech/v6.0.3/ipn
                           PayTech envoie type_event=sale_complete
                           Vérification sha256(api_key) + sha256(secret_key)
                           Mise à jour statut commande WooCommerce
```

---

## Problèmes critiques identifiés

### 🔴 CRITIQUE — SSL désactivé

```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
```

**Risque** : vulnérable aux attaques man-in-the-middle. Les communications avec PayTech ne sont pas vérifiées.
**Correction** : activer la vérification SSL avec le bon bundle de certificats.

---

### 🔴 CRITIQUE — Confirmation de succès sans vérification paiement

La redirection `?_success=1` affiche la page "Merci" **sans vérifier que le paiement IPN a bien été reçu et validé**.

```php
} else if (isset($_GET['_success']) && $_GET['_success'] === '1') {
    $woocommerce->cart->empty_cart();
    // ← aucune vérification du statut réel de la commande
    wp_redirect($rdi_url);
```

**Risque** : un utilisateur peut manipuler l'URL pour voir la page de confirmation sans payer.
**Correction** : vérifier le statut de la commande via `$order->get_status()` avant d'afficher la confirmation.

---

### 🔴 CRITIQUE — Emails de notification cassés

```php
if (
    !@empty(@WC()->mailer()->emails) &&       // ← logique inversée
    !@(@WC()->mailer()->emails) &&            // ← annule le test précédent
    ...
```

La condition est inversée : les emails ne s'envoient probablement jamais.
**Correction** : supprimer `!@(@WC()->mailer()->emails)` ou refaire la logique proprement.

---

### 🟠 IMPORTANT — Typo dans la clé de session

```php
WC()->session->set('paytech_wc_oder_id', $order_id);   // ← 'oder' au lieu de 'order'
WC()->session->get('paytech_wc_oder_id');               // cohérent mais trompeur
```

Pas fonctionnellement bloquant (les deux correspondent), mais source de confusion pour la maintenance.
**Correction** : renommer en `paytech_wc_order_id` dans les deux endroits.

---

### 🟠 IMPORTANT — Propriétés WooCommerce dépréciées

```php
$order->id          // → utiliser $order->get_id()
$order->order_total // → utiliser $order->get_total()
```

Ces accès directs sont dépréciés depuis WooCommerce 3.x et génèrent des notices en mode debug.

---

### 🟡 MINEUR — `WOOCOMMERCE_VERSION` non fiable

La constante `WOOCOMMERCE_VERSION` a été supprimée dans les versions récentes de WooCommerce.
Utiliser `WC()->version` à la place.

---

### 🟡 MINEUR — Suppression d'erreurs avec `@`

Usage extensif de `@` pour supprimer les erreurs PHP, notamment dans `get_paytech_args()` et `pay_tech_ipn_confirm()`. Masque les erreurs réelles en production.

---

## Configuration actuelle

| Paramètre | Valeur configurée |
|-----------|------------------|
| Environnement | Test (à confirmer dans WP admin) |
| URL IPN | `{site_url}/paytech/v6.0.3/ipn` |
| URL succès | `{checkout_url}?_success=1` |
| URL annulation | `{checkout_url}?_cancel=1` |
| Méthode ouverture | Popup (configurable) |
| Statut après paiement | Configurable (processing ou completed) |

---

## Tests à valider avant passage en production

| Test | Description | Résultat |
|------|-------------|---------|
| Paiement test réussi | Utiliser une carte test PayTech | À faire |
| Réception IPN | Vérifier que /paytech/v6.0.3/ipn reçoit le POST | À faire |
| Vérification signature IPN | sha256(api_key) vérifié correctement | À faire |
| Statut commande mis à jour | WooCommerce passe de "pending" à "processing" ou "completed" | À faire |
| Email admin notifié | Email "Nouvelle commande" envoyé | À faire (bugué actuellement) |
| Email client notifié | Email "Commande en cours" envoyé | À faire (bugué actuellement) |
| Annulation paiement | Statut commande → "cancelled" | À faire |
| Manipulation URL succès | Accès direct à ?_success=1 bloqué | À faire |
| SSL activé | CURLOPT_SSL_VERIFYPEER = 1 | NON — à corriger |

---

## Corrections prioritaires avant production

1. **Activer SSL** dans `post()` — `CURLOPT_SSL_VERIFYPEER = 1`
2. **Corriger la logique email** dans `pay_tech_ipn_confirm()`
3. **Vérifier le statut commande** avant de valider `?_success=1`
4. **Remplacer les propriétés dépréciées** (`$order->id` → `$order->get_id()`)
5. **Tester l'IPN** en local via ngrok ou staging accessible depuis Internet

---

## Commande de test IPN (simulation locale)

```bash
# Simuler un IPN PayTech sur l'environnement local
curl -X POST http://localhost:8080/paytech/v6.0.3/ipn \
  -d "type_event=sale_complete" \
  -d "api_key_sha256=$(echo -n 'VOTRE_API_KEY' | sha256sum | cut -d' ' -f1)" \
  -d "api_secret_sha256=$(echo -n 'VOTRE_SECRET_KEY' | sha256sum | cut -d' ' -f1)" \
  -d 'custom_field={"order_id":1,"hash":"xxx","order_number":"1"}' \
  -d "payment_method=Orange Money"
```

> ⚠️ Remplacer `VOTRE_API_KEY` et `VOTRE_SECRET_KEY` par les vraies clés de test (jamais committer).
