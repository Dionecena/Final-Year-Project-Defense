# Cahier des charges : plateforme de préconsultation et de prise de rendez-vous médicaux

> Document de référence fourni par l'auteur du mémoire. Le code doit y correspondre exactement :
> aucune fonctionnalité hors périmètre, aucune retirée sans le signaler.

## 0. Rôle et manière de travailler

Tu es un développeur full-stack senior. Tu vas concevoir et développer une application web complète, en suivant **strictement** le cahier des charges ci-dessous, qui correspond à un mémoire de Licence en Génie Logiciel déjà rédigé. Le code doit donc correspondre exactement à ce qui est décrit : ne rajoute aucune fonctionnalité hors périmètre et n'en retire aucune sans le signaler.

Règles de travail :

1. Travaille **module par module**, dans l'ordre de la section 12. À la fin de chaque module : lance les migrations et les tests, puis donne un court compte rendu (ce qui est fait, ce qui reste, problèmes rencontrés).
2. Si une règle est ambiguë ou si une contrainte technique t'oblige à t'en écarter, **arrête-toi et pose la question** au lieu de décider seul.
3. **Ne donne aucun nom commercial ni nom de marque à la plateforme.** Dans l'interface, utilise un intitulé descriptif, par exemple « Prise de rendez-vous en ligne – Clinique Croix Bleue ». N'utilise aucun logo de la clinique tant qu'il n'en a pas été fourni un.
4. **N'invente aucune donnée réelle sur la clinique.** Les seules données réelles sont la liste des spécialités (section 6). Les médecins, horaires et patients de démonstration doivent être **fictifs** et placés dans un seeder de démonstration séparé, clairement identifié.
5. L'interface est **entièrement en français**. Le code (noms de classes, de tables, de variables) suit les noms du modèle de la section 5.
6. Fuseau horaire de l'application : `Africa/Dakar`.

## 1. Contexte du projet

La Clinique Croix Bleue est une clinique privée de Dakar (Sénégal). Aujourd'hui :

- les patients peuvent se présenter directement à l'accueil, sans rendez-vous ;
- plusieurs spécialités ne sont assurées que certains jours de la semaine ;
- lorsqu'un médecin devient indisponible, il prévient la secrétaire, qui appelle elle-même les patients concernés ;
- le médecin ne dispose d'aucune information sur le patient avant la consultation.

La plateforme est **complémentaire de l'accueil direct**, qu'elle ne remplace pas. Elle permet :

- au patient de consulter les créneaux réellement disponibles, de réserver avec confirmation automatique et de remplir une préconsultation facultative ;
- de suggérer au patient, de façon indicative, une autre spécialité lorsque son motif paraît incompatible avec celle qu'il a choisie ;
- à la secrétaire de gérer les disponibilités et les indisponibilités des médecins, les rendez-vous touchés par une indisponibilité étant identifiés et leurs patients informés automatiquement ;
- au médecin de consulter son planning et les préconsultations de ses propres rendez-vous.

**Hors périmètre** (à ne pas développer) : dossier médical, ordonnances, facturation, paiement, téléconsultation, envoi de SMS, rappels automatiques avant rendez-vous, chatbot, toute fonction de **diagnostic**.

## 2. Pile technique imposée

