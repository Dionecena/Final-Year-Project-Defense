# Prise de rendez-vous en ligne – Clinique Croix Bleue

Plateforme de préconsultation et de prise de rendez-vous médicaux, complémentaire de l'accueil
direct de la clinique. Projet de mémoire de Licence en Génie Logiciel.

| Dossier | Contenu |
|---|---|
| `backend/` | API Laravel 12 (PHP 8.3), Sanctum en mode SPA, PostgreSQL |
| `frontend/` | SPA React 18 + TypeScript + Vite + Tailwind CSS |

## Fonctionnalités

| Rôle | Ce qu'il peut faire |
|---|---|
| Visiteur | Page d'accueil, liste des spécialités et de leurs jours de présence, inscription, connexion |
| Patient | Prise de rendez-vous en 5 étapes (spécialité, préconsultation facultative, suggestion d'orientation éventuelle, médecin et créneau, confirmation), consultation de ses rendez-vous, changement de créneau ou annulation jusqu'à 24 h avant |
| Secrétaire | Liste des rendez-vous filtrable, planning des médecins (vue semaine), ajout de disponibilités, enregistrement d'indisponibilités, reprogrammation ou annulation des rendez-vous « à reprogrammer » |
| Médecin | Son planning du jour et de la semaine (lecture seule), détail d'un rendez-vous avec la préconsultation |
| Administrateur | Comptes et rôles (création d'un médecin avec sa spécialité, désactivation), spécialités, statistiques |

Les courriels au patient (confirmation, modification ou reprogrammation, annulation, rendez-vous à
reprogrammer) passent par une file d'attente et sont journalisés dans la table `notifications`.
Une tâche planifiée passe chaque heure les rendez-vous dépassés au statut « Passé ».
La préconsultation et la suggestion d'orientation **ne constituent pas un diagnostic**, et la plateforme
ne remplace pas l'accueil direct de la clinique.

## Architecture

```
Navigateur (React SPA)  ──HTTPS / JSON──>  API Laravel  ──SQL──>  PostgreSQL (Supabase)
                                              ├── File d'attente (database) → courriels
                                              └── Planificateur → statut « Passé »
```

- `backend/app/Http` : routes (`routes/api.php`, `routes/web.php`), middlewares `role` et
  `compte.actif`, contrôleurs, FormRequest, ressources JSON.
- `backend/app/Services` : `CreneauService`, `RendezVousService`, `OrientationService`,
  `IndisponibiliteService`, `StatistiqueService`, `PlanningService`, `NotificationService`,
  `UtilisateurService`.
- `backend/app/Policies` : `RendezVousPolicy`, `PreconsultationPolicy`, `UtilisateurPolicy`, `SpecialitePolicy`.
- `backend/config` : règles métier réglables (`rendezvous.php` : délai de 24 h et horizon de 30 jours ;
  `preconsultation.php` : saisie restreinte en psychiatrie ; `orientation.php` : termes d'orientation,
  **exemples à faire valider par un médecin** ; `clinique.php` : coordonnées reprises dans les courriels).
- `frontend/src` : pages par espace (`pages/patient`, `pages/secretariat`, `pages/medecin`, `pages/admin`),
  composants partagés, client Axios Sanctum (`lib/api.ts`).

## Prérequis

| Outil | Version utilisée |
|---|---|
| PHP | 8.3.26 (extensions `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `zip`) — **pas PHP 8.2** |
| Composer | 2.8 |
| Node.js / npm | 25.8 / 11.16 (Node 20.19 minimum pour Vite 7) |
| PostgreSQL | Supabase pour l'application ; PostgreSQL 17 local pour les tests |
| ngrok | pour la mise à disposition |

Sous Windows, le PHP 8.3 de Laragon convient : `C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe`.
Activer `extension=pdo_pgsql`, `extension=pgsql` et `extension=zip` dans son `php.ini`. Dans la suite,
`php` désigne ce PHP 8.3.

## Installation

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Renseigner dans `backend/.env` : la base de données (voir ci-dessous), `ADMIN_EMAIL`, `ADMIN_TELEPHONE`,
`ADMIN_PASSWORD`, et `DEMO_PASSWORD` si l'on charge les données de démonstration.

```bash
cd frontend
npm install
cp .env.example .env
```

### Base de données Supabase

Supabase sert **uniquement** de base PostgreSQL : ni Supabase Auth, ni Storage, ni API générées.

1. Tableau de bord Supabase → projet → bouton **Connect** → onglet **Connection string**.
2. Choisir :
   - **Direct connection** (port 5432) si le poste dispose d'IPv6 ;
   - sinon **Session pooler** (port 5432, IPv4) : l'utilisateur est de la forme `postgres.<ref-projet>` ;
   - **ne pas utiliser le Transaction pooler (port 6543)** : il n'accepte pas les requêtes préparées.
3. Reporter l'hôte, le port, la base, l'utilisateur et le mot de passe dans `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, avec `DB_SSLMODE=require`. Le mot de passe est celui
   de la base (Project Settings → Database → *Reset database password* si on l'a oublié).

Pour savoir si le poste dispose d'IPv6, dans PowerShell :
`Test-NetConnection db.<ref-projet>.supabase.co -Port 5432` (`TcpTestSucceeded : False` → session pooler).
Exemple de configuration avec le session pooler :

```
DB_CONNECTION=pgsql
DB_HOST=aws-0-eu-west-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<ref-projet>
DB_PASSWORD=<mot de passe de la base>
DB_SSLMODE=require
```

Le schéma est créé **uniquement** par les migrations Laravel, jamais à la main dans la console Supabase.
Utiliser **exclusivement des données fictives** (hébergement hors du Sénégal, loi n° 2008-12).

Un projet Supabase gratuit est mis en pause après 7 jours de faible activité : avant chaque
démonstration, ouvrir le tableau de bord et, si le projet est en pause, cliquer sur **Restore project**.

### Migrations et seeders

```bash
cd backend
php artisan migrate --seed                      # tables + 10 spécialités + compte administrateur
php artisan db:seed --class=DemoSeeder          # facultatif : données FICTIVES de démonstration
```

`DemoSeeder` crée une secrétaire (`secretaire.demo@exemple.test`), un médecin fictif par spécialité
(`medecin.<specialite>@exemple.test`, par exemple `medecin.orl@exemple.test`), trois patients
(`patient1.demo@exemple.test`…), quelques rendez-vous et une indisponibilité du médecin d'ORL avec
un rendez-vous « à reprogrammer ». Mot de passe commun : `DEMO_PASSWORD`.

## Lancement en développement

Trois terminaux (un quatrième pour le planificateur, voir « Tâche planifiée ») :

```bash
cd backend && php artisan serve --host=127.0.0.1 --port=8000
```

```bash
cd backend && php artisan queue:work
```

```bash
cd frontend && npm run dev
```

Ouvrir http://localhost:5173. Le serveur Vite relaie `/api`, `/sanctum`, `/login` et `/logout` vers
Laravel : la SPA et l'API partagent la même origine, condition de Sanctum en mode SPA.

### Courriels et file d'attente

Les courriels au patient (confirmation, modification ou reprogrammation, annulation, rendez-vous à
reprogrammer) sont placés dans la file d'attente `database` (`QUEUE_CONNECTION=database`) : **ils ne
partent que si un travailleur tourne**.

```bash
cd backend && php artisan queue:work
```

Pour traiter une seule fois les courriels en attente puis s'arrêter : `php artisan queue:work --stop-when-empty`.
Les envois en échec sont listés par `php artisan queue:failed` et relancés par `php artisan queue:retry all`.

En développement, deux possibilités dans `backend/.env` :

- **Mailpit** (fourni par Laragon : `C:\laragon\bin\mailpit\<version>\mailpit.exe`, ou menu Laragon) :
  `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`. Les courriels s'affichent sur
  http://localhost:8025.
- **Journal** : `MAIL_MAILER=log`, les courriels sont écrits dans `storage/logs/laravel.log`.

Chaque courriel crée une ligne dans la table `notifications` (objet, contenu) ; la colonne `date_envoi`
est renseignée quand l'envoi a réussi. Les coordonnées de la clinique reprises dans les courriels se
renseignent dans `.env` (`CLINIQUE_NOM`, `CLINIQUE_ADRESSE`, `CLINIQUE_TELEPHONE`, `CLINIQUE_EMAIL`).

### Tâche planifiée (planificateur)

La commande `rendezvous:marquer-passes` passe à « Passé » les rendez-vous **confirmés** dont l'heure
est dépassée (les rendez-vous « à reprogrammer » restent à traiter par la secrétaire). Elle est
planifiée **toutes les heures** dans `backend/routes/console.php` ; **elle ne s'exécute que si le
planificateur de Laravel tourne**.

```bash
cd backend && php artisan schedule:list                 # vérifier la planification
cd backend && php artisan rendezvous:marquer-passes     # exécution manuelle immédiate
```

**En développement**, un quatrième terminal laissé ouvert :

```bash
cd backend && php artisan schedule:work
```

**En démonstration sous Windows**, le planificateur de tâches appelle `schedule:run` chaque minute
(Laravel décide lui-même d'exécuter la commande à chaque heure pleine). Dans une invite de commandes
(`cmd`), en adaptant le chemin du dépôt :

```
schtasks /Create /TN "CroixBleue - planificateur Laravel" /SC MINUTE /MO 1 /TR "\"C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe\" \"C:\chemin\vers\plateformecroixbleue\backend\artisan\" schedule:run"
```

Ou, dans l'interface « Planificateur de tâches » : *Créer une tâche*, déclencheur « Une fois », répéter
toutes les **1 minute** pour une durée **indéfinie** ; action « Démarrer un programme » avec pour
programme le `php.exe` de Laragon, pour arguments `artisan schedule:run` et pour dossier de départ
`…\plateformecroixbleue\backend`. La tâche s'exécute tant que la session Windows est ouverte.
Pour la supprimer après la démonstration :

```
schtasks /Delete /TN "CroixBleue - planificateur Laravel" /F
```

## Mise à disposition avec ngrok

Sanctum en mode SPA exige que la SPA et l'API aient **la même origine** : on n'ouvre donc **qu'un seul
tunnel**. Prérequis : un compte ngrok et `ngrok config add-authtoken <jeton>` (une seule fois).
Le compte gratuit attribue un domaine fixe du type `<sous-domaine>.ngrok-free.dev`, affiché au
lancement de `ngrok http …` (et dans le tableau de bord ngrok, *Domains*).

### 1. Configurer `backend/.env` pour ce domaine

```
APP_URL=https://<sous-domaine>.ngrok-free.dev
SANCTUM_STATEFUL_DOMAINS=<sous-domaine>.ngrok-free.dev,localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000
SESSION_DOMAIN=<sous-domaine>.ngrok-free.dev
SESSION_SECURE_COOKIE=true
```

puis `php artisan config:clear`. Variante pratique : laisser `SESSION_DOMAIN=` **vide** (cookie
limité à l'hôte qui l'a émis) permet d'utiliser à la fois `http://localhost:5173` et l'adresse ngrok
sans changer la configuration (les navigateurs acceptent les cookies `Secure` sur `localhost`).
Pour revenir au développement local seul : `APP_URL=http://localhost:5173`, `SESSION_DOMAIN=` (vide)
et `SESSION_SECURE_COOKIE=false`.

**Performances avec Supabase** : garder `DB_PERSISTENT=true` (réutilise la connexion TLS) et activer
OPcache dans le `php.ini` de Laragon (`zend_extension=opcache`, `opcache.enable=1`,
`opcache.enable_cli=1`), car `php artisan serve` relit sinon tout Laravel à chaque requête.

### 2a. En développement : tunnel sur le serveur Vite

```bash
cd backend && php artisan serve --host=127.0.0.1 --port=8000
```

```bash
cd frontend && npm run dev
```

```bash
ngrok http 5173
```

Vite accepte les domaines ngrok (`server.allowedHosts` dans `vite.config.ts`) et relaie l'API vers Laravel.

### 2b. Pour la démonstration : SPA compilée servie par Laravel

```bash
cd frontend && npm run build
```

```bash
cd backend && php artisan serve --host=127.0.0.1 --port=8000
```

```bash
ngrok http 8000
```

`npm run build` écrit la SPA dans `backend/public/spa` ; la route de repli de `routes/web.php` renvoie
`index.html` pour toute URL qui n'est ni l'API ni Sanctum. Lancer aussi `php artisan queue:work`
(courriels) et le planificateur (voir ci-dessus).

Le compte ngrok gratuit affiche une page d'avertissement à la première visite d'un navigateur :
cliquer sur **Visit Site**. Avant chaque démonstration : vérifier que le projet Supabase n'est pas en
pause, lancer les serveurs, puis ouvrir l'adresse ngrok sur un smartphone.

## Tests

Les tests s'exécutent sur une base PostgreSQL **locale**, jamais sur Supabase (un garde-fou dans
`tests/TestCase.php` refuse un hôte Supabase).

```bash
createdb -U postgres croixbleue_test
cd backend
cp .env.testing.example .env.testing   # renseigner DB_PASSWORD, puis :
php artisan key:generate --env=testing
php artisan test
```
