# Prise de rendez-vous en ligne – Clinique Croix Bleue

Projet de mémoire (Licence Génie Logiciel). **Référence absolue : `docs/CAHIER_DES_CHARGES.md`.**
Le code doit y correspondre exactement : aucune fonctionnalité hors périmètre, aucune retirée
sans le signaler. Décisions déjà prises : `docs/DECISIONS.md` (à lire avant de coder, à compléter
à la fin de chaque module).

## Règles de travail

- Un module à la fois (section 12 du cahier des charges). À la fin : migrations, tests, compte
  rendu court (fait / reste / problèmes), ajout dans `docs/DECISIONS.md`, commit du module.
- Règle ambiguë ou contrainte qui oblige à s'écarter du cahier des charges : **s'arrêter et poser
  la question à l'auteur**, ne pas décider seul.
- Aucun nom commercial ni logo ; intitulé « Prise de rendez-vous en ligne – Clinique Croix Bleue ».
- Aucune donnée réelle inventée : seules les 10 spécialités sont réelles. Tout le reste est fictif
  (`DemoSeeder`, adresses `@exemple.test`, téléphones `00 000 00 0x`).
- Interface entièrement en français ; code nommé d'après le modèle de la section 5.
- Fuseau `Africa/Dakar`.

## Environnement (Windows)

- PHP : **toujours** `C:/laragon/bin/php/php-8.3.26-Win32-vs16-x64/php.exe` (le `php` du PATH est
  un PHP 8.2 interdit). Composer : `<php> C:/laragon/bin/composer/composer.phar`.
- Tests : `cd backend && <php> artisan test` (PostgreSQL local `croixbleue_test`, `.env.testing`).
  Ne jamais lancer les tests sur Supabase.
- Base de développement : `croixbleue_local` (locale) tant que Supabase n'est pas configuré.
  Réinitialiser : `<php> artisan migrate:fresh --seed && <php> artisan db:seed --class=DemoSeeder`.
- Serveurs de développement : `.claude/launch.json` (`api-laravel` sur 8000, `frontend-vite` sur 5173).
- Build : `cd frontend && npm run build` (vérifie aussi les types) → `backend/public/spa`.
- Identifiants de test locaux : `backend/.env` (`ADMIN_*`, `DEMO_PASSWORD`). Ne jamais les commiter
  ni les recopier dans les messages.

## Conventions du code

Backend (`backend/`) :
- Routes : `routes/api.php` (groupe `auth:sanctum` + `compte.actif`, puis `role:<role>`) ;
  `/login`, `/logout` et la route de repli de la SPA dans `routes/web.php`.
- Validation dans des FormRequest (`app/Http/Requests`, trait `Concerns/ReglesUtilisateur`).
- Contrôleurs minces → services (`app/Services`), politiques (`app/Policies`, enregistrées dans
  `AppServiceProvider` si le nom ne suit pas la convention, ex. `UtilisateurPolicy` pour `User`).
- Réponses via `JsonResource` (enveloppe `data`), jamais `password`.
- Enums `App\Enums\Role` et `App\Enums\StatutRdv` (libellés français, `StatutRdv::actifs()`).
- Configs métier : `config/rendezvous.php`, `config/preconsultation.php`, `config/orientation.php`,
  `config/clinique.php`.
- Erreurs JSON en français (`bootstrap/app.php`) ; messages de validation dans `lang/fr/validation.php`
  (ajouter les attributs manquants).
- Tests de fonctionnalités dans `tests/Feature`, nommés en français ; les tests du cahier des charges
  s'appellent `test_tXX_…`. `UserFactory` : `->secretaire()`, `->medecin($specialite)`,
  `->administrateur()`, `->inactif()`.

Frontend (`frontend/src/`) :
- Client `lib/api.ts` (`api`, `initialiserCsrf`, `erreursChamps`, `messageErreur`).
- `auth/AuthContext.tsx` (`useAuth`), `auth/RequireRole.tsx`, `components/Layout.tsx`
  (liens par rôle), `components/Formulaire.tsx` (`Champ`, `Liste`, `Alerte`, `Bouton`).
- `types.ts` : `LIBELLES_STATUT`, `LIBELLES_ROLE`, `MENTION_NON_DIAGNOSTIC`.
- Routes dans `App.tsx` ; remplacer les `PageEnConstruction` au fil des modules.
- Mobile d'abord, labels sur tous les champs, pas de `dangerouslySetInnerHTML`.
