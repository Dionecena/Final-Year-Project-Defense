# Journal des décisions et écarts

Chaque session ajoute ici, à la fin de son module, les choix qu'elle a dû faire et les écarts
par rapport à `docs/CAHIER_DES_CHARGES.md`, en indiquant s'ils ont été validés par l'auteur.
Ce journal alimente la section « écarts » de `RAPPORT.md`.

## Environnement (module 1)

- **PHP 8.3.26** de Laragon (`C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe`), et non le
  PHP 8.2 de XAMPP présent dans le PATH. Extensions `pdo_pgsql`, `pgsql`, `zip` activées dans son
  `php.ini` (copie de sauvegarde : `php.ini.bak-croixbleue`). Validé par l'auteur.
- Dépôt Git propre au dossier `plateformecroixbleue/`. Validé par l'auteur.
- Base de test : PostgreSQL 17.5 local, base `croixbleue_test` (identifiants dans `backend/.env.testing`,
  non versionné). Base de développement locale `croixbleue_local` en attendant les paramètres Supabase.
- Versions : Laravel 12.70, Sanctum 4, React 18.3.1, React Router 6.30.1, Axios 1.12.2,
  TypeScript 5.9.3, Vite 7.1.9, Tailwind CSS 4.1.14, Node 25.8.1, npm 11.16.

## Module 1 — socle

- Pages React sous `/connexion` et `/inscription` (et non `/login`) : `/login` et `/logout` sont
  les routes POST de Laravel relayées par le proxy Vite.
- SPA compilée dans `backend/public/spa` (base `/spa/`), servie par une route de repli de
  `routes/web.php` qui exclut `api/`, `sanctum/` et `up`.
- Pas de colonne `remember_token` (non prévue au modèle ; pas de « se souvenir de moi »).
- Middleware `compte.actif` : un compte désactivé après sa connexion est déconnecté (401).
- Garde-fou dans `tests/TestCase.php` : les tests refusent de s'exécuter sur un hôte Supabase.
- `DemoSeeder` : « Tous les jours » traduit par lundi à dimanche ; plage fictive 09:00–12:00,
  créneaux de 30 min ; mot de passe commun `DEMO_PASSWORD` ; téléphone fictif `00 000 00 00`.
- Les identifiants initiaux sont lus via `config('auth.administrateur_initial')` et
  `config('auth.demo_password')` (compatibles avec `config:cache`).

## Module 2 — gestion des utilisateurs

- `telephone` obligatoire (non marqué nullable dans la section 5) ; `ADMIN_TELEPHONE` ajouté.
- `GET /api/specialites` réalisé dès le module 2 (minimal), car le formulaire de création d'un
  médecin en a besoin. À compléter au module 3.
- Règles décidées faute de précision (à confirmer par l'auteur) :
  - l'administrateur ne crée que des comptes du personnel (secrétaire, médecin, administrateur) ;
  - le rôle d'un patient ou d'un médecin n'est pas modifiable (les disponibilités et rendez-vous
    restent rattachés à la fiche médecin : désactiver et recréer) ;
  - l'administrateur ne peut ni désactiver son propre compte ni changer son propre rôle ;
  - la désactivation supprime les sessions ouvertes du compte ; la réactivation se fait par
    `PATCH … { actif: true }`.
- Mot de passe : 8 caractères minimum, au moins une lettre et un chiffre.
- Le même message générique « Identifiants incorrects. » est renvoyé pour un compte désactivé.
- Prévu pour le module 5 : un médecin désactivé ne doit plus proposer de créneaux.
