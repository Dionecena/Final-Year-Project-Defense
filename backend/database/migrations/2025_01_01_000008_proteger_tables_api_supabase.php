<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase expose automatiquement le schéma public par son API REST (rôles anon et
 * authenticated). La plateforme n'utilise pas cette API (section 11) : on la ferme pour que les
 * données de santé ne soient lisibles que par l'API Laravel (section 10, loi n° 2008-12).
 *
 * - RLS activée sans aucune politique sur toutes les tables : refus pour tout rôle autre que le
 *   propriétaire des tables (celui de Laravel, qui n'est pas concerné par la RLS).
 * - Droits retirés aux rôles de l'API Supabase, y compris pour les tables futures.
 *
 * Sur un PostgreSQL local (tests), les rôles anon et authenticated n'existent pas : seule la RLS
 * est activée, sans effet pour le propriétaire.
 */
return new class extends Migration
{
    private const ROLES_API = ['anon', 'authenticated'];

    public function up(): void
    {
        foreach ($this->tablesPubliques() as $table) {
            DB::statement(sprintf('ALTER TABLE public.%s ENABLE ROW LEVEL SECURITY', $this->identifiant($table)));
        }

        foreach ($this->rolesApiExistants() as $role) {
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$role}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$role}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM {$role}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM {$role}");
        }
    }

    public function down(): void
    {
        foreach ($this->tablesPubliques() as $table) {
            DB::statement(sprintf('ALTER TABLE public.%s DISABLE ROW LEVEL SECURITY', $this->identifiant($table)));
        }
    }

    /** @return list<string> */
    private function tablesPubliques(): array
    {
        return array_map(
            fn ($ligne) => $ligne->tablename,
            DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"),
        );
    }

    /** @return list<string> */
    private function rolesApiExistants(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::ROLES_API), '?'));

        return array_map(
            fn ($ligne) => $ligne->rolname,
            DB::select("SELECT rolname FROM pg_roles WHERE rolname IN ({$placeholders})", self::ROLES_API),
        );
    }

    private function identifiant(string $nom): string
    {
        return '"'.str_replace('"', '""', $nom).'"';
    }
};
