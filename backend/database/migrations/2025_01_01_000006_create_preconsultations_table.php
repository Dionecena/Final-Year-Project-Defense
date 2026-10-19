<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preconsultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendez_vous_id')->unique()->constrained('rendez_vous')->cascadeOnDelete();
            $table->string('motif');
            $table->text('symptomes');
            $table->foreignId('specialite_suggeree_id')->nullable()->constrained('specialites');
            $table->timestamp('date_saisie')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preconsultations');
    }
};
