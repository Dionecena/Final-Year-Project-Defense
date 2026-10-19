<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medecin_id')->constrained('medecins', 'user_id')->cascadeOnDelete();
            $table->smallInteger('jour_semaine');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->integer('duree_creneau');
            $table->timestamps();

            $table->index(['medecin_id', 'jour_semaine']);
        });

        DB::statement('ALTER TABLE disponibilites ADD CONSTRAINT disponibilites_jour_semaine_check CHECK (jour_semaine BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE disponibilites ADD CONSTRAINT disponibilites_heures_check CHECK (heure_fin > heure_debut)');
        DB::statement('ALTER TABLE disponibilites ADD CONSTRAINT disponibilites_duree_creneau_check CHECK (duree_creneau > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilites');
    }
};
