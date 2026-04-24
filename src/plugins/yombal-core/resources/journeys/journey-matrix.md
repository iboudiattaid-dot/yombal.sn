# Yombal Journey Matrix

Source de verite lisible pour les parcours client, partenaire, litiges et systeme.

## Conventions

- `Cxx` : parcours client
- `Pxx` : parcours partenaire
- `Lxx` : parcours litiges
- `Sxx` : controles systeme / transverses

## Scenarios

| ID | Acteur | Entree | Resultat attendu | Validation |
| --- | --- | --- | --- | --- |
| S01 | anonymous | `/` | Homepage avec shell public complet | http+html |
| S02 | anonymous | `/catalogue-tailleurs/` | Catalogue couture public coherent | http+html |
| S03 | anonymous | `/catalogue-tissus/` | Catalogue tissus public coherent | http+html |
| S04 | anonymous | `/catalogue-modeles/` | Catalogue modeles public coherent | http+html |
| S05 | anonymous | `/devenir-partenaire-yombal/` | Formulaire partenaire public | http+html |
| S06 | anonymous | `/mentions-legales/` | Shell public editorial/legal coherent | http+html |
| C01 | client | `/espace-client-yombal/` | Overview client complet | auth+html |
| C02 | client | `/espace-client-yombal/?tab=measurements` | Profils de mesures reutilisables | auth+html |
| C03 | client | `/espace-client-yombal/?tab=messages` | Messages et conversations | auth+html |
| C04 | client | `/espace-client-yombal/?tab=notifications` | Notifications in-app visibles | auth+html |
| C05 | client | `/espace-client-yombal/?tab=events` | Evenement partageable par code | auth+html |
| C06 | client | `couture_requests.pending.customer_url` | Demande couture en attente | auth+html |
| C07 | client | `couture_requests.approved.customer_url` | Demande couture approuvee avec CTA checkout | auth+html |
| C08 | client | `couture_requests.needs_more_fabric.customer_url` | Demande couture avec tissu supplementaire | auth+html |
| C09 | client | `couture_requests.payment_completed.customer_url` | Confirmation post-paiement | auth+html |
| L01 | client | `/litiges-yombal/` | Creation de ticket client | auth+html |
| L02 | client | `tickets.waiting_partner.client_url` | Ticket en attente du partenaire | auth+html |
| L03 | client | `tickets.waiting_customer.client_url` | Ticket en attente du client | auth+html |
| L04 | client | `tickets.resolved.client_url` | Ticket resolu / reouverture | auth+html |
| P01 | pending_partner | `/espace-partenaire-yombal/` | Ecran attente de verification | auth+html |
| P02 | tailor | `/espace-partenaire-yombal/` | Dashboard tailleur complet | auth+html |
| P03 | tailor | `/espace-partenaire-yombal/?tab=products` | Gestion produits tailleur | auth+html |
| P04 | tailor | `/espace-partenaire-yombal/?tab=messages` | Messages partenaire | auth+html |
| P05 | tailor | `tickets.waiting_partner.partner_url` | Inbox litiges partenaire | auth+html |
| P06 | tailor | `/espace-partenaire-yombal/?tab=tailor-requests` | Demandes couture cote atelier | auth+html |
| P07 | fabric_vendor | `/espace-partenaire-yombal/` | Overview vendeur tissus sans onglet couture | auth+html |
| P08 | fabric_vendor | `/espace-partenaire-yombal/?tab=products` | Gestion produits tissus | auth+html |
| S07 | admin | `/wp-admin/admin-post.php?action=yombal_export_journey_report` | Export JSON Journey Lab | auth+json |

## Fixtures attendues

- `client` : compte client test
- `tailor` : partenaire tailleur approuve
- `fabric_vendor` : partenaire vendeur tissus approuve
- `pending_partner` : partenaire en attente de revue
- produits publics et brouillons pour tailleur et vendeur tissus
- profil de mesures client
- evenement avec code `YTESTEVT`
- thread de messages de test
- tickets litiges `waiting_partner`, `waiting_customer`, `resolved`
- demandes couture `pending`, `approved`, `needs_more_fabric`, `payment_completed`

## Regles metier verrouillees

- aucun nouveau slug public
- `pending_review` et `rejected` ne remontent pas dans le front public
- le partenaire traite les tickets recus mais ne cree pas de litige client generique
- les vrais medias WordPress sont utilises prioritairement; les fallbacks ne servent qu en dernier recours
