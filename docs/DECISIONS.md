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

## Module 3 — spécialités

- `GET, POST, PATCH /api/admin/specialites` ; aucune route de suppression (section 14), vérifié
  par un test (405).
- Libellé : obligatoire, 100 caractères, espaces superflus retirés, **unique sans tenir compte de
  la casse** (« orl » est refusé si « ORL » existe). Description facultative (1 000 caractères),
  une description vide est enregistrée à `null`.
- La liste de l'administrateur indique le nombre de médecins de chaque spécialité
  (`nombre_medecins`), absent de la liste publique.
- Le libellé sert de clé dans `config/preconsultation.php` (`saisie_restreinte`) et
  `config/orientation.php` : l'écran avertit l'administrateur qu'il faut le modifier avec prudence.
- Pas de `SpecialitePolicy` à enregistrer : le nom suit la convention de Laravel.
- Vérification dans le navigateur avec des comptes temporaires `verif.*@exemple.test` (les
  identifiants de `.env` ne sont pas lus par l'assistant), effacés par `migrate:fresh`.

## Module 4 — plannings

- **Chevauchement refusé** (validé par l'auteur) : une plage qui chevauche une disponibilité du
  même médecin et du même jour est refusée (422, message indiquant la plage existante). Des plages
  contiguës (09:00–12:00 puis 12:00–14:00) sont acceptées.
- **Route ajoutée `GET /api/medecins`** (secrétaire, lecture seule ; validée par l'auteur, écart
  par rapport à la section 14) : liste des médecins (id, nom, prénom, spécialité, actif) pour le
  choix du médecin. Les médecins désactivés y figurent, en fin de liste, marqués « désactivé ».
- **Rendez-vous annulés affichés** dans le planning avec leur statut (validé par l'auteur).
- RG14 : aucune route PUT, PATCH ni DELETE sur les disponibilités (T13 : 404/405 vérifiés).
- Validation de l'ajout : `jour_semaine` 1–7, heures au format HH:MM, fin postérieure au début,
  `duree_creneau` entier ≥ 1. Ajouts décidés : la durée du créneau ne doit pas dépasser la durée
  de la plage (sinon aucun créneau, section 8.1) ; un médecin désactivé ne reçoit aucune
  disponibilité (422 sur `medecin_id`) ; un identifiant qui n'est pas un médecin renvoie 404.
- Planning : `GET /api/medecins/{id}/planning?date=AAAA-MM-JJ` (secrétaire) et
  `GET /api/medecin/planning?date=` (médecin connecté, fiche lue sur la session, aucun paramètre
  de médecin accepté). Semaine du lundi au dimanche contenant la date (aujourd'hui par défaut).
  Réponse : `medecin`, `semaine {du, au}`, `disponibilites`, `indisponibilites` qui touchent la
  semaine, `rendez_vous` (date, heure, statut, nom et prénom du patient seulement). Aucune donnée
  de préconsultation (module 6). Dates et heures renvoyées en chaînes dans le fuseau
  Africa/Dakar, pour ne pas dépendre du fuseau du navigateur.
- Service `PlanningService` (lecture) ; l'ajout d'une disponibilité, trivial, reste dans le
  contrôleur après validation par `StoreDisponibiliteRequest`.
- Écrans : composant partagé `components/Planning.tsx` (vue semaine en colonnes sur grand écran,
  en liste sur smartphone ; vue jour), prêt à afficher indisponibilités et rendez-vous des
  modules 5 et 7. Le médecin et la date choisis sont conservés dans l'URL.
- Vérification dans le navigateur avec des comptes temporaires (secrétaire de vérification, mot
  de passe du médecin de démonstration de cardiologie changé), effacés par `migrate:fresh`.

## Module 5 — créneaux et rendez-vous

- **Changement de médecin** (tranché par l'auteur, **écart** par rapport à la section 8.2) : **seule la
  secrétaire** peut déplacer un rendez-vous vers un autre médecin de la même spécialité. Le patient ne
  change que le créneau, avec le même médecin (422 sur `medecin_id` sinon, l'écran ne lui propose que
  les créneaux de son médecin). Jamais vers une autre spécialité.
- Décision prise faute de réponse :
  - le patient peut modifier ou annuler lui-même un rendez-vous « à reprogrammer », dans le délai ;
    un changement de créneau le repasse à « confirmé ».
- `CreneauService` (section 8.1) : plages `[heure_debut, heure_fin[`, dernier créneau incomplet
  ignoré ; créneau retiré s'il a commencé (`debut <= maintenant`), s'il chevauche une indisponibilité
  ou s'il porte un rendez-vous `confirme`/`a_reprogrammer`. Une indisponibilité couvre
  `[date_debut, date_fin]` **bornes comprises** (cohérent avec la section 8.4 : un rendez-vous dont
  l'heure tombe dans la période est concerné). Médecins désactivés : aucun créneau. Indisponibilités
  et rendez-vous de la période chargés en une requête chacun. Tri par heure puis par nom de médecin.
- Horizon : `horizon_jours` jours **aujourd'hui compris** (30 → d'aujourd'hui à J+29). Période par
  défaut : tout l'horizon ; `au` au-delà de l'horizon ou avant `du` → 422 ; un `du` passé est ramené
  à aujourd'hui. Une réservation au-delà de l'horizon est refusée (422).
- Réservation : revérification dans la transaction ; créneau hors disponibilité, non aligné, passé,
  sous indisponibilité ou médecin désactivé → **422** (champ `date_heure`/`medecin_id`) ; créneau déjà
  pris (revérification ou violation de l'index unique) → **409** « Ce créneau vient d'être réservé
  par quelqu'un d'autre. Veuillez choisir un autre créneau. » (`CreneauDejaReserveException`).
  Le chemin de l'index unique est testé en neutralisant la revérification.
- Paramètre `$preconsultation` de `RendezVousService::reserver` déjà prévu (insertion dans la
  transaction) ; la `FormRequest` ne l'accepte pas encore (module 6). Points d'appel des courriels
  marqués `// Module 8` après la transaction (réservation, modification, annulation).
- RG08 (`RendezVousPolicy`) : patient autorisé tant que `maintenant < date_heure − délai` (la borne
  exacte est refusée) ; message 403 invitant à contacter la clinique. La secrétaire n'a pas de délai.
  Un rendez-vous `annule` ou `passe`, **ou dont l'heure est dépassée** (statut pas encore mis à jour
  par la tâche planifiée), ne se modifie ni ne s'annule plus, même par la secrétaire.
- Format des dates échangées : `AAAA-MM-JJ HH:MM`, heure de Dakar. La ressource indique `modifiable`,
  `annulable` (calculés par la politique) et `limite_modification` pour l'affichage.
- `GET /api/secretariat/rendez-vous` : paginé par 20, trié par date croissante ; la secrétaire voit
  nom, prénom et téléphone du patient (pour le joindre). Le patient ne reçoit pas ce bloc.
- Assistant de réservation : les étapes 2 (préconsultation) et 3 (orientation) sont des emplacements ;
  « Passer cette étape » mène directement à l'étape 4 (RG02 ; RG04 : pas de suggestion sans
  préconsultation). En cas de 409, retour à l'étape 4 avec le message et rechargement des créneaux.
  La page « Spécialités » propose un lien de réservation avec la spécialité présélectionnée.
- Le planning (module 4) affichait déjà les rendez-vous de la semaine ; vérifié après réservation.
- `DemoSeeder` : ajout d'un rendez-vous de pédiatrie (modifiable), d'un rendez-vous annulé et d'un
  rendez-vous passé pour le premier patient de démonstration.
- Vérification dans le navigateur avec des comptes temporaires `verif.*@exemple.test`, effacés par
  `migrate:fresh`. Les ports 8000/5173 étant occupés par une autre session, la SPA compilée a été
  servie sur le port 8001 (ajout temporaire à `SANCTUM_STATEFUL_DOMAINS`, retiré ensuite).

## Module 6 — préconsultation et orientation

- **Égalité de correspondances** (validé par l'auteur) : la spécialité listée en premier dans
  `config/orientation.php` l'emporte ; le médecin qui valide la liste en fixe donc aussi la priorité.
- **`specialite_suggeree_id`** (validé par l'auteur) : renseignée seulement si le patient conserve sa
  spécialité malgré la suggestion (section 8.3). S'il choisit la spécialité suggérée, elle reste
  `null`, donc une suggestion suivie n'est pas tracée : la statistique « suggestions émises » du
  module 9 ne comptera que les suggestions non suivies.
- La spécialité suggérée n'est **jamais reçue du client** : `RendezVousService::reserver` la recalcule
  avec `OrientationService` sur la spécialité du médecin réservé.
- Réservation : `POST /api/rendez-vous` accepte `preconsultation: { motif, symptomes }` (facultative,
  RG02). Toute autre clé est refusée (422, collecte minimale). Motif : 255 caractères (VARCHAR) ;
  symptômes : `Specialite::maxSymptomes()`, soit 500 pour `saisie_restreinte` et 2 000 sinon (RG11).
  La modification d'un rendez-vous (`PATCH`) ne touche pas à la préconsultation.
- `POST /api/orientation/verification` (patient) : `{ specialite_id, motif, symptomes }`, mêmes règles ;
  réponse `{ suggestion: null | { specialite, creneaux (3 au plus) } }`. Rien n'est enregistré.
- Orientation : texte normalisé (minuscules, `Str::ascii`, tout ce qui n'est ni lettre ni chiffre
  devient une espace). **Un terme correspond s'il commence un mot** : « oreille » trouve
  « oreilles », mais « vue » ne trouve pas « prévue ». Le décompte porte sur les termes distincts
  trouvés. Les libellés de la configuration absents de la base sont ignorés. Seuls les termes
  d'exemple sont conservés, avec l'avertissement de validation par un médecin.
- `GET /api/rendez-vous/{id}/preconsultation` : groupe `role:patient,medecin`, puis
  `PreconsultationPolicy` (médecin du rendez-vous ou patient concerné). L'autorisation passe avant le
  test d'existence : un tiers reçoit 403, jamais 404. Le médecin ou le patient concerné reçoit 404
  s'il n'y a pas de préconsultation.
- La secrétaire voit seulement le booléen `a_preconsultation` (sa liste et le planning) ; le planning
  du médecin le contient aussi. `RendezVousResource` n'expose aucune donnée de préconsultation.
- `GET /api/specialites` expose `saisie_restreinte` et `max_symptomes`, pour que la limite ne soit
  définie qu'à un seul endroit (configuration).
- Écrans : étape 2 (formulaire, compteur, champ plus court et consigne simple en psychiatrie, mention
  RG03, « Passer cette étape ») ; étape 3 seulement s'il y a une suggestion (mention RG03, 3 créneaux
  affichés à titre d'information, « Conserver ma spécialité » / « Choisir la spécialité suggérée »).
  Si le patient suit la suggestion et que ses symptômes dépassent la limite de la nouvelle
  spécialité, il revient à l'étape 2. Médecin : un clic sur un rendez-vous de son planning ouvre son
  détail et la préconsultation (`?rdv=id` dans l'URL), sans nouvelle route API. Composant `ZoneTexte`
  ajouté à `Formulaire.tsx`.
- `DemoSeeder` : deux préconsultations fictives (une sans suggestion ; une en ophtalmologie où la
  suggestion Dermatologie n'a pas été suivie).
- Vérification dans le navigateur (SPA compilée sur le port 8001, ports 8000/5173 occupés par une
  autre session ; ajout temporaire dans `SANCTUM_STATEFUL_DOMAINS`, retiré ensuite). La base de
  développement n'a pas été réinitialisée (le `DemoSeeder` est idempotent). Comptes temporaires
  `verif.*@exemple.test`, supprimés après la vérification avec leurs rendez-vous.
- Remarque hors module : les boutons radio des spécialités (étape 1, module 5) n'ont pas de nom
  accessible propre (« on » dans l'arbre d'accessibilité) ; à reprendre aux finitions.

## Module 7 — indisponibilités

- `POST /api/medecins/{id}/indisponibilites` (secrétaire) : `{ date_debut, date_fin, motif? }`, dates
  `AAAA-MM-JJ HH:MM` (heure de Dakar), motif 255 caractères. Réponse 201
  `{ data: { indisponibilite, rendez_vous } }` : `rendez_vous` est la liste des rendez-vous passés à
  « à reprogrammer » (`RendezVousResource`, avec le téléphone du patient), vide si aucun.
- `IndisponibiliteService::enregistrer` (section 8.4) : `date_fin >= date_debut` (validation 422, revérifiée
  dans le service, et contrainte CHECK) ; transaction qui crée l'indisponibilité
  (`enregistree_par` = secrétaire connectée) puis passe à `a_reprogrammer` les rendez-vous `confirme`
  du médecin dont l'heure est dans `[date_debut, date_fin]`, **bornes comprises** (même convention
  que `CreneauService`, module 5). Les lignes sont verrouillées (`lockForUpdate`).
- **Décisions prises faute de précision, validées par l'auteur** :
  - une indisponibilité dont la fin est déjà passée est refusée (422 « Cette période est déjà passée. ») ;
  - un rendez-vous dont l'heure est déjà passée n'est pas concerné (il passera à « passé » par la tâche
    planifiée et ne pourrait de toute façon plus être reprogrammé) ;
  - une indisponibilité peut chevaucher une autre, ou viser un médecin désactivé ;
  - aucune route de modification ni de suppression d'une indisponibilité (non prévue section 14 ;
    vérifié par un test, 404).
- **Aucune route ajoutée pour le traitement** : « Reprogrammer » utilise `PATCH /api/rendez-vous/{id}`
  (nouveau créneau libre, même médecin ou autre médecin de la même spécialité, retour à `confirme`) et
  « Annuler » `POST /api/rendez-vous/{id}/annulation`. La liste « À reprogrammer » est
  `GET /api/secretariat/rendez-vous?statut=a_reprogrammer`. Un créneau encore couvert par
  l'indisponibilité est refusé (422) par la revérification du module 5.
- Créneaux et planning : rien à modifier, `CreneauService` (module 5) retirait déjà les créneaux
  couverts et le planning (module 4) affichait déjà les indisponibilités ; vérifié par des tests.
- Écrans : « Enregistrement d'une indisponibilité » (`/secretariat/indisponibilites`, médecin
  présélectionnable par `?medecin=id`) affiche aussitôt les rendez-vous concernés avec « Reprogrammer »
  et « Annuler », puis le planning de la semaine du début ; « Rendez-vous à reprogrammer »
  (`/secretariat/a-reprogrammer`). La ligne de rendez-vous de la secrétaire est extraite dans
  `components/LigneRendezVous.tsx` (bouton « Reprogrammer » pour un rendez-vous à reprogrammer,
  « Modifier » sinon) et partagée avec la liste des rendez-vous.
- `DemoSeeder` : une indisponibilité fictive (matinée du médecin d'ORL) et un rendez-vous « à
  reprogrammer », créés directement (sans le service) pour n'envoyer aucun courriel au chargement.
- Les courriels au patient (passage à « à reprogrammer », reprogrammation, annulation) sont réalisés
  au module 8.
- Vérification dans le navigateur (SPA compilée sur le port 8001, ports 8000/5173 occupés par une
  autre session ; ajout temporaire dans `SANCTUM_STATEFUL_DOMAINS` et `.claude/launch.json`, retiré
  ensuite), avec un compte temporaire `verif.secretaire@exemple.test`.

## Module 8 — notifications

- Une classe par événement dans `app/Notifications`, toutes filles de `NotificationRendezVous`
  (`ShouldQueue`, canal `mail` seul, `afterCommit()`) : `ConfirmationRendezVous`,
  `ModificationRendezVous` (modification par le patient ou la secrétaire ; `reprogrammation` vrai si le
  rendez-vous était « à reprogrammer », objet « Reprogrammation de votre rendez-vous »),
  `AnnulationRendezVous` (`parPatient` : texte différent selon l'auteur), `RendezVousAReprogrammer`.
- Points d'envoi, tous **après** la transaction : `RendezVousService::reserver`, `::modifier`,
  `::annuler` (qui reçoit désormais l'auteur de l'annulation) et `IndisponibiliteService::enregistrer`
  (un courriel par rendez-vous concerné, aucun si la liste est vide). Un 409 ou un 422 n'envoie rien.
- **Journal** (`NotificationService`) : la ligne de `notifications` (objet, contenu en texte brut) est
  créée au moment où le courriel est placé dans la file, avec `date_envoi` vide ; l'écouteur de
  `NotificationSent` (enregistré dans `AppServiceProvider`) renseigne `date_envoi` quand le canal
  `mail` a réussi. Un courriel en échec reste donc visible avec `date_envoi` vide. Le canal
  `database` de Laravel n'est pas utilisé.
- **Contenu figé à la création** : l'objet et les paragraphes sont calculés quand l'événement se
  produit (et non au passage de la file), pour que le journal et le courriel soient identiques même si
  le rendez-vous change entre-temps. La notification ne transporte que l'identifiant du rendez-vous
  et ces textes, jamais le modèle ni la préconsultation.
- Contenu : salutation au nom du patient, date en toutes lettres, heure, médecin, spécialité, rappel de
  l'accueil direct (RG13), coordonnées de `config/clinique.php` (les valeurs vides sont omises). Le
  courriel de modification rappelle l'ancien créneau ; celui de confirmation rappelle le délai de RG08.
  **Aucune donnée de préconsultation** (vérifié par un test sur le courriel réellement envoyé et sur
  le journal). Pas de bouton d'action (pas de lien vers l'application dans le corps).
- `lang/fr.json` traduit les textes du gabarit de courriel de Laravel (« Tous droits réservés. »…).
- Propriétés des notifications non `readonly` : la désérialisation de la file ne peut pas les initialiser.
- Tests : T11 complété (courriel à chaque patient concerné, aucun pour une indisponibilité sans
  rendez-vous, avec `Notification::fake()` puis `Queue::fake()`), un courriel par événement,
  passage par la file avec `afterCommit`, contenu du courriel (transport `array`), `date_envoi`
  renseignée après envoi et vide sans envoi effectif, aucun envoi si la transaction échoue (409, 422).
- Vérification : Mailpit 1.22.3 de Laragon (SMTP 1025, interface 8025), `MAIL_MAILER=smtp` le temps de la
  vérification puis retour à `log` ; indisponibilité, annulation et reprogrammation par la secrétaire
  dans le navigateur, 3 tâches dans `jobs`, `queue:work --stop-when-empty`, 3 courriels reçus en
  français et 3 lignes du journal avec `date_envoi`. La confirmation et la modification par le patient
  sont couvertes par les tests. Données de vérification supprimées ensuite et `DemoSeeder` relancé.

## Module 9 — statistiques

- `GET /api/admin/statistiques?du=AAAA-MM-JJ&au=AAAA-MM-JJ` (administrateur ; middleware `role` et
  `authorize()` de `PeriodeStatistiquesRequest`). Contrôleur `Admin\StatistiqueController`, service
  `StatistiqueService`, ressource `StatistiquesResource`.
- **Regroupement par mois** (et non par semaine) : sur la période par défaut, 7 barres au lieu d'une
  trentaine, lisibles sur smartphone. Les mois sans rendez-vous apparaissent à zéro.
- **Période** (décisions prises faute de précision, validées par l'auteur) : bornes comprises, heure
  de Dakar. Par défaut, du 1er jour du mois cinq mois en arrière au dernier jour du mois suivant (six
  mois écoulés et les rendez-vous déjà pris pour le mois à venir). `au` avant `du` → 422 ; période de
  24 mois ou plus → 422 (lisibilité).
- **Rattachement à la période** : rendez-vous à leur `date_heure`, tous statuts confondus ;
  préconsultations à la date **de leur rendez-vous** (et non `date_saisie`), pour rester comparables au
  nombre de rendez-vous ; indisponibilités comptées si elles **touchent** la période.
- Réponse : `rendez_vous` (`total`, `par_statut` avec les quatre statuts et leur libellé français,
  `par_specialite` avec les dix spécialités triées par nombre décroissant, `par_mois`),
  `preconsultations` (`total`, `suggestions`), `indisponibilites` (`total`). Aucune donnée nominative,
  aucun contenu de préconsultation (vérifié par un test).
- `suggestions` = préconsultations dont `specialite_suggeree_id` est renseignée, c'est-à-dire les
  suggestions **non suivies** (décision du module 6) ; l'écran le précise sous les chiffres clés.
- Six requêtes agrégées (`GROUP BY` du query builder, `to_char(date_heure, 'YYYY-MM')` propre à
  PostgreSQL), quel que soit le volume : vérifié par un test sur le journal des requêtes.
- Écran « Statistiques » (`/admin`) : période dans l'URL, quatre chiffres clés, barres horizontales en
  CSS (aucune bibliothèque de graphiques), nombre écrit en clair à côté de chaque barre. Le composant
  `PageEnConstruction`, devenu inutile, est supprimé.
- `DemoSeeder` inchangé : ses rendez-vous, préconsultations et indisponibilité suffisent à alimenter
  l'écran.
- Vérification dans le navigateur (SPA compilée sur le port 8001, ports 8000/5173 occupés par une
  autre session ; ajouts temporaires dans `SANCTUM_STATEFUL_DOMAINS` et `.claude/launch.json`, retirés
  ensuite) avec un compte temporaire `verif.admin@exemple.test`, supprimé après : période par défaut,
  filtrage par mois, erreur de période, affichage à 375 px sans défilement horizontal.
- Correction hors module : caractère de contrôle dans le chemin de Mailpit du README.

## Module 10 — tâche planifiée

- Commande `rendezvous:marquer-passes` (`app/Console/Commands/MarquerRendezVousPasses.php`) : une seule
  requête `UPDATE` sur les rendez-vous `confirme` dont `date_heure <= maintenant` (même borne que
  `RendezVousPolicy` et `CreneauService`, qui considèrent un rendez-vous commencé à son heure exacte).
  Les statuts `a_reprogrammer`, `annule` et `passe` ne sont jamais modifiés. Elle affiche le nombre de
  rendez-vous traités et n'envoie **aucun courriel** (ce n'est pas un des quatre événements de la
  section 9 ; vérifié par un test).
- Planification : `Schedule::command('rendezvous:marquer-passes')->hourly()->withoutOverlapping()` dans
  `routes/console.php` (à chaque heure pleine). `withoutOverlapping()` ajouté par précaution (verrou dans
  le cache `database`).
- Tests (`TachePlanifieeTest`) : T12 (confirmés dépassés, y compris à l'heure exacte → `passe` ; futurs,
  « à reprogrammer », annulés et déjà passés inchangés, `CarbonImmutable::setTestNow()`), exécution
  répétée, et présence de la commande dans le planificateur avec l'expression `0 * * * *`.
- README : `schedule:work` en développement ; en démonstration, tâche Windows appelant `schedule:run`
  chaque minute (commande `schtasks` et marche à suivre dans l'interface). La création de la tâche
  Windows n'a pas été faite sur le poste (réglage du système, laissé à l'auteur).
- Vérification : `schedule:list` (prochaine exécution à l'heure pleine), `schedule:test`, puis sur la base
  de développement un rendez-vous confirmé fictif daté de trois jours plus tôt, passé à « passé » par la
  commande, puis supprimé.
