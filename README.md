# Prise de rendez-vous en ligne – Clinique Croix Bleue

Plateforme de préconsultation et de prise de rendez-vous médicaux, complémentaire de l'accueil
direct de la clinique. Projet de mémoire de Licence en Génie Logiciel.

| Dossier | Contenu |
|---|---|
| `backend/` | API Laravel 12 (PHP 8.3), Sanctum en mode SPA, PostgreSQL |
| `frontend/` | SPA React 18 + TypeScript + Vite + Tailwind CSS |

> État : modules 1 (socle), 2 (gestion des utilisateurs), 3 (spécialités), 4 (plannings), 5 (créneaux et rendez-vous) et 6 (préconsultation et orientation) terminés. Ce README est complété à chaque module.

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
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, avec `DB_SSLMODE=require`.

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
(`patient1.demo@exemple.test`…) et quelques rendez-vous. Mot de passe commun : `DEMO_PASSWORD`.

## Lancement en développement

Trois terminaux :

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
