Tu reprends le développement de la plateforme « Prise de rendez-vous en ligne – Clinique Croix Bleue » (projet de mémoire). Les modules 1 (socle) et 2 (gestion des utilisateurs) sont terminés et commités.

**Avant d'écrire du code**, lis entièrement :
- `CLAUDE.md` (conventions, chemin obligatoire du PHP 8.3, commandes de test) ;
- `docs/CAHIER_DES_CHARGES.md` (référence absolue, en particulier les sections 5, 7, 13 et 14) ;
- `docs/DECISIONS.md` (choix déjà faits) ;
- `git log` et le code existant (`backend/routes/api.php`, `app/Http`, `app/Models`, `frontend/src/App.tsx`, `components/Formulaire.tsx`, `pages/admin/Utilisateurs.tsx` comme modèle d'écran).

**Ta mission : réaliser les modules 3 et 4, l'un après l'autre.**

## Module 3 — Spécialités
- Gestion par l'administrateur : `GET, POST, PATCH /api/admin/specialites` (libellé unique, description facultative qui porte les jours de présence). Pas de suppression (non prévue par la section 14).
- Liste publique `GET /api/specialites` (existe déjà en version minimale) : à compléter si besoin.
- Écrans : page publique « Spécialités » avec les jours de présence ; écran admin « Gestion des spécialités ».
- Tests : droits d'accès (seul l'administrateur modifie), unicité du libellé, liste publique sans authentification.

## Module 4 — Plannings
- `POST /api/medecins/{id}/disponibilites` (secrétaire) : jour_semaine 1–7, heure_debut < heure_fin, duree_creneau > 0, médecin actif existant. **RG14 : aucune route PUT, PATCH ni DELETE** sur les disponibilités.
- `GET /api/medecins/{id}/planning` (secrétaire) et `GET /api/medecin/planning` (médecin connecté, le sien uniquement ; RG12 : il ne modifie rien). Le planning renvoie, pour une semaine donnée (paramètre de date), les disponibilités et les rendez-vous de la période ; les indisponibilités et rendez-vous n'existent pas encore côté écran mais la structure doit pouvoir les afficher plus tard (modules 5 et 7). Le médecin ne voit pas ici le contenu des préconsultations (module 6).
- Si les deux plages d'un même médecin et d'un même jour se chevauchent : **demande-moi** s'il faut les refuser avant de décider.
- Écrans : secrétaire « Planning des médecins » en vue semaine avec choix du médecin et formulaire « Ajout d'une disponibilité » ; médecin « Mon planning » (jour et semaine), en lecture seule.
- Tests : **T13** (tentative de modification ou de suppression d'une disponibilité → 404 ou 405), validation des disponibilités, accès (patient et médecin ne peuvent pas ajouter ; un médecin ne voit que son planning).

## Fin de chaque module
1. `migrate:fresh --seed` + `DemoSeeder` sur `croixbleue_local`, puis `artisan test` : tout doit passer.
2. `npm run build` sans erreur, et vérification des écrans dans le navigateur (serveurs de `.claude/launch.json`, comptes de démonstration).
3. Ajoute tes choix et écarts dans `docs/DECISIONS.md`, mets à jour la ligne « État » du `README.md`.
4. Un commit par module (message en français, « Module N : … »).
5. Donne-moi un compte rendu court : fait, reste, problèmes. Si une règle est ambiguë, arrête-toi et pose-moi la question au lieu de décider seul.
