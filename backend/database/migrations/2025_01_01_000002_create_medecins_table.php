<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Données propres au médecin, liées à son compte dans users.
        Schema::create('medecins', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users');
            $table->foreignId('specialite_id')->constrained('specialites');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medecins');
    }
};
