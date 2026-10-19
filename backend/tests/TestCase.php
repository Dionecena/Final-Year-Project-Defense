<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Garde-fou : les tests vident la base, ils ne doivent jamais viser Supabase.
        $hote = (string) config('database.connections.pgsql.host');
        if (str_contains($hote, 'supabase')) {
            throw new RuntimeException('Les tests ne doivent pas s\'exécuter sur la base Supabase : vérifiez .env.testing.');
        }
    }

    /** En-têtes d'une requête de la SPA (domaine déclaré dans SANCTUM_STATEFUL_DOMAINS). */
    protected function enTetesSpa(): array
    {
        return ['Referer' => 'http://localhost:5173/', 'Accept' => 'application/json'];
    }
}
