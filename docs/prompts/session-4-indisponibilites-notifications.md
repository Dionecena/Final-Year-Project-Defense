Tu reprends le développement de la plateforme « Prise de rendez-vous en ligne – Clinique Croix Bleue » (projet de mémoire). Les modules 1 à 6 sont terminés et commités.

**Avant d'écrire du code**, lis entièrement `CLAUDE.md`, `docs/CAHIER_DES_CHARGES.md` (RG09, sections 8.4, 9, 13, 14, 15), `docs/DECISIONS.md`, puis `git log` et le code existant (`RendezVousService`, `CreneauService`, commentaires `// Module 8`, écrans secrétaire, `config/clinique.php`).

**Ta mission : réaliser le module 7 (Indisponibilités) puis le module 8 (Notifications).**

## Module 7 — Indisponibilités
- `POST /api/medecins/{id}/indisponibilites` (secrétaire) → `IndisponibiliteService::enregistrer` (section 8.4) : transaction, `date_fin >= date_debut`, `enregistree_par` = secrétaire connectée, rendez-vous `confirme` du médecin dans la période → `a_reprogrammer`. Réponse : la liste des rendez-vous concernés (vide si aucun).
- Traitement par la secrétaire de chaque rendez-vous `a_reprogrammer` : **reprogrammer** (nouveau créneau libre, retour à `confirme`) ou **annuler** (`annule`). Réutilise les routes existantes `PATCH /api/rendez-vous/{id}` et `POST /api/rendez-vous/{id}/annulation` si possible plutôt que d'en créer de nouvelles ; si une route supplémentaire te semble indispensable, demande-moi d'abord.
- Les créneaux couverts par l'indisponibilité disparaissent des créneaux proposés et le planning les affiche.
- Écrans secrétaire : « Enregistrement d'une indisponibilité » avec **affichage immédiat** des rendez-vous concernés ; liste « À reprogrammer » avec les actions « Reprogrammer » et « Annuler ».

## Module 8 — Notifications
- Une classe par événement dans `app/Notifications`, canal `mail` uniquement, `ShouldQueue` (driver `database`) : confirmation ; modification ou reprogrammation ; annulation (par le patient ou la secrétaire) ; passage à `a_reprogrammer`.
- Envoi **après** validation des transactions (`afterCommit`). Le courriel contient date, heure, médecin, spécialité et les coordonnées de `config/clinique.php` ; **aucune donnée de préconsultation**. Courriels en français.
- Journal : chaque envoi crée une ligne dans la table `notifications` (objet, contenu) et renseigne `date_envoi` quand l'envoi a réussi (par exemple via l'événement `NotificationSent`). Ne pas utiliser le canal `database` de Laravel.
- Développement : Mailpit (fourni par Laragon : `C:\laragon\bin\mailpit`, SMTP 1025, interface 8025) ou `MAIL_MAILER=log`. Documente dans le README le lancement de `php artisan queue:work`.

## Tests obligatoires
- **T11** indisponibilité couvrant des rendez-vous confirmés → statut `a_reprogrammer` et notifications envoyées ; puis indisponibilité sans rendez-vous → liste vide et **aucun courriel** (`Notification::fake()`, `Queue::fake()`).
- Plus : un courriel par événement (confirmation, modification, annulation par patient et par secrétaire, reprogrammation), aucun contenu de préconsultation dans le courriel, ligne créée dans `notifications` avec `date_envoi`, aucun envoi si la transaction échoue (409), indisponibilité refusée si `date_fin < date_debut`, accès réservé à la secrétaire.

## Fin de chaque module
Migrations + `DemoSeeder` + `artisan test` au vert, `npm run build`, vérification dans le navigateur (et dans Mailpit pour le module 8), ajout dans `docs/DECISIONS.md`, ligne « État » du README, **un commit par module**, compte rendu court. En cas d'ambiguïté, arrête-toi et pose-moi la question.
