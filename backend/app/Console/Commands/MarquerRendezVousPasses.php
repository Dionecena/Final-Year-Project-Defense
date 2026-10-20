<?php

namespace App\Console\Commands;

use App\Enums\StatutRdv;
use App\Models\RendezVous;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Section 8.5 : passe à « passé » les rendez-vous confirmés dont l'heure est atteinte ou dépassée
 * (même borne que RendezVousPolicy). Les rendez-vous « à reprogrammer » ne sont pas touchés : la
 * secrétaire doit encore les traiter. Planifiée toutes les heures (routes/console.php).
 */
class MarquerRendezVousPasses extends Command
{
    protected $signature = 'rendezvous:marquer-passes';

    protected $description = 'Passe à « passé » les rendez-vous confirmés dont l\'heure est dépassée';

    public function handle(): int
    {
        $nombre = RendezVous::query()
            ->where('statut', StatutRdv::Confirme->value)
            ->where('date_heure', '<=', CarbonImmutable::now())
            ->update(['statut' => StatutRdv::Passe->value]);

        $this->info("{$nombre} rendez-vous marqué(s) comme passé(s).");

        return self::SUCCESS;
    }
}
