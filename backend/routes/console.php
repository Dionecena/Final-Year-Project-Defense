<?php

use Illuminate\Support\Facades\Schedule;

// Section 8.5 : rendez-vous confirmés dont l'heure est dépassée → « passé », toutes les heures.
// Le planificateur doit tourner : php artisan schedule:work (développement) ou schedule:run
// chaque minute (planificateur de tâches de Windows), voir le README.
Schedule::command('rendezvous:marquer-passes')->hourly()->withoutOverlapping();
