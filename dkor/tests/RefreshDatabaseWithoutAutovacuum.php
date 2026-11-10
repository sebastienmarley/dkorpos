<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * RefreshDatabase, sans l'autovacuum de PostgreSQL sur les tables de test.
 *
 * Chaque test est annulé (rollback) : ses lignes deviennent des lignes mortes, que l'autovacuum finit par nettoyer en
 * notant que la table est vide. Le planificateur choisit alors des plans pour une table vide (boucles imbriquées,
 * lectures séquentielles), et un test qui insère beaucoup dans sa transaction — le seeder de démo — passe de quelques
 * secondes à plusieurs dizaines de minutes, de façon intermittente. Sans effet sur SQLite.
 */
trait RefreshDatabaseWithoutAutovacuum
{
    use RefreshDatabase {
        migrateDatabases as protected migrateTestDatabases;
    }

    /**
     * Roule une fois par processus, juste après les migrations et avant la transaction du premier test.
     */
    protected function migrateDatabases(): void
    {
        $this->migrateTestDatabases();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE test_table regclass;
            BEGIN
                FOR test_table IN SELECT oid FROM pg_class WHERE relkind = 'r' AND relnamespace = current_schema()::regnamespace
                LOOP
                    EXECUTE format('ALTER TABLE %s SET (autovacuum_enabled = false)', test_table);
                END LOOP;
            END $$;
            SQL);
    }
}
