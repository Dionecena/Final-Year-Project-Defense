# Prompts des sessions de développement

Six sessions, **à lancer l'une après l'autre, dans l'ordre** : chaque module s'appuie sur le
précédent et elles modifient les mêmes fichiers (`routes/api.php`, `App.tsx`…). Ouvrir chaque
session dans le dossier `plateformecroixbleue/`, puis coller le contenu du fichier
correspondant. Avant de passer à la suivante, vérifier que la session a commité son module.

| Ordre | Fichier | Modules du cahier des charges | Tests visés |
|---|---|---|---|
| 1 | `session-1-specialites-plannings.md` | 3. Spécialités · 4. Plannings | T13 |
| 2 | `session-2-creneaux-rendez-vous.md` | 5. Créneaux et rendez-vous | T03, T04, T09, T10 (partie RDV) |
| 3 | `session-3-preconsultation-orientation.md` | 6. Préconsultation et orientation | T05 à T08, T10 (partie préconsultation) |
| 4 | `session-4-indisponibilites-notifications.md` | 7. Indisponibilités · 8. Notifications | T11 |
| 5 | `session-5-statistiques-tache-planifiee.md` | 9. Statistiques · 10. Tâche planifiée | T12 |
| 6 | `session-6-finitions-mise-a-disposition.md` | 10. Finitions, accueil, README, Supabase, ngrok, `RAPPORT.md` | T01 à T13 (relance) |

Chaque session lit automatiquement `CLAUDE.md` (conventions, chemin du PHP 8.3) et doit lire
`docs/CAHIER_DES_CHARGES.md` et `docs/DECISIONS.md`. Elle complète `docs/DECISIONS.md` à la fin
de son module : c'est ce journal qui permet à la session 6 de rédiger `RAPPORT.md`.
