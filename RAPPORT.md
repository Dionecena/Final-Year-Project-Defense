# Rapport final : plateforme de préconsultation et de prise de rendez-vous

Prise de rendez-vous en ligne – Clinique Croix Bleue. Document rédigé pour le chapitre III du
mémoire. Référence : [`docs/CAHIER_DES_CHARGES.md`](docs/CAHIER_DES_CHARGES.md) ; détail des choix
module par module : [`docs/DECISIONS.md`](docs/DECISIONS.md).

État au 20 octobre 2026 : les dix modules de la section 12 sont réalisés. La suite automatisée
compte **139 tests de fonctionnalités, tous réussis (843 assertions)**, exécutés sur une base
PostgreSQL locale dédiée. L'application tourne sur la base Supabase et a été utilisée à travers un
tunnel ngrok.

## 1. Atteinte des objectifs

Un objectif n'est déclaré « atteint » que s'il est implémenté **et** vérifié par un test
automatisé ou par une manipulation décrite.

| N° | Objectif | État | Justification |
|---|---|---|---|
| 1 | Gestion des utilisateurs et des spécialités | **Atteint** | Inscription du patient, connexion et déconnexion par session Sanctum, création des comptes du personnel (médecin avec sa spécialité), modification, désactivation sans suppression physique, gestion des spécialités sans route de suppression. Tests : `AuthentificationTest`, `GestionUtilisateursTest`, `GestionSpecialitesTest` (dont T01, T02). Manipulation : création d'un médecin depuis l'écran « Gestion des utilisateurs ». |
| 2 | Consultation de l'offre de soins | **Atteint** | Liste publique des spécialités avec leurs jours de présence (page « Spécialités » et page d'accueil), créneaux publics par spécialité et par médecin. Tests : `GestionSpecialitesTest`, `CreneauxTest` (T03). Manipulation : page d'accueil et page « Spécialités » consultées sans connexion, en 375 px et en bureau. |
| 3 | Gestion des rendez-vous | **Atteint** | Calcul des créneaux (section 8.1), réservation transactionnelle avec confirmation automatique, 409 si le créneau est pris, consultation, modification et annulation dans le délai de 24 h (RG08), gestion par la secrétaire avec filtres. Tests : `CreneauxTest`, `RendezVousTest` (T03, T04, T09, T10). Manipulation : réservation et modification par un patient de démonstration. |
| 4 | Plannings et indisponibilités | **Atteint** | Ajout des disponibilités par la secrétaire, sans route de modification ni de suppression (RG14). Planning en vue semaine (secrétaire) et planning du médecin en lecture seule (RG12). Indisponibilités qui passent les rendez-vous confirmés à « à reprogrammer », puis reprogrammation ou annulation. Tests : `PlanningsTest` (T13), `IndisponibilitesTest` (T11). Manipulation : indisponibilité enregistrée dans le navigateur, liste des rendez-vous concernés affichée aussitôt. |
| 5 | Préconsultation | **Atteint** | Formulaire facultatif (RG02) limité au motif et aux symptômes, 500 caractères en psychiatrie et 2 000 ailleurs (RG11), mention « pas un diagnostic » (RG03), contenu lisible seulement par le médecin du rendez-vous et par le patient (RG10). Tests : `PreconsultationTest` (T05, T08, T10). Manipulation : préconsultation saisie, puis lue depuis le planning du médecin. |
| 6 | Suggestion d'orientation | **Atteint** | `OrientationService` (section 8.3) : suggestion seulement en cas d'incompatibilité (RG04), avec les 3 prochains créneaux, et choix « Conserver ma spécialité » / « Choisir la spécialité suggérée ». Les termes de `config/orientation.php` ne sont que des **exemples** : une liste doit être validée par un médecin avant toute mise en service. Tests : `PreconsultationTest` (T06, T07). |
| 7 | Notifications | **Atteint** | Quatre courriels (confirmation, modification ou reprogrammation, annulation, passage à « à reprogrammer »), envoyés par la file d'attente `database` après la transaction, journalisés dans `notifications` avec `date_envoi`, sans donnée de préconsultation. Tests : `NotificationsTest`, `IndisponibilitesTest` (T11). Manipulation : courriels reçus dans Mailpit (module 8). |

## 2. Résultats des tests obligatoires (section 15)

Commande : `cd backend && php artisan test` (PHP 8.3.26, PostgreSQL 17.5 local, base `croixbleue_test`).
Résultat de la dernière exécution : `Tests: 139 passed (843 assertions)`.

