# Règles pour agents IA

## Règles générales

- Travailler avec méthode : analyser → planifier → exécuter → vérifier → documenter.
- Ne jamais exposer d'identifiants, mots de passe, clés API ou tokens.
- Ne jamais modifier wp-config.php.
- Ne jamais envoyer de base de données complète dans GitHub.
- Ne jamais envoyer de fichiers de sauvegarde dans GitHub.
- Ne jamais supprimer un plugin ou un thème sans justification écrite.
- Documenter chaque changement dans CHANGELOG.md.

## Règles WordPress

- Ne pas toucher directement à la production pendant les phases de conception.
- Préférer un environnement local ou staging avant production.
- Ne pas utiliser Code Snippets comme solution principale.
- Préférer un thème enfant, un plugin personnalisé ou un mu-plugin propre.
- Tester après chaque modification importante.

## Règles Yombal.sn

- La plateforme doit rester orientée marketplace couture + tissus.
- Les paiements doivent rester sur le site.
- WhatsApp peut servir aux notifications, mais pas à contourner la commande.
- PayTech reste en mode TEST tant que le tunnel complet n'est pas validé.
- WCFM est considéré comme une dépendance actuelle mais non définitive.
- Préparer progressivement une architecture plus propre et maîtrisée.

## Convention de commits

```
type(scope): description courte

Types : feat, fix, docs, style, refactor, chore
Exemples :
  feat(design): add homepage spec
  fix(plugin): correct paytech webhook handler
  docs(agents): update IA rules
```

## Ordre de priorité pour les modifications

1. Documenter l'intention dans MISSION.md ou CHANGELOG.md.
2. Tester en local ou staging.
3. Valider avant push.
4. Ne jamais pusher sur main sans vérification.