| Couche | Technologie |
|---|---|
| Frontend | React 18, TypeScript, Vite, Tailwind CSS, React Router ; appels HTTP avec Axios |
| Backend | Laravel 12, PHP 8.3 ou 8.4 (ne pas utiliser PHP 8.2, dont le support de sécurité s'arrête le 31/12/2026) |
| Authentification | Laravel Sanctum en **mode SPA** (session et cookies, protection CSRF), sans jeton stocké dans le navigateur |
| Base de données | PostgreSQL hébergé sur Supabase (section 11) ; PostgreSQL local uniquement pour les tests |
| Courriels | Système de notifications de Laravel, canal `mail`, envoyés via une **file d'attente** (driver `database`) ; en développement, utiliser Mailpit ou le driver `log` |
| Tâches planifiées | Planificateur de Laravel |
| Tests | PHPUnit ou Pest (tests de fonctionnalités Laravel) |

Organisation du dépôt : un monorepo avec deux dossiers, `backend/` (Laravel) et `frontend/` (React), plus un `README.md` racine.

## 3. Architecture

Architecture trois-tiers :

```
Navigateur (React SPA)  ──HTTPS / JSON──>  API Laravel  ──SQL──>  PostgreSQL
                                              │
                                              ├── File d'attente → courriels
                                              └── Planificateur → statut « passé »
```

Organisation du backend par couches :

- **routes et middlewares** : `routes/api.php`, middleware `auth:sanctum` et un middleware de rôle (`role:patient`, `role:secretaire`, etc.) ;
- **FormRequest** : toute la validation des entrées ;
- **contrôleurs** : minces, ils délèguent aux services ;
- **services métier** (`app/Services`) : `CreneauService`, `RendezVousService`, `OrientationService`, `IndisponibiliteService`, `StatistiqueService` ;
- **modèles Eloquent et politiques d'accès** (`app/Policies`) : `RendezVousPolicy`, `PreconsultationPolicy`, `UtilisateurPolicy` ;
- **notifications** (`app/Notifications`) : une classe par événement (section 9).

## 4. Acteurs et rôles

Un seul modèle d'authentification (`users`), avec un attribut `role` qui prend l'une de ces valeurs :

| Rôle | Description |
|---|---|
| `patient` | Crée lui-même son compte, réserve et gère ses rendez-vous, effectue des préconsultations |
| `secretaire` | Gère les rendez-vous, les disponibilités et les indisponibilités des médecins |
| `medecin` | Consulte son planning et les préconsultations de ses rendez-vous ; ne modifie pas son planning |
| `administrateur` | Gère les comptes, les rôles, les spécialités ; consulte les statistiques |

Seul le patient s'inscrit lui-même. Les comptes du personnel (secrétaire, médecin, administrateur) sont créés par l'administrateur. Un compte désactivé (`actif = false`) ne peut plus se connecter.

## 5. Modèle de données (conforme au diagramme de classes du mémoire)

Classes du diagramme : `Utilisateur` (super-classe) avec les sous-classes `Patient`, `Secretaire`, `Medecin`, `Administrateur` ; puis `Specialite`, `Disponibilite`, `Indisponibilite`, `RendezVous`, `Preconsultation`, `Notification` et l'énumération `StatutRdv`.

Traduction en base :

- l'héritage est traduit par **une seule table `users`** avec la colonne `role` ; les données propres au médecin sont dans une table `medecins` liée à son compte ;
- les modèles Eloquent peuvent exposer des scopes ou des classes dédiées (`Patient`, `Medecin`…) tant que la table reste unique.

Tables et contraintes :

**users** : `id`, `nom`, `prenom`, `email` (unique), `telephone`, `password` (haché), `role` (CHECK dans patient, secretaire, medecin, administrateur), `actif` (booléen, vrai par défaut), `timestamps`.

**specialites** : `id`, `libelle` (unique), `description` (nullable), `timestamps`.

**medecins** : `user_id` (clé primaire et clé étrangère vers `users`), `specialite_id` (clé étrangère vers `specialites`).

**disponibilites** : `id`, `medecin_id` (clé étrangère vers `medecins.user_id`, suppression en cascade), `jour_semaine` (SMALLINT, CHECK entre 1 = lundi et 7 = dimanche), `heure_debut`, `heure_fin` (TIME, CHECK `heure_fin > heure_debut`), `duree_creneau` (minutes, CHECK > 0), `timestamps`.

**indisponibilites** : `id`, `medecin_id` (clé étrangère), `enregistree_par` (clé étrangère vers `users` ; la secrétaire), `date_debut`, `date_fin` (TIMESTAMP, CHECK `date_fin >= date_debut`), `motif` (nullable), `timestamps`.

**rendez_vous** : `id`, `patient_id` (clé étrangère vers `users`), `medecin_id` (clé étrangère vers `medecins.user_id`), `date_heure` (TIMESTAMP, début du créneau), `statut` (CHECK dans confirme, annule, a_reprogrammer, passe ; défaut confirme), `timestamps`.

- **Index unique partiel obligatoire**, ajouté par une instruction SQL brute dans la migration :

```sql
CREATE UNIQUE INDEX rendez_vous_creneau_actif_unique
    ON rendez_vous (medecin_id, date_heure)
    WHERE statut IN ('confirme', 'a_reprogrammer');
```

**preconsultations** : `id`, `rendez_vous_id` (clé étrangère, **unique**, suppression en cascade), `motif` (VARCHAR, obligatoire), `symptomes` (TEXT, obligatoire), `specialite_suggeree_id` (clé étrangère nullable vers `specialites`), `date_saisie`.

**notifications** : `id`, `rendez_vous_id` (clé étrangère), `objet`, `contenu` (TEXT), `date_envoi` (nullable, renseignée à l'envoi effectif), `timestamps`. Cette table sert de **journal des courriels envoyés** : ne pas utiliser le canal `database` de Laravel, qui réclame une table de même nom avec un autre schéma.

Ajoute aussi les tables techniques de Laravel (sessions, jobs, failed_jobs, cache).

Suppression : **jamais de suppression physique d'un compte**. « Supprimer » un utilisateur revient à passer `actif` à faux.

## 6. Données initiales

Seeder `SpecialiteSeeder` (données réelles, communiquées par la secrétaire de la clinique) avec les dix spécialités et, dans la description, les jours de présence :

| Spécialité | Jours de présence communiqués |
|---|---|
| Médecine générale | Tous les jours |
| Pédiatrie | Tous les jours |
| ORL | Lundi, mercredi, vendredi |
| Cardiologie | Lundi, mercredi |
| Ophtalmologie | Mardi |
| Psychiatrie | Lundi, vendredi, samedi |
| Dermatologie | Mercredi, samedi |
| Neurologie | Mercredi |
| Neurochirurgie | Jeudi |
| Chirurgie générale | Jeudi |

Seeder `AdministrateurSeeder` : un compte administrateur dont l'email et le mot de passe sont lus dans `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`).

Seeder `DemoSeeder`, **séparé et non exécuté par défaut**, qui crée pour la démonstration : une secrétaire, un médecin fictif par spécialité avec des disponibilités cohérentes avec les jours ci-dessus (plages horaires fictives), deux ou trois patients et quelques rendez-vous. Tous les noms doivent être manifestement fictifs, par exemple « Médecin Démo ORL ».

## 7. Règles de gestion (à implémenter et à tester)

| Réf. | Règle |
|---|---|
| RG01 | Toute fonctionnalité autre que l'accueil public, la liste des spécialités, l'inscription et la connexion exige une authentification. |
| RG02 | La préconsultation est **facultative** : elle ne bloque jamais la réservation. |
| RG03 | La préconsultation et la suggestion d'orientation **ne constituent pas un diagnostic**. Le texte « Ces informations ne constituent pas un diagnostic et ne remplacent pas une consultation médicale » est affiché sur le formulaire et sur la suggestion. |
| RG04 | La suggestion n'apparaît **qu'en cas d'incompatibilité** entre le motif et la spécialité choisie. Le patient peut toujours conserver son choix initial. |
| RG05 | Un créneau n'est proposé et ne peut être réservé que s'il est compris dans une disponibilité du médecin, non couvert par une indisponibilité, non passé et non déjà réservé (statut confirme ou a_reprogrammer). |
| RG06 | La confirmation est **automatique** : un rendez-vous enregistré a directement le statut `confirme`, sans validation par la secrétaire. |
| RG07 | Si le créneau a été pris entre-temps (violation de l'index unique), le patient reçoit un message clair et choisit un autre créneau. |
| RG08 | Un patient ne modifie ou n'annule que **ses propres** rendez-vous, et seulement jusqu'à un délai avant l'heure du rendez-vous (`config/rendezvous.php`, clé `delai_modification_heures`, valeur par défaut **24**, à confirmer par la clinique). Passé ce délai, l'interface invite à contacter la clinique. |
| RG09 | L'enregistrement d'une indisponibilité fait passer les rendez-vous confirmés de la période au statut `a_reprogrammer`, notifie chaque patient concerné et renvoie la liste à la secrétaire, qui les reprogramme ou les annule ensuite. |
| RG10 | Le contenu d'une préconsultation n'est lisible que par le **médecin du rendez-vous** et par le **patient** qui l'a saisie. La secrétaire voit seulement si une préconsultation existe ; l'administrateur n'y a pas accès. |
| RG11 | En psychiatrie, les symptômes sont saisis dans un **champ libre et court** (500 caractères au maximum, sans questionnaire détaillé). Les spécialités concernées sont listées dans `config/preconsultation.php`, clé `saisie_restreinte` (valeur : `['Psychiatrie']`). Pour les autres spécialités, le maximum est de 2 000 caractères. |
| RG12 | Le médecin consulte son planning mais **ne le modifie pas**. |
| RG13 | La plateforme n'empêche jamais un patient de se présenter directement : la page d'accueil le rappelle. |
| RG14 | Une disponibilité n'est **ni modifiée ni supprimée** : aucune route de modification ni de suppression ne doit exister. Tout changement passe par une indisponibilité. |

## 8. Algorithmes métier

### 8.1 Calcul des créneaux (`CreneauService`)

Entrées : un médecin (ou une spécialité) et une période ; horizon par défaut de 30 jours (`config/rendezvous.php`, clé `horizon_jours`).

1. Pour chaque jour de la période, retenir les disponibilités dont `jour_semaine` correspond au jour.
2. Découper chaque plage `[heure_debut, heure_fin[` en créneaux successifs de `duree_creneau` minutes ; un créneau qui dépasse `heure_fin` est ignoré.
3. Retirer les créneaux passés et ceux qui chevauchent une indisponibilité du médecin.
4. Retirer les créneaux qui portent déjà un rendez-vous au statut `confirme` ou `a_reprogrammer`.
5. Renvoyer les créneaux triés, regroupés par jour.

Le même service fournit `prochainsCreneaux(specialite, n = 3)` : les *n* prochains créneaux libres, tous médecins de la spécialité confondus.

### 8.2 Réservation (`RendezVousService::reserver`)

Dans **une transaction** :

1. Revérifier, via `CreneauService`, que le créneau est toujours disponible.
2. Insérer le rendez-vous avec le statut `confirme`.
3. Si une préconsultation est fournie, l'insérer.

En cas de violation de l'index unique, attraper l'exception, annuler la transaction et renvoyer une erreur 409 avec un message clair (RG07). **Après** validation de la transaction seulement, placer le courriel de confirmation dans la file d'attente.

La modification par le patient consiste à changer de créneau, avec le même médecin ou un autre médecin de la même spécialité. Elle suit la même logique transactionnelle et respecte le délai de RG08.

### 8.3 Orientation (`OrientationService`)

- Les règles sont dans `config/orientation.php`, qui associe à chaque libellé de spécialité une liste de termes. **Fournir uniquement quelques termes d'exemple**, avec un commentaire indiquant que la liste doit être établie et validée par un médecin de la clinique avant toute mise en service. Ne pas inventer de règles médicales détaillées.
- Normalisation du texte (motif et symptômes) : minuscules, suppression des accents et de la ponctuation.
- Une spécialité correspond si au moins un de ses termes apparaît dans le texte normalisé.
- **Aucune suggestion** si la spécialité choisie fait partie des correspondances, ou si aucune règle ne s'applique.
- Sinon, suggérer la spécialité qui a le plus de correspondances, accompagnée de `prochainsCreneaux(specialite, 3)`.
- Route dédiée : `POST /api/orientation/verification`, appelée avant la réservation. Si le patient garde sa spécialité initiale, la préconsultation est quand même enregistrée, avec `specialite_suggeree_id` renseignée.

### 8.4 Indisponibilité (`IndisponibiliteService::enregistrer`)

Dans une transaction :

1. Valider que `date_fin >= date_debut`.
2. Créer l'indisponibilité, avec `enregistree_par` égal à la secrétaire connectée.
3. Rechercher les rendez-vous `confirme` du médecin dont `date_heure` tombe dans la période et les passer à `a_reprogrammer`.

Après la transaction, notifier chaque patient concerné et renvoyer la liste des rendez-vous concernés. Si aucun n'est concerné, la liste est vide et aucun courriel n'est envoyé.

La secrétaire traite ensuite chaque rendez-vous `a_reprogrammer` :

- **reprogrammer** : choix d'un nouveau créneau libre ; le rendez-vous repasse à `confirme` et le patient est notifié ;
- **annuler** : le statut passe à `annule` et le patient est notifié.

### 8.5 Tâche planifiée

Commande `rendezvous:marquer-passes`, exécutée **toutes les heures**, qui passe à `passe` les rendez-vous `confirme` dont `date_heure` est dépassée.

## 9. Notifications par courriel

Quatre événements déclenchent un courriel au patient, toujours via la file d'attente :

1. confirmation d'un rendez-vous ;
2. modification ou reprogrammation ;
3. annulation, que l'auteur soit le patient ou la secrétaire ;
4. passage à `a_reprogrammer` à la suite d'une indisponibilité.

Chaque envoi crée une ligne dans la table `notifications` (objet, contenu) et renseigne `date_envoi` lorsque l'envoi a réussi. Le courriel indique la date, l'heure, le médecin et la spécialité, ainsi que les coordonnées de la clinique à compléter dans `.env`. **Il ne contient aucune donnée de préconsultation.**

## 10. Sécurité et protection des données

Les préconsultations sont des données de santé, protégées au Sénégal par la loi n° 2008-12 sur la protection des données personnelles.

- Mots de passe hachés (mécanisme par défaut de Laravel) ; jamais stockés ni journalisés en clair.
- Connexion : message d'erreur **générique** (« Identifiants incorrects ») et limitation à 5 tentatives par minute par adresse électronique et adresse IP.
- Vérification de `actif` à la connexion.
- Middleware de rôle sur chaque groupe de routes, et **politiques d'accès** au niveau de chaque ressource (RG08, RG10). Le masquage des menus côté React ne remplace jamais le contrôle serveur.
- Validation de toutes les entrées côté serveur (FormRequest).
- Aucune requête SQL construite par concaténation : utiliser Eloquent ou des requêtes paramétrées.
- Pas de `dangerouslySetInnerHTML` dans React.
- Ressources API (`JsonResource`) qui n'exposent que les champs nécessaires, jamais `password`.
- Collecte minimale : la préconsultation se limite au motif et aux symptômes.

## 11. Environnement d'exécution et mise à disposition

La plateforme s'exécute **en local** et est rendue accessible par **ngrok**.

**Contrainte importante.** Sanctum en mode SPA exige que le frontend et l'API soient servis par la **même origine**. Avec ngrok, n'utiliser donc **qu'un seul tunnel** :

- **en développement** : le serveur Vite redirige (proxy) `/api`, `/sanctum`, `/login` et `/logout` vers Laravel, et ngrok pointe sur le port de Vite ;
- **pour la démonstration** : `npm run build`, les fichiers générés sont servis par Laravel (route de repli vers `index.html` pour React Router), et ngrok pointe sur Laravel.

Configurer `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` et `APP_URL` pour ce domaine et documenter la procédure dans le README.

**Base de données : PostgreSQL hébergé sur Supabase** (choix arrêté). Supabase n'est utilisé **que comme base de données** : ni Supabase Auth, ni Storage, ni les API générées automatiquement. L'authentification reste celle de Laravel Sanctum, et tout accès aux données passe par l'API Laravel.

- **Connexion depuis Laravel** : driver `pgsql`, paramètres lus dans `.env` (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) et `DB_SSLMODE=require`. Ne jamais mettre de vrais identifiants dans le dépôt.
- **Point de connexion** :
  - utiliser la **connexion directe** (port 5432) si le poste dispose d'IPv6 ;
  - sinon, le **session pooler** (port 5432, IPv4), qui accepte les requêtes préparées ;
  - **ne pas utiliser le transaction pooler (port 6543)** : il n'accepte pas les requêtes préparées et ne conserve pas l'état de session.
  - Documenter dans le README comment récupérer ces paramètres dans le tableau de bord Supabase.
- **Schéma** : les tables sont créées **uniquement par les migrations Laravel** (`php artisan migrate`), jamais à la main dans la console Supabase, afin que le schéma reste versionné. L'index unique partiel et les contraintes CHECK sont créés par ces migrations.
- **Base de test** : les tests automatisés ne doivent **jamais** s'exécuter sur la base Supabase. Utiliser une base PostgreSQL locale ou un conteneur dédié aux tests (`.env.testing`).
- **Données** : utiliser **exclusivement des données fictives**. Supabase héberge les données hors du Sénégal, et des données de santé réelles exigeraient les formalités prévues par la loi n° 2008-12 auprès de la CDP.
- **Mise en pause** : un projet Supabase gratuit est suspendu après 7 jours de faible activité. Indiquer dans le README comment le réactiver depuis le tableau de bord, et préciser qu'il faut le vérifier avant chaque démonstration.
- **Version** : relever la version de PostgreSQL du projet Supabase et l'indiquer dans `RAPPORT.md`.

Fournir un `.env.example` complet pour les deux dossiers.

## 12. Ordre de développement (méthode Kanban, un module à la fois)

1. **Socle** : création des projets, configuration de Sanctum en mode SPA et du proxy Vite, migrations complètes, seeders, layout React avec routage par rôle.
2. **Gestion des utilisateurs** : inscription du patient, connexion et déconnexion, gestion des comptes et des rôles par l'administrateur (création d'un médecin avec sa spécialité, désactivation).
3. **Spécialités** : gestion par l'administrateur, liste publique avec les jours de présence.
4. **Plannings** : ajout des disponibilités par la secrétaire (RG14), consultation du planning par la secrétaire et le médecin.
5. **Créneaux et rendez-vous** : calcul des créneaux, réservation, consultation, modification et annulation par le patient, gestion par la secrétaire.
6. **Préconsultation et orientation** : formulaire facultatif intégré au parcours, règle de la psychiatrie, suggestion indicative, consultation par le médecin.
7. **Indisponibilités** : enregistrement, rendez-vous à reprogrammer, reprogrammation et annulation par la secrétaire.
8. **Notifications** : courriels via la file d'attente, journal dans `notifications`.
9. **Statistiques** : tableau de bord de l'administrateur.
10. **Tâche planifiée**, finitions responsives, README, mise à disposition avec ngrok.

## 13. Interfaces à réaliser (React)

Design sobre et professionnel, pensé d'abord pour le smartphone, accessible : labels sur tous les champs et contrastes suffisants.

**Pages publiques**
- **Accueil** : présentation, accès à la prise de rendez-vous, rappel que l'on peut toujours se présenter directement à l'accueil.
- **Spécialités** : liste avec les jours de présence.
- **Inscription** et **connexion**.

**Espace patient**
- Tableau de bord.
- **Prise de rendez-vous** en 5 étapes :
  1. spécialité ;
  2. proposition de préconsultation facultative (bouton « Passer cette étape ») ;
  3. suggestion d'orientation éventuelle, avec deux boutons : « Conserver ma spécialité » et « Choisir la spécialité suggérée » ;
  4. médecin et créneau ;
  5. récapitulatif et confirmation.
- **Mes rendez-vous** : statut, modification et annulation si le délai le permet.

**Espace secrétaire**
- Liste des rendez-vous, avec filtres par médecin, date et statut.
- Planning des médecins, en vue semaine.
- Ajout d'une disponibilité.
- Enregistrement d'une indisponibilité, avec affichage immédiat des rendez-vous concernés.
- Liste des rendez-vous « à reprogrammer », avec les actions « Reprogrammer » et « Annuler ».

**Espace médecin**
- Planning du jour et de la semaine.
- Détail d'un rendez-vous, avec la préconsultation si elle existe.

**Espace administrateur**
- Gestion des utilisateurs : liste, création, modification, désactivation, attribution du rôle et de la spécialité.
- Gestion des spécialités.
- Statistiques :
  - nombre de rendez-vous par statut, par spécialité et par période ;
  - nombre de préconsultations et de suggestions émises ;
  - nombre d'indisponibilités.

Libellés des statuts : `confirme` → « Confirmé », `annule` → « Annulé », `a_reprogrammer` → « À reprogrammer », `passe` → « Passé ».

## 14. Routes API attendues

| Méthode | Route | Accès |
|---|---|---|
| POST | /api/inscription | Public |
| POST | /login, /logout ; GET /api/moi | Public / authentifié |
| GET | /api/specialites | Public |
| GET | /api/specialites/{id}/creneaux?du=&au= | Public |
| GET | /api/medecins/{id}/creneaux?du=&au= | Public |
| POST | /api/orientation/verification | Patient |
| POST | /api/rendez-vous | Patient |
| GET | /api/mes-rendez-vous | Patient |
| PATCH | /api/rendez-vous/{id} | Patient (le sien, avant le délai) ou secrétaire |
| POST | /api/rendez-vous/{id}/annulation | Patient (le sien, avant le délai) ou secrétaire |
| GET | /api/secretariat/rendez-vous | Secrétaire |
| GET | /api/medecins/{id}/planning | Secrétaire |
| POST | /api/medecins/{id}/disponibilites | Secrétaire (aucune route PUT ni DELETE : RG14) |
| POST | /api/medecins/{id}/indisponibilites | Secrétaire |
| GET | /api/medecin/planning | Médecin |
| GET | /api/rendez-vous/{id}/preconsultation | Médecin du rendez-vous ou patient concerné |
| GET, POST, PATCH | /api/admin/utilisateurs, /api/admin/utilisateurs/{id} | Administrateur |
| POST | /api/admin/utilisateurs/{id}/desactivation | Administrateur |
| GET, POST, PATCH | /api/admin/specialites | Administrateur |
| GET | /api/admin/statistiques | Administrateur |

## 15. Tests obligatoires (tests de fonctionnalités Laravel)

| N° | Scénario | Résultat attendu |
|---|---|---|
| T01 | Inscription avec une adresse déjà utilisée | Refus avec message |
| T02 | Connexion avec un mauvais mot de passe, puis avec un compte désactivé | Message générique ; accès refusé |
| T03 | Créneaux d'un médecin ayant une indisponibilité et des rendez-vous réservés | Créneaux indisponibles et réservés absents |
| T04 | Deux réservations du même créneau | Une seule enregistrée ; la seconde renvoie 409 |
| T05 | Préconsultation sans motif ou sans symptômes | Erreur de validation |
| T06 | Motif correspondant à une autre spécialité selon la configuration | Suggestion avec créneaux ; réservation possible dans la spécialité initiale |
| T07 | Motif compatible avec la spécialité choisie | Aucune suggestion |
| T08 | Préconsultation en psychiatrie dépassant 500 caractères | Erreur de validation |
| T09 | Annulation après le délai | Refus |
| T10 | Accès au rendez-vous d'un autre patient ; accès d'un médecin ou d'une secrétaire au contenu d'une préconsultation qui ne le concerne pas | 403 |
| T11 | Indisponibilité couvrant des rendez-vous confirmés, puis indisponibilité sans rendez-vous | Statut `a_reprogrammer` et notifications ; puis liste vide et aucun courriel |
| T12 | Exécution de `rendezvous:marquer-passes` | Statut `passe` pour les rendez-vous dépassés |
| T13 | Tentative de modification ou de suppression d'une disponibilité | Route inexistante (404 ou 405) |

Utiliser `Notification::fake()` et `Queue::fake()` pour vérifier les envois.

## 16. Livrables attendus

1. Le code complet (`backend/`, `frontend/`), versionné avec Git, avec des commits par module.
2. Un `README.md` qui couvre : prérequis (versions de PHP, Node et PostgreSQL), installation, `.env`, migrations et seeders, lancement (serveur, file d'attente, planificateur), procédure ngrok, exécution des tests.
3. Les `.env.example`.
4. Un **rapport final** (`RAPPORT.md`) pour le chapitre III du mémoire :
   - pour chaque objectif ci-dessous, l'état « atteint », « partiellement atteint » ou « non atteint », avec justification :
     1. gestion des utilisateurs et des spécialités ;
     2. consultation de l'offre de soins ;
     3. gestion des rendez-vous ;
     4. plannings et indisponibilités ;
     5. préconsultation ;
     6. suggestion d'orientation ;
     7. notifications ;
   - le résultat de chaque test T01 à T13 ;
   - les versions exactes installées (PHP, Laravel, React, TypeScript, Tailwind, PostgreSQL, Node) ;
   - les écarts éventuels par rapport à ce cahier des charges ;
   - la liste des écrans à capturer pour le mémoire : connexion ; choix de la spécialité et des créneaux ; préconsultation et suggestion ; rendez-vous du patient ; planning des médecins et ajout d'une disponibilité ; enregistrement d'une indisponibilité ; planning du médecin et préconsultation ; gestion des utilisateurs ; tunnel ngrok ; tables de la base.

**Ne déclarer un objectif « atteint » que s'il est réellement implémenté et vérifié par un test ou une manipulation décrite.**
