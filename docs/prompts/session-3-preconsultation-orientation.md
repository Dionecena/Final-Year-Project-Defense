Tu reprends le développement de la plateforme « Prise de rendez-vous en ligne – Clinique Croix Bleue » (projet de mémoire). Les modules 1 à 5 sont terminés et commités.

**Avant d'écrire du code**, lis entièrement `CLAUDE.md`, `docs/CAHIER_DES_CHARGES.md` (RG02, RG03, RG04, RG10, RG11, sections 8.2, 8.3, 10, 13, 15), `docs/DECISIONS.md`, puis `git log` et le code existant (`RendezVousService`, assistant de prise de rendez-vous, `config/preconsultation.php`, `config/orientation.php`).

**Ta mission : réaliser le module 6 — Préconsultation et orientation.**

## Backend
- Préconsultation **facultative** (RG02) envoyée avec la réservation (`POST /api/rendez-vous`) : `motif` et `symptomes` obligatoires si la préconsultation est fournie ; longueur max des symptômes 500 caractères pour les spécialités de `config('preconsultation.saisie_restreinte')` (Psychiatrie), 2 000 sinon (RG11). Collecte minimale : rien d'autre que motif et symptômes.
- `OrientationService` (section 8.3) : normalisation (minuscules, accents et ponctuation retirés), correspondance par termes de `config/orientation.php` ; **aucune suggestion** si la spécialité choisie correspond ou si aucune règle ne s'applique ; sinon la spécialité ayant le plus de correspondances, avec `prochainsCreneaux(specialite, 3)`. Ne rajoute **aucune règle médicale** : garde les termes d'exemple et le commentaire de validation par un médecin. En cas d'égalité entre deux spécialités, **demande-moi** la règle de départage avant de décider.
- `POST /api/orientation/verification` (patient). Si le patient conserve sa spécialité, la préconsultation est enregistrée avec `specialite_suggeree_id` renseignée.
- `GET /api/rendez-vous/{id}/preconsultation` : lisible uniquement par le médecin du rendez-vous et le patient concerné (RG10), via `PreconsultationPolicy`. La secrétaire voit seulement **si** une préconsultation existe (booléen dans ses listes) ; l'administrateur n'y a pas accès.
- Le courriel (module 8) ne contiendra aucune donnée de préconsultation : n'expose pas ces champs dans les ressources de rendez-vous.

## Frontend
- Étape 2 : formulaire facultatif (motif, symptômes avec compteur de caractères et limite selon la spécialité ; champ libre et court en psychiatrie, sans questionnaire), bouton « Passer cette étape », mention RG03 « Ces informations ne constituent pas un diagnostic et ne remplacent pas une consultation médicale » (`MENTION_NON_DIAGNOSTIC`).
- Étape 3 : n'apparaît qu'en cas de suggestion (RG04), avec la mention RG03, les 3 prochains créneaux de la spécialité suggérée, et les boutons « Conserver ma spécialité » et « Choisir la spécialité suggérée ».
- Espace médecin : détail d'un rendez-vous avec la préconsultation si elle existe.

## Tests obligatoires
- **T05** préconsultation sans motif ou sans symptômes → erreur de validation.
- **T06** motif correspondant à une autre spécialité → suggestion avec créneaux ; réservation possible dans la spécialité initiale (avec `specialite_suggeree_id`).
- **T07** motif compatible → aucune suggestion.
- **T08** psychiatrie > 500 caractères → erreur de validation.
- **T10** (partie préconsultation) : médecin d'un autre rendez-vous, secrétaire et administrateur → 403.
- Plus : réservation sans préconsultation toujours possible (RG02), normalisation des accents.

## Fin du module
Migrations + `DemoSeeder` + `artisan test` au vert, `npm run build`, vérification du parcours complet dans le navigateur, ajout dans `docs/DECISIONS.md`, ligne « État » du README, commit « Module 6 : … », compte rendu court. En cas d'ambiguïté, arrête-toi et pose-moi la question.