| N° | Scénario | Test(s) | Résultat |
|---|---|---|---|
| T01 | Inscription avec une adresse déjà utilisée | `AuthentificationTest::test_t01_inscription_avec_une_adresse_deja_utilisee_est_refusee` | Réussi : 422, « Cette adresse électronique est déjà utilisée. » |
| T02 | Mauvais mot de passe, puis compte désactivé | `AuthentificationTest::test_t02_mauvais_mot_de_passe_renvoie_un_message_generique`, `…test_t02_compte_desactive_ne_peut_pas_se_connecter` | Réussi : « Identifiants incorrects. » dans les deux cas, aucune session ouverte |
| T03 | Créneaux avec indisponibilité et rendez-vous réservés | `CreneauxTest::test_t03_creneaux_indisponibles_et_reserves_absents` | Réussi : créneaux couverts et réservés absents |
| T04 | Deux réservations du même créneau | `RendezVousTest::test_t04_deux_reservations_du_meme_creneau`, `…test_t04_violation_de_l_index_unique_renvoie_409` | Réussi : une seule enregistrée, la seconde reçoit 409 (y compris par l'index unique partiel) |
| T05 | Préconsultation sans motif ou sans symptômes | `PreconsultationTest::test_t05_preconsultation_sans_motif_ou_sans_symptomes` | Réussi : erreur de validation |
| T06 | Motif d'une autre spécialité | `PreconsultationTest::test_t06_motif_d_une_autre_specialite_suggestion_avec_creneaux`, `…test_t06_le_patient_choisit_la_specialite_suggeree` | Réussi : suggestion avec créneaux ; réservation possible dans la spécialité initiale (`specialite_suggeree_id` renseignée) |
| T07 | Motif compatible | `PreconsultationTest::test_t07_motif_compatible_aucune_suggestion` | Réussi : aucune suggestion |
| T08 | Psychiatrie au-delà de 500 caractères | `PreconsultationTest::test_t08_psychiatrie_plus_de_500_caracteres` | Réussi : erreur de validation |
| T09 | Annulation après le délai | `RendezVousTest::test_t09_annulation_et_modification_apres_le_delai_refusees` | Réussi : 403, invitation à contacter la clinique |
| T10 | Rendez-vous d'un autre patient ; préconsultation consultée par un tiers | `RendezVousTest::test_t10_acces_au_rendez_vous_d_un_autre_patient`, `PreconsultationTest::test_t10_preconsultation_lisible_par_le_medecin_du_rendez_vous_et_le_patient`, `…test_t10_preconsultation_refusee_aux_autres` | Réussi : 403 pour l'autre patient, l'autre médecin, la secrétaire et l'administrateur |
| T11 | Indisponibilité avec, puis sans rendez-vous | `IndisponibilitesTest::test_t11_indisponibilite_couvrant_des_rendez_vous_puis_sans_rendez_vous`, `…test_t11_les_courriels_sont_places_dans_la_file_d_attente` | Réussi : statut `a_reprogrammer` et un courriel par patient ; puis liste vide et aucun courriel (`Notification::fake()`, `Queue::fake()`) |
| T12 | `rendezvous:marquer-passes` | `TachePlanifieeTest::test_t12_marquer_passes_ne_touche_que_les_rendez_vous_confirmes_depasses`, `…test_t12_execution_repetee_sans_effet_supplementaire` | Réussi : confirmés dépassés → `passe`, autres statuts inchangés |
| T13 | Modification ou suppression d'une disponibilité | `PlanningsTest::test_t13_aucune_route_de_modification_ni_de_suppression_d_une_disponibilite` | Réussi : 404/405, aucune route |

## 3. Versions installées

| Composant | Version |
|---|---|
| PHP | 8.3.26 (Laragon, avec OPcache) |
| Laravel | 12.70.0 |
| Laravel Sanctum | 4.3.3 |
| PHPUnit | 11.5.57 |
| React / React DOM | 18.3.1 |
| React Router | 6.30.1 |
| Axios | 1.12.2 |
| TypeScript | 5.9.3 |
| Vite | 7.1.9 |
| Tailwind CSS | 4.1.14 |
| three.js (animations de l'accueil) | 0.180.0 |
| Node.js / npm | 25.8.1 / 11.16.0 |
| PostgreSQL local (tests) | 17.5 |
| PostgreSQL Supabase (application) | 17.11 (projet « final-year-project-defense », région eu-west-2, session pooler) |
| ngrok | 3.39.9 |
| Mailpit (développement) | 1.22.3 |

## 4. Écarts par rapport au cahier des charges

Validés par l'auteur :

1. **Changement de médecin** : seule la secrétaire peut déplacer un rendez-vous vers un autre médecin
   de la même spécialité. Le patient ne change que le créneau, avec le même médecin. La section 8.2
   autorisait aussi le patient à changer de médecin.
2. **Route ajoutée `GET /api/medecins`** (secrétaire, lecture seule), absente de la section 14 :
   elle sert à choisir le médecin dans les écrans du planning et des indisponibilités.
3. **Disponibilités qui se chevauchent** pour un même médecin et un même jour : refusées.
4. **Égalité dans l'orientation** : la spécialité listée en premier dans `config/orientation.php`
   l'emporte. `specialite_suggeree_id` n'est renseignée que si le patient **conserve** sa
   spécialité, si bien que la statistique « suggestions émises » ne compte que les suggestions non
   suivies.
5. **Indisponibilités** : une période déjà passée est refusée ; les rendez-vous dont l'heure est
   passée ne sont pas concernés ; aucune route de modification ni de suppression.
6. **Statistiques** : regroupement par mois ; par défaut, du 1er jour du mois cinq mois en arrière
   au dernier jour du mois suivant.

Décisions techniques ou de sécurité, prises pendant le développement :

7. **Téléphone obligatoire** (la colonne n'est pas indiquée « nullable » dans la section 5) ;
   ajout de `ADMIN_TELEPHONE` pour le compte administrateur initial.
8. **Gestion des comptes** (à confirmer par l'auteur) : l'administrateur ne crée que des comptes du
   personnel ; le rôle d'un patient ou d'un médecin n'est pas modifiable ; l'administrateur ne peut
   ni se désactiver ni changer son propre rôle ; mot de passe de 8 caractères minimum, avec au
   moins une lettre et un chiffre.
9. **Protection de l'API automatique de Supabase** : migration `2025_01_01_000008` qui active la
   RLS (sans politique) sur toutes les tables et retire les droits aux rôles `anon` et
   `authenticated`. Sans elle, la clé publique du projet aurait permis de lire les données par
   l'API REST générée de Supabase. Vérifié : le rôle `anon` reçoit « permission denied ».
10. **Connexions PostgreSQL persistantes** (`DB_PERSISTENT=true`) et **OPcache** activé dans le PHP de
    Laragon : une requête d'API passe d'environ 1,5 s à 0,45 s en local avec Supabase.
11. **Page d'accueil** : la mise en page reprend le modèle fourni, mais tout contenu non conforme a
    été retiré (nom commercial, chiffres inventés, médecins nommés, témoignages, services hors
    périmètre). Les animations 3D sont désactivées sur smartphone et pour les utilisateurs qui
    demandent de réduire les animations.
12. Pas de colonne `remember_token` (pas de « se souvenir de moi »).

Limites connues :

- Les termes d'orientation sont des exemples et doivent être validés par un médecin de la clinique.
- Le délai de 24 h (RG08) et les coordonnées de la clinique dans les courriels sont à confirmer par
  la clinique (`.env`).
- À travers ngrok depuis Dakar, une action prend environ 1 s et une connexion environ 2,5 s
  (latence du tunnel, environ 0,4 s par requête, et de la base à Londres, environ 70 ms par
  requête SQL). Si besoin, `SESSION_DRIVER=file` supprimerait les allers-retours de session vers
  Supabase.
- Le compte ngrok gratuit affiche une page d'avertissement à la première visite (« Visit Site »).
- La tâche Windows du planificateur n'est pas créée sur le poste : la procédure est décrite dans le
  README.

## 5. Écrans à capturer pour le mémoire

Comptes de démonstration (`DemoSeeder`, mot de passe `DEMO_PASSWORD` de `backend/.env`) :
`patient1.demo@exemple.test`, `secretaire.demo@exemple.test`, `medecin.<specialite>@exemple.test`,
et le compte administrateur `ADMIN_EMAIL`.

1. **Connexion** : `/connexion` (avec le message « Identifiants incorrects. » après une erreur).
2. **Choix de la spécialité et des créneaux** : `/patient/prise-de-rendez-vous`, étapes 1 et 4.
3. **Préconsultation et suggestion** : étape 2 (formulaire et mention RG03), puis étape 3 avec un
   motif d'une autre spécialité (par exemple « problème de peau » en ophtalmologie).
4. **Rendez-vous du patient** : `/patient/mes-rendez-vous` (statuts, « Modifier », « Annuler »,
   message de délai dépassé).
5. **Planning des médecins et ajout d'une disponibilité** : `/secretariat/planning`.
6. **Enregistrement d'une indisponibilité** : `/secretariat/indisponibilites`, avec la liste des
   rendez-vous concernés, puis `/secretariat/a-reprogrammer`.
7. **Planning du médecin et préconsultation** : `/medecin`, clic sur un rendez-vous qui a une
   préconsultation.
8. **Gestion des utilisateurs** : `/admin/utilisateurs` (liste et formulaire de création d'un médecin).
9. **Tunnel ngrok** : la console de `ngrok http 8000` et l'application ouverte sur un smartphone à
   l'adresse `https://…ngrok-free.dev`.
10. **Tables de la base** : *Table Editor* de Supabase (tables `users`, `rendez_vous`,
    `preconsultations`, `notifications`), et l'index `rendez_vous_creneau_actif_unique`.

Captures utiles en complément : la page d'accueil (encadré « Sans rendez-vous, c'est toujours
possible »), les statistiques de l'administrateur (`/admin`) et un courriel dans Mailpit.
