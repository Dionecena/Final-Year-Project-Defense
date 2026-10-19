<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('medecin_id')->constrained('medecins', 'user_id');
            $table->timestamp('date_heure');
            $table->string('statut', 20)->default('confirme');
            $table->timestamps();

            $table->index(['patient_id', 'date_heure']);
            $table->index(['statut', 'date_heure']);
        });

        DB::statement("ALTER TABLE rendez_vous ADD CONSTRAINT rendez_vous_statut_check CHECK (statut IN ('confirme', 'annule', 'a_reprogrammer', 'passe'))");

        // Un créneau ne peut porter qu'un seul rendez-vous actif (RG05, RG07).
        DB::statement("CREATE UNIQUE INDEX rendez_vous_creneau_actif_unique ON rendez_vous (medecin_id, date_heure) WHERE statut IN ('confirme', 'a_reprogrammer')");
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
