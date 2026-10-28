<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UtilisateurPolicy;
use App\Services\NotificationService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UtilisateurPolicy::class);

        // Journal des courriels : date_envoi renseignée quand l'envoi a réussi (section 9).
        Event::listen(NotificationSent::class, [NotificationService::class, 'marquerEnvoyee']);

        // Garde-fou : la base Supabase de la démonstration ne peut pas être vidée par erreur
        // (migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback, db:wipe refusés).
        DB::prohibitDestructiveCommands(str_contains((string) config('database.connections.pgsql.host'), 'supabase'));
    }
}
