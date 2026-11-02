<?php

/*
| Coordonnées de la clinique reprises dans les courriels. À compléter dans .env :
| aucune donnée réelle n'est inscrite dans le dépôt.
*/

return [

    'nom' => env('CLINIQUE_NOM', 'Clinique Croix Bleue'),
    'adresse' => env('CLINIQUE_ADRESSE', ''),
    'telephone' => env('CLINIQUE_TELEPHONE', ''),
    'email' => env('CLINIQUE_EMAIL', ''),

    // Inscription : le domaine de l'adresse doit exister (enregistrement DNS MX ou A).
    'verifier_domaine_email' => (bool) env('VERIFIER_DOMAINE_EMAIL', true),

    // Soutenance : adresse du compte secrétaire ouvert d'un clic depuis la page de connexion.
    // Vide (par défaut) : la connexion de démonstration n'existe pas.
    'demo_secretariat' => env('DEMO_SECRETARIAT', ''),

];
