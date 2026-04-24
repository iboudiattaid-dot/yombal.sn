# PayTech — Mode TEST

## Statut actuel

PayTech est intégré à WooCommerce via le plugin `paytech_woocommerce`.
Le paiement est actuellement en **mode TEST uniquement**.

## Ce que cela signifie

- Les paiements ne sont pas réels.
- Les transactions de test ne génèrent pas de vrai mouvement d'argent.
- Les webhooks de confirmation doivent être testés et validés.
- Le passage en production nécessite une validation complète du tunnel.

## Conditions pour passer en mode production

1. Le tunnel de commande complet doit être testé de bout en bout.
2. Les webhooks PayTech doivent être reçus et traités correctement.
3. Les statuts de commande WooCommerce doivent se mettre à jour automatiquement.
4. Les notifications (email, SMS ou WhatsApp) doivent fonctionner.
5. Un test de remboursement doit être effectué.
6. La validation d'un responsable Yombal est requise avant activation.

## Configuration

- Ne jamais stocker les clés API PayTech dans GitHub.
- Les clés doivent être dans wp-config.php (exclu de Git) ou dans des variables d'environnement.
- Le plugin se trouve dans : `wp-content/plugins/paytech_woocommerce/`

## Tests à documenter

| Scénario | Résultat attendu | Validé |
|----------|-----------------|--------|
| Paiement tissu seul | Commande créée, statut "En cours" | À tester |
| Paiement confection | Commande créée avec détail tailleur | À tester |
| Paiement échoué | Message d'erreur, commande en attente | À tester |
| Webhook reçu | Statut commande mis à jour | À tester |
| Remboursement | Statut remboursé, client notifié | À tester |

## Règle absolue

Ne jamais activer le mode production sans avoir complété et documenté tous les tests ci-dessus.
