<?php

// Messages de validation en français (règles utilisées par l'application).

return [

    'accepted' => 'Le champ :attribute doit être accepté.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'array' => 'Le champ :attribute doit être une liste.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'between' => [
        'numeric' => 'La valeur de :attribute doit être comprise entre :min et :max.',
        'string' => 'Le texte :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'date' => 'Le champ :attribute n\'est pas une date valide.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'email' => 'Le champ :attribute doit être une adresse électronique valide.',
    'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
    'gt' => [
        'numeric' => 'La valeur de :attribute doit être supérieure à :value.',
    ],
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'max' => [
        'numeric' => 'La valeur de :attribute ne peut pas dépasser :max.',
        'string' => 'Le texte :attribute ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'numeric' => 'La valeur de :attribute doit être au moins :min.',
        'string' => 'Le texte :attribute doit contenir au moins :min caractères.',
    ],
    'not_in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute apparaît dans une fuite de données. Choisissez-en un autre.',
    ],
    'prohibited' => 'Le champ :attribute n\'est pas autorisé.',
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigné.',
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',

    'custom' => [
        'email' => [
            'unique' => 'Cette adresse électronique est déjà utilisée.',
        ],
    ],

    'attributes' => [
        'nom' => 'nom',
        'prenom' => 'prénom',
        'email' => 'adresse électronique',
        'telephone' => 'téléphone',
        'password' => 'mot de passe',
        'role' => 'rôle',
        'actif' => 'actif',
        'specialite_id' => 'spécialité',
        'libelle' => 'libellé',
        'description' => 'description',
        'jour_semaine' => 'jour de la semaine',
        'heure_debut' => 'heure de début',
        'heure_fin' => 'heure de fin',
        'duree_creneau' => 'durée du créneau',
        'date_debut' => 'date de début',
        'date_fin' => 'date de fin',
        'motif' => 'motif',
        'symptomes' => 'symptômes',
        'medecin_id' => 'médecin',
        'date_heure' => 'date et heure',
        'du' => 'date de début',
        'au' => 'date de fin',
    ],

];
