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
|
| Une spécialité absente de cette liste (médecine générale, pédiatrie) accepte tout motif ; pour
| les autres, un motif sans aucun de leurs termes est signalé au patient comme ne correspondant
| pas à la spécialité choisie (message indicatif, la réservation reste possible).
*/

return [

    'regles' => [
        'ORL' => ['oreille', 'sinus', 'gorge', 'nez', 'audition'],
        'Cardiologie' => ['palpitations', 'coeur', 'tension', 'thoracique'],
        'Ophtalmologie' => ['vue', 'oeil', 'yeux'],
        'Dermatologie' => ['peau', 'eczema', 'bouton', 'demangeaison'],
        'Gynécologie' => ['grossesse', 'enceinte', 'regles', 'menstruation', 'contraception'],
        'Neurologie' => ['migraine', 'vertige', 'tremblement', 'epilepsie'],
        'Neurochirurgie' => ['hernie discale', 'colonne vertebrale'],
        'Psychiatrie' => ['anxiete', 'angoisse', 'depression', 'insomnie'],
        'Chirurgie générale' => ['appendicite', 'vesicule', 'kyste'],
    ],

];
