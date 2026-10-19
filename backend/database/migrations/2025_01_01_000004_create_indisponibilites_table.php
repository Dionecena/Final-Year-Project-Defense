<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indisponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medecin_id')->constrained('medecins', 'user_id')->cascadeOnDelete();
            $table->foreignId('enregistree_par')->constrained('users');
            $table->timestamp('date_debut');
            $table->timestamp('date_fin');
            $table->string('motif')->nullable();
            $table->timestamps();

            $table->index(['medecin_id', 'date_debut', 'date_fin']);
        });

        DB::statement('ALTER TABLE indisponibilites ADD CONSTRAINT indisponibilites_dates_check CHECK (date_fin >= date_debut)');
    }

    public function down(): void
    {
        Schema::dropIfExists('indisponibilites');
    }
};
