<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preuve du consentement (loi n° 2008-12) : date à laquelle le patient a accepté la politique de
 * confidentialité et les conditions d'utilisation à l'inscription, puis le traitement de ses
 * données de santé à la préconsultation. Nulle pour les comptes du personnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('date_consentement')->nullable();
        });

        Schema::table('preconsultations', function (Blueprint $table) {
            $table->timestamp('date_consentement')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_consentement');
        });

        Schema::table('preconsultations', function (Blueprint $table) {
            $table->dropColumn('date_consentement');
        });
    }
};
