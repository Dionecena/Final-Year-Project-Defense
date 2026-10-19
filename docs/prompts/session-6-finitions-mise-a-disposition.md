Tu reprends le développement de la plateforme « Prise de rendez-vous en ligne – Clinique Croix Bleue » (projet de mémoire). Les modules 1 à 9 et la tâche planifiée sont terminés et commités. Il reste les finitions du module 10 et les livrables.

**Avant d'écrire du code**, lis entièrement `CLAUDE.md`, `docs/CAHIER_DES_CHARGES.md` (sections 11, 13, 16 en particulier), `docs/DECISIONS.md` (tous les choix et écarts, qui alimentent le rapport), `README.md`, puis `git log`.

## 1. Page d'accueil à partir du modèle fourni
Le modèle est `docs/modele_accueil.html`. Reprends son **style** dans `frontend/src/pages/Accueil.tsx` (palette bleu médical, police Inter, hero, animations 3D sphère et onde cardiaque, étapes, bandeau final, pied de page), en composants React + Tailwind, mais **adapte le contenu** au cahier des charges :
- pas de nom « MediConsult » : intitulé « Prise de rendez-vous en ligne – Clinique Croix Bleue » ;
- supprimer les chiffres inventés (15 000 patients, 98 % de satisfaction…) : seul le nombre de spécialités, lu depuis `GET /api/specialites`, peut être affiché ;
- supprimer l'équipe de médecins nommés et les témoignages (faux) ; les remplacer par la liste des spécialités avec leurs jours de présence ;
- services limités à ce qui existe : prise de rendez-vous avec créneaux réellement disponibles et confirmation automatique, préconsultation facultative (avec la mention RG03), courriel de confirmation ; pas de dossier médical, messagerie, ordonnances, « chiffrement de bout en bout » ;
- étapes : créer un compte, choisir spécialité et créneau, recevoir la confirmation par courriel ;
- **RG13** : encadré bien visible « Vous pouvez toujours vous présenter directement à l'accueil de la clinique, sans rendez-vous » ;
- Three.js en paquet npm (version exacte), chargé en différé, animations désactivées sous 768 px et si `prefers-reduced-motion` ; aucun `onclick` en ligne, aucun `dangerouslySetInnerHTML`.
Demande-moi avant d'ajouter toute autre section.

## 2. Finitions responsives et accessibilité
Passe sur tous les écrans en 375 px et en bureau : pas de défilement horizontal, labels sur tous les champs, contrastes suffisants, focus visible, messages d'erreur reliés aux champs. Corrige ce qui ne va pas.

## 3. Supabase
Demande-moi les paramètres de connexion (session pooler ou connexion directe, **jamais** le port 6543), mets-les uniquement dans `backend/.env` (`DB_SSLMODE=require`), lance `php artisan migrate --seed` puis `DemoSeeder` sur Supabase. Vérifie que l'index unique partiel et les contraintes CHECK existent. Relève la version de PostgreSQL (`select version()`). Les tests restent sur la base locale.

## 4. Mise à disposition avec ngrok
Un seul tunnel (même origine pour Sanctum) :
- développement : ngrok sur le port 5173 de Vite ;
- démonstration : `npm run build`, Laravel sert `public/spa`, ngrok sur le port 8000.
Configure et documente `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, puis vérifie une connexion complète à travers le tunnel. Mesure aussi la durée d'une connexion (elle prenait 3 à 4 s en développement) et propose une amélioration si nécessaire.

## 5. README et RAPPORT.md
- `README.md` complet (section 16.2) : prérequis et versions, installation, `.env`, migrations et seeders, lancement (serveur, `queue:work`, planificateur), Supabase (récupération des paramètres, réactivation d'un projet en pause avant chaque démonstration), procédure ngrok, tests.
- `RAPPORT.md` (section 16.4) : état de chaque objectif (atteint / partiellement / non atteint) **justifié par un test ou une manipulation décrite** ; résultat de chaque test T01 à T13 (relance toute la suite et cite la sortie) ; versions exactes installées (PHP, Laravel, React, TypeScript, Tailwind, PostgreSQL local et Supabase, Node) ; écarts repris de `docs/DECISIONS.md` ; liste des écrans à capturer.

## Fin
`artisan test` au vert, `npm run build`, vérification dans le navigateur, commit « Module 10 : finitions, documentation et mise à disposition », compte rendu court. En cas d'ambiguïté, arrête-toi et pose-moi la question.
