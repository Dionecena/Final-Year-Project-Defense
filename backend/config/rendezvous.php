<?php

return [

    /*
    | Délai minimal, en heures, avant l'heure du rendez-vous pendant lequel le patient
    | peut encore modifier ou annuler lui-même (RG08). Valeur à confirmer par la clinique.
    */
    'delai_modification_heures' => (int) env('RDV_DELAI_MODIFICATION_HEURES', 24),

    /*
    | Nombre de jours, à partir d'aujourd'hui, sur lesquels les créneaux sont calculés
    | par défaut (CreneauService).
    */
    'horizon_jours' => (int) env('RDV_HORIZON_JOURS', 30),

];
