<?php

/*
| Règles de la suggestion d'orientation (OrientationService).
|
| ATTENTION : les termes ci-dessous ne sont que des EXEMPLES destinés au développement
| et aux tests. La liste réelle doit être établie et validée par un médecin de la
| clinique avant toute mise en service. Cette suggestion est indicative et ne constitue
| pas un diagnostic (RG03).
|
| Clé : libellé exact de la spécialité. Valeur : termes recherchés dans le motif et les
| symptômes, écrits en minuscules et sans accents (le texte saisi est normalisé de la
| même façon avant la comparaison).
*/

return [

    'regles' => [
        'ORL' => ['oreille', 'sinus'],
        'Cardiologie' => ['palpitations'],
        'Ophtalmologie' => ['vue', 'oeil', 'yeux'],
        'Dermatologie' => ['peau', 'eczema'],
    ],

];
