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

];
