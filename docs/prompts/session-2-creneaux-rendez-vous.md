Tu reprends le développement de la plateforme « Prise de rendez-vous en ligne – Clinique Croix Bleue » (projet de mémoire). Les modules 1 à 4 sont terminés et commités.

**Avant d'écrire du code**, lis entièrement `CLAUDE.md`, `docs/CAHIER_DES_CHARGES.md` (sections 7, 8.1, 8.2, 10, 13, 14, 15), `docs/DECISIONS.md`, puis `git log` et le code existant (modèles, `config/rendezvous.php`, planning du module 4).

**Ta mission : réaliser le module 5 — Créneaux et rendez-vous.**

## Backend
- `CreneauService` conforme à la section 8.1 : découpage des plages `[heure_debut, heure_fin[`, créneau dépassant `heure_fin` ignoré, retrait des créneaux passés, chevauchant une indisponibilité, ou portant un rendez-vous `confirme`/`a_reprogrammer` ; tri et regroupement par jour ; horizon `config('rendezvous.horizon_jours')`. Méthode `prochainsCreneaux(specialite, n = 3)`. Les médecins désactivés ne proposent aucun créneau (voir `DECISIONS.md`). Évite les requêtes par créneau (charger indisponibilités et rendez-vous de la période en une fois).
- `GET /api/specialites/{id}/creneaux?du=&au=` et `GET /api/medecins/{id}/creneaux?du=&au=` (publics), avec validation et bornes de période raisonnables.
- `RendezVousService::reserver` (section 8.2) : transaction, revérification du créneau, statut `confirme` (RG06), violation de l'index unique → **409** avec message clair (RG07). Prévois dès maintenant le paramètre optionnel de préconsultation (rempli au module 6) et un point d'appel **après** validation de la transaction pour le courriel (rempli au module 8, laisser un commentaire `// Module 8`).
- `POST /api/rendez-vous`, `GET /api/mes-rendez-vous` (patient), `PATCH /api/rendez-vous/{id}` (changement de créneau, même médecin ou autre médecin de la même spécialité, même logique transactionnelle) et `POST /api/rendez-vous/{id}/annulation`, ouverts au patient (le sien, avant le délai `delai_modification_heures`, RG08) et à la secrétaire. `GET /api/secretariat/rendez-vous` avec filtres médecin, date, statut.
- `RendezVousPolicy` pour RG08 (et RG10 côté rendez-vous). Un rendez-vous `annule` ou `passe` ne se modifie plus.
- Le planning du module 4 doit maintenant afficher les rendez-vous.

## Frontend
- Prise de rendez-vous : implémente l'assistant **en 5 étapes** de la section 13, mais pour ce module les étapes 2 (préconsultation) et 3 (suggestion) peuvent rester des emplacements que le module 6 remplira ; le bouton « Passer cette étape » doit déjà fonctionner (RG02).
- « Mes rendez-vous » : statut (libellés français), modification et annulation si le délai le permet, sinon message invitant à contacter la clinique. Message clair en cas de 409.
- Tableau de bord patient ; liste secrétaire avec filtres (médecin, date, statut), modification et annulation.

## Tests obligatoires
- **T03** créneaux d'un médecin avec indisponibilité et rendez-vous réservés → absents.
- **T04** deux réservations du même créneau → une seule enregistrée, la seconde 409.
- **T09** annulation (et modification) après le délai → refus.
- **T10** (partie rendez-vous) : accès au rendez-vous d'un autre patient → 403.
- Plus : créneau hors disponibilité ou passé refusé, découpage qui ignore le dernier créneau incomplet, secrétaire autorisée sans délai, fuseau `Africa/Dakar`. Utilise `Carbon::setTestNow()` pour des tests déterministes.

## Fin du module
Migrations + `DemoSeeder` + `artisan test` au vert, `npm run build`, vérification du parcours dans le navigateur, ajout dans `docs/DECISIONS.md`, ligne « État » du README, commit « Module 5 : … », compte rendu court. Si une règle est ambiguë (par exemple qui, de la secrétaire ou du patient, peut déplacer un rendez-vous vers un autre médecin), arrête-toi et pose-moi la question.
