Tu reprends le développement de la plateforme « Prise de rendez-vous en ligne – Clinique Croix Bleue » (projet de mémoire). Les modules 1 à 8 sont terminés et commités.

**Avant d'écrire du code**, lis entièrement `CLAUDE.md`, `docs/CAHIER_DES_CHARGES.md` (sections 8.5, 12, 13 « Espace administrateur », 14, 15), `docs/DECISIONS.md`, puis `git log` et le code existant.

**Ta mission : réaliser le module 9 (Statistiques) puis la tâche planifiée du module 10.**

## Module 9 — Statistiques
- `StatistiqueService` et `GET /api/admin/statistiques` (administrateur uniquement), avec une période en paramètre (du / au, valeur par défaut raisonnable) :
  - nombre de rendez-vous par statut, par spécialité et par période (par mois ou par semaine : choisis le plus lisible et note-le dans `DECISIONS.md`) ;
  - nombre de préconsultations et de suggestions émises (préconsultations avec `specialite_suggeree_id` renseignée) ;
  - nombre d'indisponibilités.
- Requêtes agrégées (GROUP BY via Eloquent ou le query builder), sans N+1, sans aucune donnée nominative ni contenu de préconsultation dans la réponse.
- Écran « Statistiques » du tableau de bord administrateur : chiffres clés et tableaux ou graphiques simples, lisibles sur smartphone, libellés de statut en français. Si tu ajoutes une bibliothèque de graphiques, demande-moi d'abord ; sinon fais des barres en CSS.
- Tests : accès refusé aux autres rôles, exactitude des comptes sur un jeu de données fictif, filtrage par période.

## Module 10 (partie 1) — Tâche planifiée
- Commande `rendezvous:marquer-passes` : passe à `passe` les rendez-vous `confirme` dont `date_heure` est dépassée (et uniquement ceux-là : pas les `a_reprogrammer`). Planifiée **toutes les heures** dans `routes/console.php` (`Schedule::command(...)->hourly()`).
- Test **T12** : exécution de la commande → statut `passe` pour les rendez-vous dépassés, rendez-vous futurs et autres statuts inchangés (`Carbon::setTestNow()`).
- README : lancement du planificateur en développement (`php artisan schedule:work`) et en démonstration sous Windows (planificateur de tâches appelant `schedule:run` chaque minute).

## Fin de chaque module
Migrations + `DemoSeeder` + `artisan test` au vert, `npm run build`, vérification dans le navigateur, ajout dans `docs/DECISIONS.md`, ligne « État » du README, un commit par module (« Module 9 : … », « Module 10 : tâche planifiée »), compte rendu court. En cas d'ambiguïté, arrête-toi et pose-moi la question.
